<?php
// api/status_loja.php
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
        $stmt = $pdo->prepare("SELECT status FROM estabelecimentos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $estabId]);
        $estab = $stmt->fetch(PDO::FETCH_ASSOC);

        $statusValor = 0;
        if ($estab) {
            $st = strtolower(trim((string)$estab['status']));
            $statusValor = ($st === '1' || $st === 'aberto') ? 1 : 0;
        }

        echo json_encode([
            'sucesso' => true,
            'status' => $statusValor,
            'aberto' => ($statusValor === 1)
        ]);
        exit;
    }

    if ($metodo === 'POST') {
        // Somente o admin logado pode abrir/fechar a loja.
        require_once __DIR__ . '/auth_api.php';

        $dados = json_decode(file_get_contents('php://input'), true);
        $novoStatus = isset($dados['status']) ? (int)$dados['status'] : 1;

        $stmt = $pdo->prepare("UPDATE estabelecimentos SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $novoStatus,
            ':id' => $estabId
        ]);

        echo json_encode([
            'sucesso' => true,
            'status' => $novoStatus,
            'aberto' => ($novoStatus === 1)
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('status_loja.php: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao atualizar status da loja.']);
}