<?php
// database/limpar_pedidos.php
// ATENÇÃO: apaga TODOS os pedidos. Ferramenta de manutenção — só o admin
// logado pode rodar isso, e mesmo assim é recomendável remover este
// arquivo do servidor de produção depois de usado.
require_once __DIR__ . '/../api/auth_api.php';
require_once __DIR__ . '/../config/conexao.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE pedido_itens;");
    $pdo->exec("TRUNCATE TABLE pedidos;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<h2 style='color: green; font-family: sans-serif;'>✔ Todos os pedidos de teste foram apagados e os contadores zerados!</h2>";
    echo "<p><a href='../admin/index.php?estab=1' style='font-family: sans-serif; font-weight: bold;'>👉 Voltar ao Painel Admin</a></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}