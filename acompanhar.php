<?php
require_once __DIR__ . '/config/conexao.php';

$pedidoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, e.nome as estab_nome, e.logo_url, e.whatsapp as estab_whatsapp, e.cor_primaria, e.cor_secundaria
    FROM pedidos p
    INNER JOIN estabelecimentos e ON p.estabelecimento_id = e.id
    WHERE p.id = :id
");
$stmt->execute([':id' => $pedidoId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("<div style='min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0B0914; color:#fff; font-family:sans-serif;'><h2>Pedido não encontrado.</h2></div>");
}

$stmtItens = $pdo->prepare("
    SELECT i.*, COALESCE(p.nome, 'Item Personalizado') as produto_nome 
    FROM itens_pedido i
    LEFT JOIN produtos p ON i.produto_id = p.id
    WHERE i.pedido_id = :id
");
$stmtItens->execute([':id' => $pedidoId]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acompanhar Pedido - <?= htmlspecialchars($pedido['estab_nome']) ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <?php
        require_once __DIR__ . '/includes/tema.php';
        tema_imprimirTailwindConfig($pedido, ['Plus Jakarta Sans', 'Inter', 'sans-serif']);
    ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #0B0914;
            color: #F8FAFC;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
    </style>
</head>
<body class="min-h-screen antialiased flex justify-center selection:bg-brand-500 selection:text-white p-4 sm:py-8">

    <div class="w-full max-w-md space-y-4">

        <header class="bg-dark-surface border border-dark-border rounded-3xl p-5 text-center shadow-xl shadow-black/40">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-tr from-brand-600 to-fuchsia-500 p-[2px] shadow-lg shadow-purple-950/60 mb-3">
                <div class="w-full h-full bg-dark-base rounded-[14px] flex items-center justify-center overflow-hidden">
                    <?php $logoAcompanhar = tema_logoSrc($pedido); ?>
                    <?php if ($logoAcompanhar): ?>
                        <img src="<?= htmlspecialchars($logoAcompanhar) ?>" alt="Logo" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-2xl">🍔</span>
                    <?php endif; ?>
                </div>
            </div>

            <h1 class="text-lg font-extrabold text-white tracking-tight"><?= htmlspecialchars($pedido['estab_nome']) ?></h1>
            <p class="text-xs text-slate-400 mt-1">
                Olá, <strong class="text-purple-300"><?= htmlspecialchars($pedido['cliente_nome']) ?></strong>! Acompanhe o status do seu pedido:
            </p>
        </header>

        <section class="bg-dark-surface border border-dark-border rounded-3xl p-5 space-y-5 shadow-xl shadow-black/40">
            <div class="flex items-center gap-3.5">
                <div id="status-icone-box" class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xl flex-shrink-0">
                    <i id="status-icone" class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <span class="text-[10px] uppercase tracking-wider font-extrabold text-slate-400">Status Atual</span>
                    <h2 id="status-titulo" class="text-base font-extrabold text-white">Pedido Recebido</h2>
                    <p id="status-subtitulo" class="text-xs text-slate-400 mt-0.5">Seu pedido foi registrado na cozinha</p>
                </div>
            </div>

            <div class="space-y-2">
                <div class="w-full bg-dark-card rounded-full h-2 overflow-hidden border border-white/5">
                    <div id="status-barra" class="bg-gradient-to-r from-brand-600 to-fuchsia-500 h-full rounded-full transition-all duration-500" style="width: 25%"></div>
                </div>

                <div class="grid grid-cols-4 text-[10px] text-center font-bold text-slate-500 pt-1">
                    <span id="step-1" class="text-purple-400">Recebido</span>
                    <span id="step-2">Preparo</span>
                    <span id="step-3">Entrega</span>
                    <span id="step-4">Concluído</span>
                </div>
            </div>
        </section>

        <section class="bg-dark-surface border border-dark-border rounded-3xl p-5 space-y-3.5 shadow-xl shadow-black/40">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-purple-400 flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping"></i> Resumo do Pedido
            </h3>

            <div class="divide-y divide-white/5 space-y-2">
                <?php foreach ($itens as $item): ?>
                    <div class="pt-2 first:pt-0 flex justify-between items-start text-xs">
                        <div class="pr-2">
                            <strong class="text-white"><?= $item['quantidade'] ?>x <?= htmlspecialchars($item['produto_nome']) ?></strong>
                            <?php if (!empty($item['observacao'])): ?>
                                <div class="text-[11px] text-purple-300/80 mt-0.5"><?= htmlspecialchars($item['observacao']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="font-bold text-slate-300 flex-shrink-0">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-3 border-t border-white/5 space-y-1 text-xs text-slate-400">
                <div class="flex justify-between">
                    <span>Subtotal:</span>
                    <span class="text-slate-200">R$ <?= number_format($pedido['subtotal'], 2, ',', '.') ?></span>
                </div>
                <?php if ($pedido['tipo_entrega'] === 'delivery'): ?>
                    <div class="flex justify-between">
                        <span>Taxa de Entrega:</span>
                        <span class="text-slate-200">R$ <?= number_format($pedido['taxa_entrega'], 2, ',', '.') ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-sm font-extrabold text-white pt-1.5 border-t border-white/5">
                    <span>Total:</span>
                    <span class="text-brand-500 font-black">R$ <?= number_format($pedido['total'], 2, ',', '.') ?></span>
                </div>
            </div>

            <div class="pt-2 text-[11px] text-slate-400 bg-dark-card p-3 rounded-2xl border border-white/5">
                <?php if ($pedido['tipo_entrega'] === 'delivery'): ?>
                    <div>🛵 <strong>Entrega em:</strong> <?= htmlspecialchars($pedido['cliente_endereco']) ?> - <?= htmlspecialchars($pedido['cliente_bairro']) ?></div>
                <?php else: ?>
                    <div>🏪 <strong>Retirada:</strong> Retirar no balcão</div>
                <?php endif; ?>
                <div class="mt-1">💳 <strong>Pagamento:</strong> <?= strtoupper($pedido['forma_pagamento']) ?></div>
            </div>
        </section>

        <div class="space-y-2 text-center pt-2">
            <?php 
                $zapEstab = preg_replace('/\D/', '', $pedido['estab_whatsapp']);
                $msgZap = urlencode("Olá, gostaria de informações sobre o meu pedido!");
            ?>
            <a href="https://wa.me/55<?= $zapEstab ?>?text=<?= $msgZap ?>" target="_blank" class="w-full bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-bold p-3.5 rounded-2xl text-xs flex justify-center items-center gap-2 transition">
                <i class="fa-brands fa-whatsapp text-sm"></i> Falar com a Lanchonete
            </a>

            <a href="cardapio.php?estab=<?= $pedido['estabelecimento_id'] ?>" class="inline-block text-xs font-bold text-purple-400 hover:text-purple-300 py-2">
                ← Fazer outro pedido
            </a>
        </div>

    </div>

    <script>
        const pedidoId = <?= $pedido['id'] ?>;

        async function checarStatus() {
            try {
                const res = await fetch(`api/status_pedido.php?id=${pedidoId}`);
                const data = await res.json();
                if (data.sucesso) atualizarInterfaceStatus(data.status);
            } catch (err) {}
        }

        function atualizarInterfaceStatus(status) {
            const iconeBox = document.getElementById('status-icone-box');
            const icone = document.getElementById('status-icone');
            const titulo = document.getElementById('status-titulo');
            const subtitulo = document.getElementById('status-subtitulo');
            const barra = document.getElementById('status-barra');

            const s1 = document.getElementById('step-1');
            const s2 = document.getElementById('step-2');
            const s3 = document.getElementById('step-3');
            const s4 = document.getElementById('step-4');

            [s1, s2, s3, s4].forEach(s => s.className = 'text-slate-500');

            if (status === 'novo') {
                iconeBox.className = 'w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xl flex-shrink-0';
                icone.className = 'fa-solid fa-receipt';
                titulo.innerText = 'Pedido Recebido';
                subtitulo.innerText = 'Seu pedido foi registrado na cozinha';
                barra.style.width = '25%';
                s1.className = 'text-amber-400 font-bold';
            } else if (status === 'em_preparo') {
                iconeBox.className = 'w-12 h-12 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center text-xl flex-shrink-0';
                icone.className = 'fa-solid fa-fire-burner';
                titulo.innerText = 'Em Preparo na Cozinha';
                subtitulo.innerText = 'Seu pedido está sendo preparado';
                barra.style.width = '55%';
                s1.className = 'text-purple-400';
                s2.className = 'text-blue-400 font-bold';
            } else if (status === 'saiu_entrega') {
                iconeBox.className = 'w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-xl flex-shrink-0';
                icone.className = 'fa-solid fa-motorcycle';
                titulo.innerText = 'Saiu para Entrega!';
                subtitulo.innerText = 'O entregador está a caminho';
                barra.style.width = '80%';
                s1.className = 'text-purple-400';
                s2.className = 'text-purple-400';
                s3.className = 'text-purple-400 font-bold';
            } else if (status === 'concluido') {
                iconeBox.className = 'w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl flex-shrink-0';
                icone.className = 'fa-solid fa-circle-check';
                titulo.innerText = 'Pedido Entregue!';
                subtitulo.innerText = 'Bom apetite! Obrigado pela preferência';
                barra.style.width = '100%';
                [s1, s2, s3, s4].forEach(s => s.className = 'text-emerald-400 font-bold');
            }
        }

        checarStatus();
        setInterval(checarStatus, 4000);
    </script>
</body>
</html>