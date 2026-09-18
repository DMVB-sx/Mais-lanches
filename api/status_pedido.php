<?php
// api/status_pedido.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT id, status, status_pagamento, forma_pagamento, pix_copia_cola, pix_qr_base64 FROM pedidos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $pedido = $stmt->fetch();

    if ($pedido) {
        echo json_encode([
            'sucesso' => true,
            'status' => $pedido['status'],
            'status_pagamento' => $pedido['status_pagamento'],
            'forma_pagamento' => $pedido['forma_pagamento'],
            'pix_copia_cola' => $pedido['pix_copia_cola'],
            'pix_qr_base64' => $pedido['pix_qr_base64'],
        ]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado']);
    }
} catch (Exception $e) {
    error_log('status_pedido.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao consultar status do pedido.']);
}