<?php
// api/gerenciar_produto.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;
$acao = $_GET['acao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $acao = $dados['acao'] ?? $acao;
}

try {
    // 1. Listar categorias
    if ($acao === 'categorias') {
        $stmt = $pdo->prepare("SELECT id, nome FROM categorias WHERE estabelecimento_id = :estab ORDER BY ordem ASC, id ASC");
        $stmt->execute([':estab' => $estabId]);
        echo json_encode(['sucesso' => true, 'categorias' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. Listar produtos
    if ($acao === 'produtos') {
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE estabelecimento_id = :estab ORDER BY categoria_id ASC, nome ASC");
        $stmt->execute([':estab' => $estabId]);
        echo json_encode(['sucesso' => true, 'produtos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 3. Alternar Disponibilidade (liga/desliga na tela)
    if ($acao === 'toggle_status') {
        $id = (int)($dados['id'] ?? 0);
        $disponivel = !empty($dados['disponivel']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE produtos SET disponivel = :disp WHERE id = :id AND estabelecimento_id = :estab");
        $stmt->execute([':disp' => $disponivel, ':id' => $id, ':estab' => $estabId]);

        echo json_encode(['sucesso' => true]);
        exit;
    }

    // 4. Alternar Especial da Semana (Estrelinha ⭐)
    if ($acao === 'toggle_destaque') {
        $id = (int)($dados['id'] ?? 0);
        $destaque = !empty($dados['destaque']) ? 1 : 0;

        // Limpa destaque dos outros itens
        $pdo->prepare("UPDATE produtos SET destaque_dia = 0 WHERE estabelecimento_id = :estab")->execute([':estab' => $estabId]);

        if ($destaque === 1) {
            $stmt = $pdo->prepare("UPDATE produtos SET destaque_dia = 1 WHERE id = :id AND estabelecimento_id = :estab");
            $stmt->execute([':id' => $id, ':estab' => $estabId]);
        }

        echo json_encode(['sucesso' => true]);
        exit;
    }

    // 5. Salvar Preço Rápido Inline
    if ($acao === 'salvar_preco') {
        $id = (int)($dados['id'] ?? 0);
        $preco = (float)($dados['preco'] ?? 0);

        $stmt = $pdo->prepare("UPDATE produtos SET preco = :preco WHERE id = :id AND estabelecimento_id = :estab");
        $stmt->execute([':preco' => $preco, ':id' => $id, ':estab' => $estabId]);

        echo json_encode(['sucesso' => true]);
        exit;
    }

    // 6. Criar Produto
    if ($acao === 'criar_produto') {
        $destaque = !empty($dados['destaque_dia']) ? 1 : 0;
        if ($destaque === 1) {
            $pdo->prepare("UPDATE produtos SET destaque_dia = 0 WHERE estabelecimento_id = :estab")->execute([':estab' => $estabId]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO produtos (estabelecimento_id, categoria_id, nome, descricao, preco, destaque_dia, disponivel)
            VALUES (:estab, :cat, :nome, :desc, :preco, :destaque, 1)
        ");
        $stmt->execute([
            ':estab' => $estabId,
            ':cat' => (int)$dados['categoria_id'],
            ':nome' => trim($dados['nome']),
            ':desc' => trim($dados['descricao'] ?? ''),
            ':preco' => (float)$dados['preco'],
            ':destaque' => $destaque
        ]);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Item criado com sucesso!']);
        exit;
    }

    // 7. Editar Produto
    if ($acao === 'editar_produto') {
        $id = (int)$dados['id'];
        $destaque = !empty($dados['destaque_dia']) ? 1 : 0;
        if ($destaque === 1) {
            $pdo->prepare("UPDATE produtos SET destaque_dia = 0 WHERE estabelecimento_id = :estab")->execute([':estab' => $estabId]);
        }

        $stmt = $pdo->prepare("
            UPDATE produtos 
            SET categoria_id = :cat, nome = :nome, descricao = :desc, preco = :preco, destaque_dia = :destaque
            WHERE id = :id AND estabelecimento_id = :estab
        ");
        $stmt->execute([
            ':cat' => (int)$dados['categoria_id'],
            ':nome' => trim($dados['nome']),
            ':desc' => trim($dados['descricao'] ?? ''),
            ':preco' => (float)$dados['preco'],
            ':destaque' => $destaque,
            ':id' => $id,
            ':estab' => $estabId
        ]);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Item atualizado com sucesso!']);
        exit;
    }

    // 8. Excluir Produto
    if ($acao === 'excluir_produto') {
        $id = (int)($dados['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = :id AND estabelecimento_id = :estab");
        $stmt->execute([':id' => $id, ':estab' => $estabId]);

        echo json_encode(['sucesso' => true]);
        exit;
    }

    echo json_encode(['sucesso' => false, 'erro' => 'Ação inválida.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}