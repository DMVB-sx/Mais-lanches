<?php
// api/auth_api.php
// Garante que apenas o admin logado (mesma sessão do painel) possa
// chamar endpoints de escrita/gestão da API.
// Inclua este arquivo no topo de qualquer endpoint que crie, altere
// ou apague dados (pedidos, produtos, status da loja, etc.).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado. Faça login no painel administrativo.']);
    exit;
}
