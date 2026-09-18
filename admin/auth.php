<?php
// admin/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: login.php");
    exit;
}

// Isolamento entre clientes (multi-tenant): um admin só pode ver/editar o
// próprio estabelecimento, mesmo que altere o "?estab=" na URL manualmente.
if (isset($_SESSION['admin_estab']) && isset($_GET['estab'])) {
    if ((int)$_GET['estab'] !== (int)$_SESSION['admin_estab']) {
        http_response_code(403);
        die('<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0B0914; color:#fff; font-family:sans-serif; text-align:center; padding:20px;"><div><h2>Acesso negado</h2><p style="color:#94a3b8; margin-top:8px;">Você não tem permissão para acessar este estabelecimento.</p></div></div>');
    }
}