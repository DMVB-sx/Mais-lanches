<?php
require_once __DIR__ . '/auth_api.php'; // apenas admin logado pode gerenciar produtos
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once __DIR__ . '/../config/conexao.php';

try {
    $metodo = $_SERVER['REQUEST_METHOD'];
    $estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

    if ($metodo === 'GET') {
        $acao = $_GET['acao'] ?? '';
        if ($acao === 'categorias') {
            $stmt = $pdo->prepare("SELECT id, nome FROM categorias WHERE estabelecimento_id = :estab ORDER BY ordem ASC");
            $stmt->execute([':estab' => $estabId]);
            echo json_encode(['sucesso' => true, 'categorias' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($acao === 'produtos') {
            $stmt = $pdo->prepare("SELECT * FROM produtos WHERE estabelecimento_id = :estab ORDER BY categoria_id ASC, nome ASC");
            $stmt->execute([':estab' => $estabId]);
            echo json_encode(['sucesso' => true, 'produtos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
    }

    if ($metodo === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $acao = $dados['acao'] ?? '';

        if ($acao === 'criar_produto') {
            $nome = trim($dados['nome'] ?? '');
            $categoriaId = (int)($dados['categoria_id'] ?? 0);
            $descricao = trim($dados['descricao'] ?? '');
            $preco = (float)($dados['preco'] ?? 0);

            if (empty($nome) || $categoriaId <= 0 || $preco <= 0) {
                echo json_encode(['sucesso' => false, 'erro' => 'Preencha nome, categoria e preço válido.']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO produtos (categoria_id, estabelecimento_id, nome, descricao, preco, disponivel)
                VALUES (:categoria_id, :estab, :nome, :descricao, :preco, 1)
            ");
            $stmt->execute([
                ':categoria_id' => $categoriaId,
                ':estab' => $estabId,
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':preco' => $preco
            ]);

            echo json_encode(['sucesso' => true, 'mensagem' => 'Item cadastrado com sucesso!']);
            exit;
        }

        if ($acao === 'toggle_status') {
            $tipo = $dados['tipo'] ?? 'produto';
            $id = (int)($dados['id'] ?? 0);
            $novoStatus = !empty($dados['disponivel']) ? 1 : 0;

            if ($tipo === 'pizza') {
                $stmt = $pdo->prepare("UPDATE pizza_sabores SET disponivel = :status WHERE id = :id AND estabelecimento_id = :estab");
            } else {
                $stmt = $pdo->prepare("UPDATE produtos SET disponivel = :status WHERE id = :id AND estabelecimento_id = :estab");
            }
            $stmt->execute([':status' => $novoStatus, ':id' => $id, ':estab' => $estabId]);

            echo json_encode(['sucesso' => true, 'mensagem' => 'Disponibilidade atualizada!']);
            exit;
        }

        if ($acao === 'salvar_preco') {
            $tipo = $dados['tipo'] ?? 'produto';
            $id = (int)($dados['id'] ?? 0);

            if ($tipo === 'pizza') {
                $precoM = (float)$dados['preco_m'];
                $precoG = (float)$dados['preco_g'];
                $precoF = (float)$dados['preco_f'];

                $stmt = $pdo->prepare("UPDATE pizza_sabores SET preco_m = :pm, preco_g = :pg, preco_f = :pf WHERE id = :id AND estabelecimento_id = :estab");
                $stmt->execute([':pm' => $precoM, ':pg' => $precoG, ':pf' => $precoF, ':id' => $id, ':estab' => $estabId]);
            } else {
                $preco = (float)$dados['preco'];
                $stmt = $pdo->prepare("UPDATE produtos SET preco = :preco WHERE id = :id AND estabelecimento_id = :estab");
                $stmt->execute([':preco' => $preco, ':id' => $id, ':estab' => $estabId]);
            }

            echo json_encode(['sucesso' => true, 'mensagem' => 'Preço atualizado com sucesso!']);
            exit;
        }

        if ($acao === 'editar_produto') {
            $id = (int)($dados['id'] ?? 0);
            $nome = trim($dados['nome'] ?? '');
            $categoriaId = (int)($dados['categoria_id'] ?? 0);
            $descricao = trim($dados['descricao'] ?? '');
            $preco = (float)($dados['preco'] ?? 0);

            if ($id <= 0 || empty($nome) || $categoriaId <= 0 || $preco <= 0) {
                echo json_encode(['sucesso' => false, 'erro' => 'Preencha nome, categoria e preço válido.']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE produtos
                SET nome = :nome, descricao = :descricao, categoria_id = :categoria_id, preco = :preco
                WHERE id = :id AND estabelecimento_id = :estab
            ");
            $stmt->execute([
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':categoria_id' => $categoriaId,
                ':preco' => $preco,
                ':id' => $id,
                ':estab' => $estabId
            ]);

            echo json_encode(['sucesso' => true, 'mensagem' => 'Produto atualizado com sucesso!']);
            exit;
        }

        if ($acao === 'excluir_produto') {
            $id = (int)($dados['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['sucesso' => false, 'erro' => 'Produto inválido.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = :id AND estabelecimento_id = :estab");
            $stmt->execute([':id' => $id, ':estab' => $estabId]);

            echo json_encode(['sucesso' => true, 'mensagem' => 'Produto removido com sucesso!']);
            exit;
        }
    }

    echo json_encode(['sucesso' => false, 'erro' => 'Ação não reconhecida.']);

} catch (Exception $e) {
    error_log('gerenciar_produto.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar a solicitação.']);
}