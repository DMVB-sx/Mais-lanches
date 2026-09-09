<?php
/**
 * includes/tema.php
 *
 * Sistema de identidade visual por estabelecimento (multi-cliente / white-label).
 * Cada estabelecimento tem duas cores no banco (cor_primaria, cor_secundaria)
 * e uma logo (logo_url). A partir dessas duas cores, geramos automaticamente
 * uma "escala" de tons (claro → escuro) e sobrescrevemos a paleta do Tailwind
 * em tempo real, para que TODAS as classes já usadas no projeto (brand-600,
 * purple-600, fuchsia-600, etc.) passem a refletir a cor do cliente, sem
 * precisar trocar nome de classe em lugar nenhum do código.
 *
 * Se o estabelecimento não tiver cor configurada, cai automaticamente nas
 * cores padrão do sistema (o roxo/magenta usado hoje pela Drilavy).
 */

const TEMA_COR_PRIMARIA_PADRAO = '#9333ea';   // roxo (purple-600 / brand-600 originais)
const TEMA_COR_SECUNDARIA_PADRAO = '#c026d3'; // magenta (fuchsia-600 original)

/**
 * Converte hex (#rrggbb) em [r, g, b].
 */
function tema_hexParaRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = ltrim(TEMA_COR_PRIMARIA_PADRAO, '#');
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function tema_rgbParaHex(array $rgb): string {
    return '#' . implode('', array_map(fn($c) => str_pad(dechex(max(0, min(255, (int)round($c)))), 2, '0', STR_PAD_LEFT), $rgb));
}

/**
 * Mistura uma cor com branco (peso > 0 clareia) ou preto (peso < 0 escurece).
 */
function tema_misturar(array $rgb, float $peso, array $alvo): array {
    return [
        $rgb[0] + ($alvo[0] - $rgb[0]) * $peso,
        $rgb[1] + ($alvo[1] - $rgb[1]) * $peso,
        $rgb[2] + ($alvo[2] - $rgb[2]) * $peso,
    ];
}

/**
 * Gera a escala de tons (50 a 950) a partir de uma cor-base, assumindo que
 * a cor informada representa o tom 600 (o mais usado no projeto).
 */
function tema_gerarEscala(string $hexBase): array {
    $base = tema_hexParaRgb($hexBase);
    $branco = [255, 255, 255];
    $preto = [0, 0, 0];

    $pesosClaros = [50 => 0.95, 100 => 0.90, 200 => 0.78, 300 => 0.62, 400 => 0.40, 500 => 0.18];
    $pesosEscuros = [700 => 0.18, 800 => 0.32, 900 => 0.48, 950 => 0.62];

    $escala = [600 => tema_rgbParaHex($base)];
    foreach ($pesosClaros as $tom => $peso) {
        $escala[$tom] = tema_rgbParaHex(tema_misturar($base, $peso, $branco));
    }
    foreach ($pesosEscuros as $tom => $peso) {
        $escala[$tom] = tema_rgbParaHex(tema_misturar($base, $peso, $preto));
    }
    ksort($escala);
    return $escala;
}

/**
 * Monta e imprime o bloco <script> com o tailwind.config dinâmico, já
 * sobrescrevendo brand/purple/fuchsia com as cores do estabelecimento.
 * Chame isso no lugar do tailwind.config estático em cada página.
 */
function tema_imprimirTailwindConfig($estab = null, array $fontesSans = ['Inter', 'sans-serif']): void {
    $corPrimaria = !empty($estab['cor_primaria']) ? $estab['cor_primaria'] : TEMA_COR_PRIMARIA_PADRAO;
    $corSecundaria = !empty($estab['cor_secundaria']) ? $estab['cor_secundaria'] : TEMA_COR_SECUNDARIA_PADRAO;

    $escalaPrimaria = tema_gerarEscala($corPrimaria);
    $escalaSecundaria = tema_gerarEscala($corSecundaria);

    $coresJson = json_encode([
        'brand' => $escalaPrimaria,
        'purple' => $escalaPrimaria,
        'fuchsia' => $escalaSecundaria,
        'dark' => [
            'base' => '#0B0914',
            'surface' => '#141021',
            'card' => '#1C172E',
            'border' => 'rgba(255, 255, 255, 0.08)',
        ],
    ]);
    ?>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: <?= $coresJson ?>,
                    fontFamily: { sans: <?= json_encode($fontesSans) ?> }
                }
            }
        }
    </script>
    <?php
}

/**
 * Retorna a URL/caminho da logo do estabelecimento, ou null se não houver.
 * Aceita tanto um caminho local (ex: assets/uploads/logo123.png) quanto uma
 * URL completa (https://...).
 */
function tema_logoUrl($estab): ?string {
    if (empty($estab['logo_url'])) return null;
    $logo = $estab['logo_url'];
    if (preg_match('#^https?://#i', $logo)) return $logo;
    // Caminho local: confere se o arquivo existe antes de apontar pra ele
    $caminhoAbsoluto = realpath(__DIR__ . '/../' . ltrim($logo, '/'));
    return $caminhoAbsoluto ? $logo : null;
}

/**
 * Monta o atributo src="" pronto pra usar em <img>, cuidando do prefixo
 * relativo (ex: "../" quando chamado de dentro de admin/) e de URLs
 * completas (que não devem levar prefixo).
 */
function tema_logoSrc($estab, string $prefixoRelativo = ''): ?string {
    $logo = tema_logoUrl($estab);
    if (!$logo) return null;
    if (preg_match('#^https?://#i', $logo)) return $logo;
    return $prefixoRelativo . ltrim($logo, '/');
}
