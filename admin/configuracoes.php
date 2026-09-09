<?php
// admin/configuracoes.php — Identidade visual do estabelecimento (cores e logo)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

$stmtEstab = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id LIMIT 1");
$stmtEstab->execute([':id' => $estabId]);
$estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
$nomeEstab = $estab['nome'] ?? 'Drilavy Lanchonete e Pizzaria';

require_once __DIR__ . '/../includes/tema.php';
$corPrimariaAtual = !empty($estab['cor_primaria']) ? $estab['cor_primaria'] : TEMA_COR_PRIMARIA_PADRAO;
$corSecundariaAtual = !empty($estab['cor_secundaria']) ? $estab['cor_secundaria'] : TEMA_COR_SECUNDARIA_PADRAO;
$logoAtual = $estab['logo_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Visual da Loja - <?= htmlspecialchars($nomeEstab) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php tema_imprimirTailwindConfig($estab); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        input[type="color"] { -webkit-appearance: none; appearance: none; border: none; padding: 0; background: none; cursor: pointer; }
        input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        input[type="color"]::-webkit-color-swatch { border: 2px solid rgba(255,255,255,0.15); border-radius: 12px; }
    </style>
</head>
<body class="min-h-screen bg-dark-base text-slate-100 flex flex-col antialiased">

    <header class="sticky top-0 z-40 bg-dark-surface/90 backdrop-blur-md border-b border-dark-border px-4 sm:px-6 py-3">
        <div class="max-w-2xl mx-auto flex items-center gap-3">
            <a href="index.php?estab=<?= $estabId ?>" class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-dark-border flex items-center justify-center transition shrink-0">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
            <h1 class="text-sm font-extrabold text-white tracking-tight">Visual da Loja</h1>
        </div>
    </header>

    <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 space-y-5">

        <p class="text-xs text-slate-400 -mt-1">
            Escolha a cor principal e a cor secundária da sua marca. O sistema gera automaticamente todos os tons (claros e escuros) usados nas telas a partir dessas duas cores.
        </p>

        <!-- Pré-visualização ao vivo -->
        <div class="bg-dark-surface border border-dark-border rounded-2xl p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pré-visualização</span>
            <div class="mt-3 flex items-center gap-3">
                <div id="preview-logo-box" class="w-16 h-16 rounded-full p-0.5 shadow-lg flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, <?= htmlspecialchars($corPrimariaAtual) ?>, <?= htmlspecialchars($corSecundariaAtual) ?>);">
                    <div class="w-full h-full bg-[#0B0914] rounded-full flex items-center justify-center overflow-hidden">
                        <img id="preview-logo-img" src="<?= htmlspecialchars(tema_logoSrc($estab, '../') ?? '../assets/img/logo.png') ?>" alt="Logo" class="w-full h-full object-cover" onerror="this.style.display='none'">
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-extrabold text-white"><?= htmlspecialchars($nomeEstab) ?></p>
                    <button id="preview-botao" type="button" class="mt-2 px-4 py-2 rounded-xl text-xs font-extrabold text-white transition" style="background: linear-gradient(90deg, <?= htmlspecialchars($corPrimariaAtual) ?>, <?= htmlspecialchars($corSecundariaAtual) ?>);">
                        Botão de exemplo
                    </button>
                </div>
            </div>
        </div>

        <!-- Cores -->
        <div class="bg-dark-surface border border-dark-border rounded-2xl p-5 space-y-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <label class="block text-xs font-bold text-white">Cor primária</label>
                    <p class="text-[11px] text-slate-500">Usada em botões, destaques e cabeçalhos</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <input type="color" id="input-cor-primaria" value="<?= htmlspecialchars($corPrimariaAtual) ?>" class="w-11 h-11" oninput="atualizarPreview()">
                    <input type="text" id="input-cor-primaria-hex" value="<?= htmlspecialchars($corPrimariaAtual) ?>" maxlength="7" class="w-24 p-2 bg-dark-card border border-dark-border rounded-lg text-white text-xs font-mono outline-none focus:border-brand-500 transition" oninput="sincronizarHex('primaria')">
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 pt-2 border-t border-dark-border">
                <div>
                    <label class="block text-xs font-bold text-white">Cor secundária</label>
                    <p class="text-[11px] text-slate-500">Usada nos gradientes e detalhes</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <input type="color" id="input-cor-secundaria" value="<?= htmlspecialchars($corSecundariaAtual) ?>" class="w-11 h-11" oninput="atualizarPreview()">
                    <input type="text" id="input-cor-secundaria-hex" value="<?= htmlspecialchars($corSecundariaAtual) ?>" maxlength="7" class="w-24 p-2 bg-dark-card border border-dark-border rounded-lg text-white text-xs font-mono outline-none focus:border-brand-500 transition" oninput="sincronizarHex('secundaria')">
                </div>
            </div>
        </div>

        <!-- Logo -->
        <div class="bg-dark-surface border border-dark-border rounded-2xl p-5 space-y-2">
            <label class="block text-xs font-bold text-white">Logo da loja</label>
            <p class="text-[11px] text-slate-500 mb-1">Cole o link de uma imagem já hospedada (ex: https://...). Deixe em branco para usar a logo padrão do sistema.</p>
            <input type="text" id="input-logo-url" value="<?= htmlspecialchars($logoAtual) ?>" placeholder="https://exemplo.com/logo.png" oninput="atualizarPreview()" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
        </div>

        <button onclick="salvarConfiguracoes()" class="w-full bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white font-extrabold p-3.5 rounded-xl text-xs transition active:scale-95 shadow-lg shadow-purple-950/60 flex items-center justify-center gap-2">
            <i class="fa-solid fa-check"></i> Salvar Identidade Visual
        </button>
    </main>

    <script>
        const estabId = <?= $estabId ?>;

        function sincronizarHex(campo) {
            const hex = document.getElementById(`input-cor-${campo}-hex`).value;
            if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
                document.getElementById(`input-cor-${campo}`).value = hex;
            }
            atualizarPreview();
        }

        function atualizarPreview() {
            const corP = document.getElementById('input-cor-primaria').value;
            const corS = document.getElementById('input-cor-secundaria').value;
            document.getElementById('input-cor-primaria-hex').value = corP;
            document.getElementById('input-cor-secundaria-hex').value = corS;

            const gradiente = `linear-gradient(135deg, ${corP}, ${corS})`;
            document.getElementById('preview-logo-box').style.background = gradiente;
            document.getElementById('preview-botao').style.background = `linear-gradient(90deg, ${corP}, ${corS})`;

            const logoUrl = document.getElementById('input-logo-url').value.trim();
            const img = document.getElementById('preview-logo-img');
            if (logoUrl) {
                img.src = logoUrl;
                img.style.display = '';
            }
        }

        async function salvarConfiguracoes() {
            const corPrimaria = document.getElementById('input-cor-primaria').value;
            const corSecundaria = document.getElementById('input-cor-secundaria').value;
            const logoUrl = document.getElementById('input-logo-url').value.trim();

            try {
                const res = await fetch('../api/atualizar_estabelecimento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ estab_id: estabId, cor_primaria: corPrimaria, cor_secundaria: corSecundaria, logo_url: logoUrl })
                });
                const resultado = await res.json();

                if (resultado.sucesso) {
                    await Swal.fire({ icon: 'success', title: 'Salvo!', text: 'A página vai recarregar pra aplicar o novo visual.', background: '#141021', color: '#fff', timer: 1800, showConfirmButton: false });
                    window.location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: resultado.erro || 'Erro ao salvar.', background: '#141021', color: '#fff' });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Falha de conexão ao salvar.', background: '#141021', color: '#fff' });
            }
        }
    </script>
</body>
</html>
