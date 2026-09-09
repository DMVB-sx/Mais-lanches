<?php
// api/bot_proxy.php
// Faz a ponte entre o navegador (painel admin) e o microsserviço Node.js
// do WhatsApp. Isso resolve dois problemas:
// 1. Em produção, o navegador do usuário não consegue acessar
//    "localhost:3000" do servidor — só o próprio servidor consegue.
// 2. Protege o microsserviço com a chave compartilhada (BOT_SECRET),
//    sem expor essa chave no código do navegador.
require_once __DIR__ . '/auth_api.php'; // só admin logado usa isso

// Ajuste aqui se o bot rodar em outra máquina/porta.
$BOT_URL = 'http://127.0.0.1:3000';
// Precisa ser IDÊNTICA à variável de ambiente BOT_SECRET do whatsapp-bot/server.js
$BOT_SECRET = getenv('BOT_SECRET') ?: 'troque-esta-chave-antes-de-publicar';

$rotasPermitidas = ['status', 'contatos-agenda', 'disparar-transmissao', 'enviar-mensagem'];
$rota = $_GET['rota'] ?? '';

if (!in_array($rota, $rotasPermitidas, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['sucesso' => false, 'erro' => 'Rota inválida.']);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$ch = curl_init("{$BOT_URL}/{$rota}");

$headers = ["x-bot-secret: {$BOT_SECRET}"];

if ($metodo === 'POST') {
    $corpo = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
    $headers[] = 'Content-Type: application/json';
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$resposta = curl_exec($ch);
$erroCurl = curl_error($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');

if ($resposta === false) {
    http_response_code(503);
    echo json_encode(['sucesso' => false, 'erro' => 'Servidor do WhatsApp não está respondendo. Verifique se o "npm start" está rodando.']);
    exit;
}

http_response_code($statusCode ?: 200);
echo $resposta;
