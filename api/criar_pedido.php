<?php
// api/criar_pedido.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexao.php';

$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados do pedido não recebidos.']);
    exit;
}

$estabId = (int)($dados['estabelecimento_id'] ?? 1);
$cliente = $dados['cliente'] ?? [];
$tipoEntrega = $dados['tipo_entrega'] ?? 'delivery';
$taxaEntrega = (float)($dados['taxa_entrega'] ?? 0);
$subtotal = (float)($dados['subtotal'] ?? 0);
$total = (float)($dados['total'] ?? ($subtotal + $taxaEntrega));
$formaPagamento = trim($dados['forma_pagamento'] ?? 'dinheiro');
$trocoPara = !empty($dados['troco_para']) ? (float)$dados['troco_para'] : null;
$observacoes = trim($dados['observacoes'] ?? '');
$itens = $dados['itens'] ?? [];

if (empty($cliente['nome']) || empty($cliente['whatsapp'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe o seu nome e WhatsApp.']);
    exit;
}

if (empty($itens)) {
    echo json_encode(['sucesso' => false, 'erro' => 'A sua sacola está vazia.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Define status inicial: se for PIX aguarda verificação; caso contrário, segue como novo
    $statusInicial = ($formaPagamento === 'pix') ? 'novo' : 'novo';
    $pixCopiaCola = null;
    $pixQrBase64 = null;
    $mpPaymentId = null;

    // Tentativa opcional de geração de PIX via Mercado Pago
    if ($formaPagamento === 'pix' && file_exists(__DIR__ . '/../includes/mercadopago.php')) {
        try {
            require_once __DIR__ . '/../includes/mercadopago.php';
            if (function_exists('gerarPixMercadoPago')) {
                $resPix = gerarPixMercadoPago($estabId, $total, $cliente['nome']);
                if (!empty($resPix['sucesso'])) {
                    $pixCopiaCola = $resPix['copia_cola'] ?? null;
                    $pixQrBase64 = $resPix['qr_base64'] ?? null;
                    $mpPaymentId = $resPix['payment_id'] ?? null;
                }
            }
        } catch (Throwable $t) {
            // Continua o registo mesmo que a API externa falhe temporariamente
        }
    }

    $stmtPedido = $pdo->prepare("
        INSERT INTO pedidos (
            estabelecimento_id, cliente_nome, cliente_whatsapp, 
            cliente_endereco, cliente_bairro, cliente_complemento, 
            tipo_entrega, taxa_entrega, subtotal, total, 
            forma_pagamento, troco_para, observacoes, status,
            pix_copia_cola, pix_qr_base64, mercadopago_payment_id, criado_em
        ) VALUES (
            :estab, :nome, :wpp, 
            :endereco, :bairro, :compl, 
            :tipo, :taxa, :subtotal, :total, 
            :forma, :troco, :obs, :status,
            :copia_cola, :qr_base64, :mp_id, NOW()
        )
    ");

    $stmtPedido->execute([
        ':estab' => $estabId,
        ':nome' => $cliente['nome'],
        ':wpp' => $cliente['whatsapp'],
        ':endereco' => $cliente['endereco'] ?? '',
        ':bairro' => $cliente['bairro'] ?? '',
        ':compl' => $cliente['complemento'] ?? '',
        ':tipo' => $tipoEntrega,
        ':taxa' => $taxaEntrega,
        ':subtotal' => $subtotal,
        ':total' => $total,
        ':forma' => $formaPagamento,
        ':troco' => $trocoPara,
        ':obs' => $observacoes,
        ':status' => $statusInicial,
        ':copia_cola' => $pixCopiaCola,
        ':qr_base64' => $pixQrBase64,
        ':mp_id' => $mpPaymentId
    ]);

    $pedidoId = (int)$pdo->lastInsertId();

    // Gravação de itens do pedido
    $stmtItem = $pdo->prepare("
        INSERT INTO pedido_itens (
            pedido_id, produto_id, 
            quantidade, preco_unitario, subtotal, observacao
        ) VALUES (
            :ped_id, :prod_id, 
            :qtd, :preco, :subtotal, :obs
        )
    ");

    foreach ($itens as $item) {
        $qtd = max(1, (int)($item['qtd'] ?? 1));
        $precoUnit = (float)($item['preco_total_unitario'] ?? $item['preco_base'] ?? 0);
        $subItem = $qtd * $precoUnit;
        
        $obsItem = [];
        if (!empty($item['adicionais_texto'])) $obsItem[] = $item['adicionais_texto'];
        if (!empty($item['obs_item'])) $obsItem[] = $item['obs_item'];

        $stmtItem->execute([
            ':ped_id' => $pedidoId,
            ':prod_id' => (int)($item['id'] ?? 0),
            ':qtd' => $qtd,
            ':preco' => $precoUnit,
            ':subtotal' => $subItem,
            ':obs' => implode(' | ', $obsItem)
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'pedido_id' => $pedidoId,
        'pix_copia_cola' => $pixCopiaCola,
        'pix_qr_base64' => $pixQrBase64
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro na gravação: ' . $e->getMessage()]);
}