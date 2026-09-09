<?php
// api/pedidos.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        // Busca todos os pedidos ativos (exceto os arquivados/limpos)
        $stmt = $pdo->prepare("
            SELECT * FROM pedidos 
            WHERE estabelecimento_id = :estab 
            ORDER BY id DESC
        ");
        $stmt->execute([':estab' => $estabId]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pedidos as &$p) {
            $stmtItens = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id = :id");
            $stmtItens->execute([':id' => $p['id']]);
            $p['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['sucesso' => true, 'pedidos' => $pedidos]);
        exit;
    }

    if ($metodo === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $pedidoId = isset($dados['pedido_id']) ? (int)$dados['pedido_id'] : 0;
        $novoStatus = trim($dados['status'] ?? 'novo');

        $stmt = $pdo->prepare("UPDATE pedidos SET status = :status WHERE id = :id AND estabelecimento_id = :estab");
        $stmt->execute([':status' => $novoStatus, ':id' => $pedidoId, ':estab' => $estabId]);

        echo json_encode(['sucesso' => true]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}