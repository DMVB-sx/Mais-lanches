<?php
// api/clientes.php
require_once __DIR__ . '/auth_api.php'; // dados de clientes (telefone, endereço) só para o admin
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/conexao.php';

$estab_id = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, whatsapp, bairro, total_pedidos, ultimo_pedido 
        FROM clientes 
        WHERE estabelecimento_id = :id 
        ORDER BY total_pedidos DESC, ultimo_pedido DESC
    ");
    $stmt->execute([':id' => $estab_id]);
    $clientes = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'total' => count($clientes),
        'clientes' => $clientes
    ], JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    error_log('clientes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao listar clientes.']);
}