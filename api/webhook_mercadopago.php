<?php
/**
 * api/webhook_mercadopago.php
 *
 * O Mercado Pago chama esta URL sozinho, no instante em que o status de
 * um pagamento muda (ex: de "pending" para "approved"). É isso que
 * permite ao sistema saber automaticamente que um Pix foi pago, sem
 * ninguém da loja precisar confirmar nada na mão.
 *
 * IMPORTANTE: nunca confiamos no conteúdo que a notificação envia —
 * usamos só o ID do pagamento pra buscar o status DE VERDADE direto na
 * API do Mercado Pago (com o token do próprio estabelecimento). Isso
 * evita que alguém finja uma notificação falsa pra marcar um pedido
 * como pago sem ter pago nada.
 */
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/mercadopago.php';

header('Content-Type: application/json; charset=utf-8');

// O Mercado Pago manda o ID do pagamento tanto via querystring (data.id)
// quanto, em versões antigas do webhook, dentro do corpo da requisição.
$corpo = json_decode(file_get_contents('php://input'), true) ?? [];
$paymentId = $_GET['data_id'] ?? ($_GET['id'] ?? ($corpo['data']['id'] ?? null));
$topico = $_GET['type'] ?? ($_GET['topic'] ?? ($corpo['type'] ?? null));
$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 0;

// Responde 200 rapidamente pra qualquer notificação que não seja sobre
// pagamento — o Mercado Pago também manda eventos de outros tipos que
// não nos interessam aqui.
if ($topico !== 'payment' || empty($paymentId) || $estabId <= 0) {
    http_response_code(200);
    echo json_encode(['recebido' => true]);
    exit;
}

try {
    $stmtToken = $pdo->prepare("SELECT mercadopago_access_token FROM estabelecimentos WHERE id = :id");
    $stmtToken->execute([':id' => $estabId]);
    $tokenMp = $stmtToken->fetchColumn();

    if (empty($tokenMp)) {
        http_response_code(200); // Não é erro do MP, só não temos token pra conferir
        echo json_encode(['recebido' => true, 'aviso' => 'Token não configurado para este estabelecimento.']);
        exit;
    }

    // Busca o status DE VERDADE direto na API — nunca confiamos no que
    // a notificação em si diz, só usamos ela como um "toque de campainha".
    $consulta = mp_consultarPagamento($tokenMp, (string)$paymentId);

    if (!$consulta['sucesso']) {
        http_response_code(200);
        echo json_encode(['recebido' => true, 'aviso' => 'Não foi possível confirmar o pagamento agora.']);
        exit;
    }

    $statusFinal = $consulta['status'] === 'approved' ? 'pago'
        : (in_array($consulta['status'], ['cancelled', 'rejected'], true) ? 'recusado' : 'pendente');

    $stmtAtualiza = $pdo->prepare("
        UPDATE pedidos
        SET status_pagamento = :status
        WHERE pagamento_id = :pid AND estabelecimento_id = :estab
    ");
    $stmtAtualiza->execute([
        ':status' => $statusFinal,
        ':pid' => (string)$paymentId,
        ':estab' => $estabId
    ]);

    http_response_code(200);
    echo json_encode(['recebido' => true, 'status_aplicado' => $statusFinal]);
} catch (Exception $e) {
    error_log('webhook_mercadopago.php: ' . $e->getMessage());
    // Sempre responde 200: se devolvermos erro, o Mercado Pago fica
    // reenviando a mesma notificação repetidamente.
    http_response_code(200);
    echo json_encode(['recebido' => true, 'erro_interno' => true]);
}
