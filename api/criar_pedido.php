<?php
// api/criar_pedido.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/pix.php';
require_once __DIR__ . '/../includes/mercadopago.php';

try {
    $raw = file_get_contents('php://input');
    $dados = json_decode($raw, true);

    if (!$dados) {
        echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos recebidos.']);
        exit;
    }

    if (empty($dados['itens'])) {
        echo json_encode(['sucesso' => false, 'erro' => 'A sacola está vazia.']);
        exit;
    }

    $nomeCliente = trim($dados['cliente']['nome'] ?? '');
    $wppCliente = trim($dados['cliente']['whatsapp'] ?? '');

    if (empty($nomeCliente) || empty($wppCliente)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Nome e WhatsApp são obrigatórios.']);
        exit;
    }

    $estabId = (int)($dados['estabelecimento_id'] ?? 1);
    $tipoEntrega = $dados['tipo_entrega'] ?? 'delivery';
    $taxaEntrega = (float)($dados['taxa_entrega'] ?? 0);
    $subtotal = (float)($dados['subtotal'] ?? 0);
    $total = (float)($dados['total'] ?? 0);
    $formaPagamento = $dados['forma_pagamento'] ?? 'pix';
    $trocoPara = (!empty($dados['troco_para']) && is_numeric($dados['troco_para'])) ? (float)$dados['troco_para'] : null;
    $observacoes = $dados['observacoes'] ?? '';

    $endereco = $dados['cliente']['endereco'] ?? '';
    $bairro = $dados['cliente']['bairro'] ?? '';
    $complemento = $dados['cliente']['complemento'] ?? '';

    $pdo->beginTransaction();

    // Pedidos pagos na entrega (dinheiro/cartão) já entram liberados pra
    // produção. Pedidos via Pix ficam "pendente" até a loja confirmar o
    // recebimento (automaticamente, se o Mercado Pago estiver configurado,
    // ou manualmente pelo painel, caso contrário) — assim a cozinha não
    // começa a preparar um pedido que ainda não foi pago.
    $statusPagamentoInicial = ($formaPagamento === 'pix') ? 'pendente' : 'nao_aplicavel';

    $stmtPed = $pdo->prepare("
        INSERT INTO pedidos (
            estabelecimento_id, cliente_nome, cliente_whatsapp, 
            cliente_endereco, cliente_bairro, cliente_complemento, 
            tipo_entrega, taxa_entrega, subtotal, total, 
            forma_pagamento, troco_para, observacoes, status, status_pagamento, criado_em
        ) VALUES (
            :estab, :nome, :wpp, 
            :endereco, :bairro, :compl, 
            :tipo, :taxa, :subtotal, :total, 
            :forma, :troco, :obs, 'novo', :status_pag, NOW()
        )
    ");

    $stmtPed->execute([
        ':estab' => $estabId,
        ':nome' => $nomeCliente,
        ':wpp' => $wppCliente,
        ':endereco' => $endereco,
        ':bairro' => $bairro,
        ':compl' => $complemento,
        ':tipo' => $tipoEntrega,
        ':taxa' => $taxaEntrega,
        ':subtotal' => $subtotal,
        ':total' => $total,
        ':forma' => $formaPagamento,
        ':troco' => $trocoPara,
        ':obs' => $observacoes,
        ':status_pag' => $statusPagamentoInicial
    ]);

    $pedidoId = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO pedido_itens (
            pedido_id, produto_id, quantidade, preco_unitario, subtotal, observacao
        ) VALUES (
            :ped, :prod, :qtd, :preco, :subtotal, :obs
        )
    ");

    // Busca o preço real de cada produto no banco para não confiar cegamente
    // no valor enviado pelo navegador (evita alguém alterar o preço no console
    // do navegador antes de enviar o pedido).
    $stmtPrecoReal = $pdo->prepare("SELECT preco FROM produtos WHERE id = :id AND estabelecimento_id = :estab");

    $totalRecalculado = 0;

    foreach ($dados['itens'] as $item) {
        $precoUnitEnviado = (float)($item['preco_total_unitario'] ?? $item['preco_base'] ?? 0);
        $qtd = max(1, (int)($item['qtd'] ?? 1));
        $produtoId = !empty($item['id']) ? (int)$item['id'] : null;
        $temAdicionais = !empty($item['adicionais_texto']);

        // Itens simples (sem sabor/adicionais escolhidos) têm o preço
        // conferido contra o valor cadastrado no banco. Itens com pizza
        // fracionada ou adicionais mantêm o cálculo feito no cardápio,
        // pois combinam vários preços — mas nunca aceitamos valor <= 0.
        $precoUnit = $precoUnitEnviado;
        if ($produtoId && !$temAdicionais) {
            $stmtPrecoReal->execute([':id' => $produtoId, ':estab' => $estabId]);
            $produtoReal = $stmtPrecoReal->fetch(PDO::FETCH_ASSOC);
            if ($produtoReal) {
                $precoUnit = (float)$produtoReal['preco'];
            }
        }
        if ($precoUnit <= 0) {
            $precoUnit = $precoUnitEnviado > 0 ? $precoUnitEnviado : 0.01;
        }

        $sub = (float)($precoUnit * $qtd);
        $totalRecalculado += $sub;
        $obsItem = trim(($item['adicionais_texto'] ?? '') . ' ' . ($item['obs_item'] ?? ''));

        $stmtItem->execute([
            ':ped' => $pedidoId,
            ':prod' => $produtoId,
            ':qtd' => $qtd,
            ':preco' => $precoUnit,
            ':subtotal' => $sub,
            ':obs' => $obsItem
        ]);
    }

    // Se o total enviado divergir muito do recalculado (produtos simples),
    // ajusta o total gravado para refletir os itens conferidos + taxa de entrega.
    $totalConferido = round($totalRecalculado + $taxaEntrega, 2);
    if (abs($totalConferido - $total) > 0.05) {
        $stmtAjusteTotal = $pdo->prepare("UPDATE pedidos SET subtotal = :sub, total = :total WHERE id = :id");
        $stmtAjusteTotal->execute([
            ':sub' => round($totalRecalculado, 2),
            ':total' => $totalConferido,
            ':id' => $pedidoId
        ]);
    }

    $pdo->commit();

    // --- Geração do Pix (se for a forma de pagamento escolhida) ---
    $respostaPix = [];
    if ($formaPagamento === 'pix') {
        $stmtEstabPix = $pdo->prepare("SELECT nome, chave_pix, nome_pix, cidade_pix, mercadopago_access_token FROM estabelecimentos WHERE id = :id");
        $stmtEstabPix->execute([':id' => $estabId]);
        $estabPix = $stmtEstabPix->fetch(PDO::FETCH_ASSOC);

        $tokenMp = trim($estabPix['mercadopago_access_token'] ?? '');
        $valorFinal = $totalConferido; // total já validado no servidor, calculado acima

        if ($tokenMp !== '') {
            // --- Caminho automático: Mercado Pago avisa quando for pago ---
            $notificationUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/webhook_mercadopago.php?estab={$estabId}";
            $resultadoMp = mp_criarPagamentoPix(
                $tokenMp,
                $valorFinal,
                "Pedido #{$pedidoId} - " . ($estabPix['nome'] ?? 'Loja'),
                (string)$pedidoId,
                $notificationUrl
            );

            if ($resultadoMp['sucesso']) {
                $stmtSalvaPix = $pdo->prepare("UPDATE pedidos SET pagamento_id = :pid, pix_copia_cola = :copia, pix_qr_base64 = :qr WHERE id = :id");
                $stmtSalvaPix->execute([
                    ':pid' => $resultadoMp['payment_id'],
                    ':copia' => $resultadoMp['qr_code'],
                    ':qr' => $resultadoMp['qr_code_base64'],
                    ':id' => $pedidoId
                ]);
                $respostaPix = [
                    'pix_automatico' => true,
                    'pix_copia_cola' => $resultadoMp['qr_code'],
                    'pix_qr_base64' => $resultadoMp['qr_code_base64'],
                ];
            }
        }

        // Se não tem Mercado Pago configurado, ou se a chamada acima falhou
        // por qualquer motivo, cai pro Pix estático — o pedido continua
        // podendo ser pago, só que a confirmação vira manual (a loja
        // confere no próprio banco e confirma no painel).
        if (empty($respostaPix)) {
            if (!empty($estabPix['chave_pix'])) {
                $payload = pix_montarPayload(
                    $estabPix['chave_pix'],
                    $estabPix['nome_pix'] ?? $estabPix['nome'] ?? 'LOJA',
                    $estabPix['cidade_pix'] ?? 'BRASIL',
                    $valorFinal,
                    'PED' . $pedidoId
                );
                $stmtSalvaPix = $pdo->prepare("UPDATE pedidos SET pix_copia_cola = :copia WHERE id = :id");
                $stmtSalvaPix->execute([':copia' => $payload, ':id' => $pedidoId]);
                $respostaPix = [
                    'pix_automatico' => false,
                    'pix_copia_cola' => $payload,
                    'pix_qr_base64' => null,
                ];
            } else {
                // Loja nem cadastrou uma chave Pix ainda — não travamos o
                // pedido, só avisamos o cliente pra combinar o pagamento
                // direto com a loja.
                $respostaPix = ['pix_automatico' => false, 'pix_copia_cola' => null, 'pix_qr_base64' => null];
            }
        }
    }

    echo json_encode(array_merge([
        'sucesso' => true,
        'pedido_id' => $pedidoId,
        'status_pagamento' => $statusPagamentoInicial,
        'mensagem' => 'Pedido registrado com sucesso!'
    ], $respostaPix));

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao criar pedido: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Não foi possível registrar o pedido. Tente novamente em instantes.'
    ]);
}