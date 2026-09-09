<?php
// api/atualizar_estabelecimento.php
require_once __DIR__ . '/auth_api.php'; // só admin logado
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexao.php';

$dados = json_decode(file_get_contents('php://input'), true);
$estabId = isset($dados['estab_id']) ? (int)$dados['estab_id'] : 0;

if ($estabId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Estabelecimento inválido.']);
    exit;
}

// Segurança extra: só deixa editar o próprio estabelecimento do admin logado
if (isset($_SESSION['admin_estab']) && (int)$_SESSION['admin_estab'] !== $estabId) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Você não tem permissão para editar este estabelecimento.']);
    exit;
}

function validarHex($cor) {
    return is_string($cor) && preg_match('/^#[0-9a-fA-F]{6}$/', $cor);
}

$corPrimaria = $dados['cor_primaria'] ?? '';
$corSecundaria = $dados['cor_secundaria'] ?? '';
$logoUrl = trim($dados['logo_url'] ?? '');

if (!validarHex($corPrimaria) || !validarHex($corSecundaria)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Cor inválida. Use o formato #RRGGBB.']);
    exit;
}

if ($logoUrl !== '' && !preg_match('#^https?://#i', $logoUrl) && strpos($logoUrl, '..') !== false) {
    echo json_encode(['sucesso' => false, 'erro' => 'Caminho de logo inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE estabelecimentos
        SET cor_primaria = :cp, cor_secundaria = :cs, logo_url = :logo
        WHERE id = :id
    ");
    $stmt->execute([
        ':cp' => $corPrimaria,
        ':cs' => $corSecundaria,
        ':logo' => $logoUrl !== '' ? $logoUrl : null,
        ':id' => $estabId
    ]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Identidade visual atualizada com sucesso!']);
} catch (Exception $e) {
    error_log('atualizar_estabelecimento.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar as configurações.']);
}
