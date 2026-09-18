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

// Isolamento entre clientes (multi-tenant): um admin só pode ver/editar o
// próprio estabelecimento, mesmo que altere o "?estab=" na URL/requisição.
if (isset($_SESSION['admin_estab']) && isset($_GET['estab'])) {
    if ((int)$_GET['estab'] !== (int)$_SESSION['admin_estab']) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'erro' => 'Você não tem permissão para acessar este estabelecimento.']);
        exit;
    }
}
