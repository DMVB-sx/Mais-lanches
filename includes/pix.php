<?php
/**
 * includes/pix.php
 *
 * Gera o "Pix Copia e Cola" (payload EMV/BR Code) a partir da chave Pix
 * do estabelecimento — usado como RESERVA quando a loja ainda não
 * configurou o Mercado Pago (veja includes/mercadopago.php para o
 * caminho automático).
 *
 * Isso gera uma cobrança Pix estática com valor fixo — o cliente paga
 * direto na chave da loja. Como é uma transferência comum, NINGUÉM
 * consegue confirmar automaticamente que foi paga — nem este sistema,
 * nem o Mercado Pago, nem ninguém. A confirmação, nesse caminho, é
 * sempre manual (a loja confere no próprio banco).
 */

function pix_sanitizar(string $texto, int $tamanhoMax): string {
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
    $texto = preg_replace('/[^A-Za-z0-9 ]/', '', $texto);
    $texto = trim($texto);
    $texto = strtoupper($texto);
    return substr($texto, 0, $tamanhoMax) ?: 'LOJA';
}

function pix_tlv(string $id, string $valor): string {
    return $id . str_pad((string)strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
}

function pix_crc16(string $payload): string {
    $polinomio = 0x1021;
    $resultado = 0xFFFF;

    for ($i = 0; $i < strlen($payload); $i++) {
        $resultado ^= (ord($payload[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            if (($resultado & 0x8000) !== 0) {
                $resultado = (($resultado << 1) ^ $polinomio) & 0xFFFF;
            } else {
                $resultado = ($resultado << 1) & 0xFFFF;
            }
        }
    }

    return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
}

function pix_montarPayload(string $chave, string $nome, string $cidade, float $valor, string $txid = ''): string {
    $chave = trim($chave);
    $nome = pix_sanitizar($nome ?: 'LOJA', 25);
    $cidade = pix_sanitizar($cidade ?: 'BRASIL', 15);
    $txid = $txid !== '' ? pix_sanitizar($txid, 25) : '***';
    $valorFormatado = number_format(max(0, $valor), 2, '.', '');

    $merchantAccountInfo = pix_tlv('00', 'br.gov.bcb.pix') . pix_tlv('01', $chave);

    $payload =
        pix_tlv('00', '01') .
        pix_tlv('01', '12') .
        pix_tlv('26', $merchantAccountInfo) .
        pix_tlv('52', '0000') .
        pix_tlv('53', '986') .
        pix_tlv('54', $valorFormatado) .
        pix_tlv('58', 'BR') .
        pix_tlv('59', $nome) .
        pix_tlv('60', $cidade) .
        pix_tlv('62', pix_tlv('05', $txid));

    $payload .= '6304';
    return $payload . pix_crc16($payload);
}
