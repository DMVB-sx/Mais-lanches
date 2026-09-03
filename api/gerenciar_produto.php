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
            $tabela = ($tipo === 'pizza') ? 'pizza_sabores' : 'produtos';

            $stmt = $pdo->prepare("UPDATE {$tabela} SET disponivel = :status WHERE id = :id");
            $stmt->execute([':status' => $novoStatus, ':id' => $id]);

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

                $stmt = $pdo->prepare("UPDATE pizza_sabores SET preco_m = :pm, preco_g = :pg, preco_f = :pf WHERE id = :id");
                $stmt->execute([':pm' => $precoM, ':pg' => $precoG, ':pf' => $precoF, ':id' => $id]);
            } else {
                $preco = (float)$dados['preco'];
                $stmt = $pdo->prepare("UPDATE produtos SET preco = :preco WHERE id = :id");
                $stmt->execute([':preco' => $preco, ':id' => $id]);
            }

            echo json_encode(['sucesso' => true, 'mensagem' => 'Preço atualizado com sucesso!']);
            exit;
        }
    }

    echo json_encode(['sucesso' => false, 'erro' => 'Ação não reconhecida.']);

} catch (Exception $e) {
    error_log('gerenciar_produto.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar a solicitação.']);
}