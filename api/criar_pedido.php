<?php
// api/criar_pedido.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../config/conexao.php';

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

    $stmtPed = $pdo->prepare("
        INSERT INTO pedidos (
            estabelecimento_id, cliente_nome, cliente_whatsapp, 
            cliente_endereco, cliente_bairro, cliente_complemento, 
            tipo_entrega, taxa_entrega, subtotal, total, 
            forma_pagamento, troco_para, observacoes, status, criado_em
        ) VALUES (
            :estab, :nome, :wpp, 
            :endereco, :bairro, :compl, 
            :tipo, :taxa, :subtotal, :total, 
            :forma, :troco, :obs, 'novo', NOW()
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
        ':obs' => $observacoes
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

    echo json_encode([
        'sucesso' => true,
        'pedido_id' => $pedidoId,
        'mensagem' => 'Pedido registrado com sucesso!'
    ]);

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