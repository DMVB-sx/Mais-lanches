<?php
/**
 * includes/mercadopago.php
 *
 * Integração com a API do Mercado Pago para gerar cobranças Pix DINÂMICAS
 * (vinculadas a um pagamento específico) e consultar o status delas.
 *
 * Diferença importante em relação a includes/pix.php:
 * - includes/pix.php gera um Pix "copia e cola" ESTÁTICO, usando a chave
 *   da loja direto. Ninguém — nem o Mercado Pago, nem ninguém — consegue
 *   avisar automaticamente quando ele é pago. É só uma transferência comum.
 * - Este arquivo gera a cobrança Pix ATRAVÉS do Mercado Pago. Como o
 *   pagamento passa pela conta do Mercado Pago do próprio estabelecimento,
 *   ele consegue nos avisar (via webhook) no instante em que for pago.
 *
 * Cada estabelecimento usa a PRÓPRIA conta/token do Mercado Pago
 * (estabelecimentos.mercadopago_access_token) — o dinheiro cai direto pra
 * conta do cliente, o sistema nunca fica no meio do caminho do dinheiro.
 */

const MP_API_BASE = 'https://api.mercadopago.com';

/**
 * Cria uma cobrança Pix dinâmica no Mercado Pago.
 *
 * @return array{sucesso:bool, payment_id?:string, qr_code?:string, qr_code_base64?:string, erro?:string}
 */
function mp_criarPagamentoPix(string $accessToken, float $valor, string $descricao, string $externalReference, string $notificationUrl): array {
    $corpo = [
        'transaction_amount' => round($valor, 2),
        'description' => $descricao,
        'payment_method_id' => 'pix',
        // O Mercado Pago exige um e-mail do pagador mesmo pra Pix.
        // Como o sistema só coleta WhatsApp do cliente, usamos um e-mail
        // genérico — isso não afeta a cobrança nem o recebimento.
        'payer' => ['email' => 'cliente@sememail.com.br'],
        'external_reference' => $externalReference,
        'notification_url' => $notificationUrl,
    ];

    $ch = curl_init(MP_API_BASE . '/v1/payments');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($corpo),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-Idempotency-Key: ' . $externalReference . '-' . bin2hex(random_bytes(4)),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resposta === false) {
        return ['sucesso' => false, 'erro' => 'Falha de conexão com o Mercado Pago: ' . $erroCurl];
    }

    $json = json_decode($resposta, true);

    if ($codigoHttp >= 200 && $codigoHttp < 300 && !empty($json['point_of_interaction']['transaction_data']['qr_code'])) {
        return [
            'sucesso' => true,
            'payment_id' => (string)$json['id'],
            'qr_code' => $json['point_of_interaction']['transaction_data']['qr_code'],
            'qr_code_base64' => $json['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
        ];
    }

    $mensagemErro = $json['message'] ?? "Erro desconhecido (HTTP {$codigoHttp})";
    error_log("mercadopago.php: erro ao criar pagamento Pix — {$mensagemErro}");
    return ['sucesso' => false, 'erro' => $mensagemErro];
}

/**
 * Consulta o status atual de um pagamento no Mercado Pago.
 * Usado tanto pelo webhook quanto por uma checagem manual/de segurança.
 *
 * @return array{sucesso:bool, status?:string, erro?:string}
 */
function mp_consultarPagamento(string $accessToken, string $paymentId): array {
    $ch = curl_init(MP_API_BASE . '/v1/payments/' . urlencode($paymentId));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $resposta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resposta === false) {
        return ['sucesso' => false, 'erro' => 'Falha de conexão com o Mercado Pago.'];
    }

    $json = json_decode($resposta, true);

    if ($codigoHttp >= 200 && $codigoHttp < 300 && isset($json['status'])) {
        // Status possíveis do Mercado Pago: pending, approved, authorized,
        // in_process, in_mediation, rejected, cancelled, refunded, charged_back
        return ['sucesso' => true, 'status' => $json['status']];
    }

    return ['sucesso' => false, 'erro' => $json['message'] ?? "Erro HTTP {$codigoHttp}"];
}
