<?php
// api/pedidos.php
require_once __DIR__ . '/auth_api.php'; // apenas admin logado pode ver/alterar pedidos
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
      AND status NOT IN ('arquivado', 'aguardando_pagamento', 'cancelado')
    ORDER BY id DESC
");
$stmt->execute([':estab' => $estabId]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pedidos as &$p) {
            $stmtItens = $pdo->prepare("
                SELECT i.*, COALESCE(pr.nome, 'Item Personalizado') as produto_nome
                FROM pedido_itens i
                LEFT JOIN produtos pr ON i.produto_id = pr.id
                WHERE i.pedido_id = :id
            ");
            $stmtItens->execute([':id' => $p['id']]);
            $p['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['sucesso' => true, 'pedidos' => $pedidos]);
        exit;
    }

    if ($metodo === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $pedidoId = isset($dados['pedido_id']) ? (int)$dados['pedido_id'] : 0;

        if (($dados['acao'] ?? '') === 'confirmar_pagamento_pix') {
            // Confirmação manual: usada quando a loja não tem o Mercado
            // Pago conectado (ou como conferência extra), depois de checar
            // no próprio aplicativo do banco que o Pix realmente caiu.
            $stmt = $pdo->prepare("UPDATE pedidos SET status_pagamento = 'pago' WHERE id = :id AND estabelecimento_id = :estab");
            $stmt->execute([':id' => $pedidoId, ':estab' => $estabId]);
            echo json_encode(['sucesso' => true]);
            exit;
        }

        $novoStatus = trim($dados['status'] ?? 'novo');

        $stmt = $pdo->prepare("UPDATE pedidos SET status = :status WHERE id = :id AND estabelecimento_id = :estab");
        $stmt->execute([':status' => $novoStatus, ':id' => $pedidoId, ':estab' => $estabId]);

        echo json_encode(['sucesso' => true]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log('pedidos.php: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar pedidos.']);
}