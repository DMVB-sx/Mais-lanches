<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

try {
    // Busca o estabelecimento com o status real do banco
    $stmtEstab = $pdo->prepare("SELECT id, nome, status, taxa_entrega_padrao, cor_primaria, cor_secundaria FROM estabelecimentos WHERE id = :id");
    $stmtEstab->execute([':id' => $estabId]);
    $estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);

    if (!$estab) {
        echo json_encode(['sucesso' => false, 'erro' => 'Estabelecimento não encontrado.']);
        exit;
    }

    // Busca categorias
    $stmtCat = $pdo->prepare("SELECT id, nome, ordem FROM categorias WHERE estabelecimento_id = :estab ORDER BY ordem ASC");
    $stmtCat->execute([':estab' => $estabId]);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    // Busca produtos disponíveis
    $stmtProd = $pdo->prepare("SELECT * FROM produtos WHERE estabelecimento_id = :estab AND disponivel = 1 ORDER BY id ASC");
    $stmtProd->execute([':estab' => $estabId]);
    $produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    // Busca adicionais
    $stmtGrupos = $pdo->prepare("SELECT * FROM grupos_adicionais WHERE estabelecimento_id = :estab");
    $stmtGrupos->execute([':estab' => $estabId]);
    $grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

    $stmtAdic = $pdo->prepare("SELECT * FROM adicionais WHERE disponivel = 1");
    $stmtAdic->execute();
    $adicionais = $stmtAdic->fetchAll(PDO::FETCH_ASSOC);

    foreach ($grupos as &$grp) {
        $grp['itens'] = array_values(array_filter($adicionais, fn($a) => $a['grupo_id'] == $grp['id']));
    }

    // Vincula grupos aos produtos
    $stmtVinc = $pdo->query("SELECT * FROM produto_grupos_adicionais");
    $vinculos = $stmtVinc->fetchAll(PDO::FETCH_ASSOC);

    foreach ($produtos as &$prod) {
        $gruposDoProdIds = array_column(array_filter($vinculos, fn($v) => $v['produto_id'] == $prod['id']), 'grupo_id');
        $prod['grupos_adicionais'] = array_values(array_filter($grupos, fn($g) => in_array($g['id'], $gruposDoProdIds)));
    }

    // Agrupa por categoria
    $cardapio = [];
    foreach ($categorias as $cat) {
        $prodsDaCat = array_values(array_filter($produtos, fn($p) => $p['categoria_id'] == $cat['id']));
        if (count($prodsDaCat) > 0) {
            $cardapio[] = [
                'categoria_id' => $cat['id'],
                'categoria_nome' => $cat['nome'],
                'produtos' => $prodsDaCat
            ];
        }
    }

    // Busca sabores de pizza
    $stmtPizza = $pdo->prepare("SELECT * FROM pizza_sabores WHERE estabelecimento_id = :estab AND disponivel = 1 ORDER BY categoria_sabor, nome ASC");
    $stmtPizza->execute([':estab' => $estabId]);
    $pizzaSabores = $stmtPizza->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sucesso' => true,
        'estabelecimento' => $estab,
        'cardapio' => $cardapio,
        'pizza_sabores' => $pizzaSabores
    ]);

} catch (Exception $e) {
    error_log('produtos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao carregar o cardápio. Tente novamente.']);
}