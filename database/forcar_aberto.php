<?php
// database/forcar_aberto.php
// Ferramenta de manutenção — só o admin logado pode rodar isso, e é
// recomendável remover este arquivo do servidor de produção depois de usado.
require_once __DIR__ . '/../api/auth_api.php';
require_once __DIR__ . '/../config/conexao.php';

try {
    // 1. Garante que as colunas 'aberto' e 'status' existam
    try {
        $pdo->exec("ALTER TABLE estabelecimentos ADD COLUMN aberto TINYINT(1) NOT NULL DEFAULT 1");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE estabelecimentos ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'aberto'");
    } catch (Exception $e) {}

    // 2. Força o estabelecimento 1 a ficar ABERTO em ambas as colunas
    $stmt = $pdo->prepare("UPDATE estabelecimentos SET aberto = 1, status = 'aberto' WHERE id = 1");
    $stmt->execute();

    // 3. Consulta de conferência
    $stmtVerifica = $pdo->query("SELECT id, nome, aberto, status FROM estabelecimentos WHERE id = 1");
    $estab = $stmtVerifica->fetch(PDO::FETCH_ASSOC);

    echo "<div style='font-family: sans-serif; max-width: 500px; margin: 40px auto; padding: 20px; background: #111; color: #fff; border-radius: 12px; border: 1px solid #333;'>";
    echo "<h2 style='color: #4ade80; margin-top: 0;'>✔ Loja Aberta no Banco com Sucesso!</h2>";
    echo "<pre style='background: #222; padding: 10px; border-radius: 8px; color: #a855f7;'>" . print_r($estab, true) . "</pre>";
    echo "<p><a href='../cardapio.php?estab=1' style='display: inline-block; padding: 10px 18px; background: #9333ea; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold;'>👉 Abrir Cardápio</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
}