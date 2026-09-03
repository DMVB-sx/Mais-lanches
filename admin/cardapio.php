<?php
// cardapio.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

// 1. Busca dados do estabelecimento
$stmtEstab = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id LIMIT 1");
$stmtEstab->execute([':id' => $estabId]);
$estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);

if (!$estab) {
    die("<h2 style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>Estabelecimento não encontrado.</h2>");
}

// 2. Busca categorias
$stmtCat = $pdo->prepare("
    SELECT * FROM categorias 
    WHERE estabelecimento_id = :estab 
    ORDER BY id ASC
");
$stmtCat->execute([':estab' => $estabId]);
$categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// 3. Busca produtos
$stmtProd = $pdo->prepare("
    SELECT * FROM produtos 
    WHERE estabelecimento_id = :estab 
    ORDER BY categoria_id ASC, preco ASC
");
$stmtProd->execute([':estab' => $estabId]);
$produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

$produtosPorCategoria = [];
foreach ($produtos as $p) {
    $produtosPorCategoria[$p['categoria_id']][] = $p;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($estab['nome']) ?> - Cardápio Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 400: '#c084fc', 500: '#a855f7', 600: '#9333ea', 700: '#7e22ce' },
                        dark: { base: '#0B0914', surface: '#141021', card: '#1C172E', border: 'rgba(255, 255, 255, 0.08)' }
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen bg-dark-base text-slate-100 flex flex-col antialiased pb-28">

    <!-- Header / Banner da Loja -->
    <header class="pt-8 pb-4 px-4 text-center flex flex-col items-center">
        <!-- Logo -->
        <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-brand-600 to-fuchsia-500 p-1 shadow-xl shadow-purple-950/80 mb-3 relative">
            <div class="w-full h-full bg-[#0B0914] rounded-full flex items-center justify-center overflow-hidden">
                <img src="assets/img/logo.png" alt="Logo" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='assets/img/logo.jpg';">
            </div>
        </div>

        <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight">
            <?= htmlspecialchars($estab['nome']) ?>
        </h1>

        <!-- Container da Badge Aberto/Fechado (Controlado em Tempo Real via API) -->
        <div id="container-status-loja" class="mt-2.5">
            <span class="px-3.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-white/10 inline-flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-spinner fa-spin text-[10px]"></i> Verificando status...
            </span>
        </div>

        <!-- Faixa Informativa de Horário -->
        <div class="mt-3 w-full max-w-md bg-[#181326] border border-amber-500/20 rounded-2xl py-2 px-4 text-xs font-semibold text-amber-300 flex items-center justify-center gap-2 shadow-inner">
            <i class="fa-regular fa-clock text-amber-400"></i>
            <span>Horário de funcionamento: <b>18:00 às 22:30</b></span>
        </div>
    </header>

    <!-- Navegação de Categorias -->
    <nav class="sticky top-0 z-30 bg-dark-base/90 backdrop-blur-md border-y border-dark-border py-3 px-4">
        <div class="max-w-4xl mx-auto flex items-center gap-2 overflow-x-auto no-scrollbar">
            <?php foreach ($categorias as $index => $cat): ?>
                <a href="#cat-<?= $cat['id'] ?>" class="px-4 py-2 rounded-2xl text-xs font-bold whitespace-nowrap transition <?= $index === 0 ? 'bg-gradient-to-r from-brand-600 to-fuchsia-600 text-white shadow-lg' : 'bg-dark-card text-slate-300 border border-dark-border' ?>">
                    <?= htmlspecialchars($cat['nome']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Lista de Itens -->
    <main class="max-w-4xl mx-auto w-full px-4 mt-6 space-y-8 flex-1">
        <?php foreach ($categorias as $cat): ?>
            <?php $prods = $produtosPorCategoria[$cat['id']] ?? []; ?>
            <?php if (!empty($prods)): ?>
                <section id="cat-<?= $cat['id'] ?>" class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xs sm:text-sm font-extrabold uppercase tracking-wider text-white">
                            <?= htmlspecialchars($cat['nome']) ?>
                        </h2>
                        <span class="text-[11px] font-bold text-purple-400 bg-purple-500/10 border border-purple-500/20 px-2.5 py-0.5 rounded-full">
                            <?= count($prods) ?> <?= count($prods) == 1 ? 'opção' : 'opções' ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <?php foreach ($prods as $p): ?>
                            <div class="bg-dark-card border border-dark-border rounded-2xl p-4 shadow-lg flex items-center justify-between gap-3 hover:border-brand-500/30 transition">
                                <div class="space-y-1 flex-1">
                                    <h3 class="text-sm font-bold text-white"><?= htmlspecialchars($p['nome']) ?></h3>
                                    <?php if (!empty($p['descricao'])): ?>
                                        <p class="text-xs text-slate-400 line-clamp-2"><?= htmlspecialchars($p['descricao']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-sm font-black text-brand-400 pt-1">
                                        R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                    </p>
                                </div>

                                <button onclick="adicionarAoCarrinho(<?= htmlspecialchars(json_encode($p)) ?>)" class="px-4 py-2 bg-brand-600/20 hover:bg-brand-600 text-brand-300 hover:text-white border border-brand-500/30 rounded-xl text-xs font-bold transition active:scale-95 shrink-0 flex items-center gap-1.5">
                                    <i class="fa-solid fa-plus text-[10px]"></i> Adicionar
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    </main>

    <!-- Barra Inferior Sacola -->
    <div id="barra-sacola" class="fixed bottom-0 inset-x-0 bg-dark-surface/95 backdrop-blur-lg border-t border-dark-border p-3 sm:p-4 z-40">
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Total da sua sacola</p>
                <p id="total-sacola-barra" class="text-base font-extrabold text-white">R$ 0,00</p>
            </div>

            <button onclick="abrirModalSacola()" class="px-5 py-3 bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white font-extrabold rounded-2xl text-xs sm:text-sm transition active:scale-95 shadow-lg flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping"></i> Ver Sacola (<span id="qtd-itens-barra">0</span>)
            </button>
        </div>
    </div>

    <!-- Modal da Sacola / Finalização -->
    <div id="modal-sacola" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="bg-dark-surface border border-dark-border w-full max-w-lg rounded-t-3xl sm:rounded-3xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-dark-border flex items-center justify-between">
                <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <i class="fa-solid fa-bag-shopping text-brand-400"></i> Sua Sacola
                </h3>
                <button onclick="fecharModalSacola()" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-slate-300 flex items-center justify-center">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto space-y-4 flex-1">
                <div id="lista-itens-sacola" class="space-y-2"></div>

                <div class="space-y-2 pt-2 border-t border-dark-border">
                    <label class="block text-xs font-bold text-slate-300">Tipo de Entrega</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center gap-2 p-3 bg-dark-card border border-brand-500/40 rounded-xl cursor-pointer text-xs font-bold text-white">
                            <input type="radio" name="tipo_entrega" value="delivery" checked onchange="atualizarTotais()">
                            <i class="fa-solid fa-motorcycle text-brand-400"></i> Delivery
                        </label>
                        <label class="flex items-center justify-center gap-2 p-3 bg-dark-card border border-dark-border rounded-xl cursor-pointer text-xs font-bold text-white">
                            <input type="radio" name="tipo_entrega" value="retirada" onchange="atualizarTotais()">
                            <i class="fa-solid fa-store text-amber-400"></i> Retirada
                        </label>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-dark-border">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">Seu Nome *</label>
                        <input type="text" id="cli-nome" placeholder="Ex: Daniel" class="w-full p-2.5 bg-dark-card border border-dark-border rounded-xl text-xs text-white focus:border-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">WhatsApp *</label>
                        <input type="tel" id="cli-wpp" placeholder="Ex: 75988887777" class="w-full p-2.5 bg-dark-card border border-dark-border rounded-xl text-xs text-white focus:border-brand-500 focus:outline-none">
                    </div>
                    <div id="box-endereco" class="space-y-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">Rua e Número *</label>
                            <input type="text" id="cli-endereco" placeholder="Ex: Rua A, 123" class="w-full p-2.5 bg-dark-card border border-dark-border rounded-xl text-xs text-white focus:border-brand-500 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 mb-1">Bairro *</label>
                                <input type="text" id="cli-bairro" placeholder="Ex: Centro" class="w-full p-2.5 bg-dark-card border border-dark-border rounded-xl text-xs text-white focus:border-brand-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 mb-1">Complemento</label>
                                <input type="text" id="cli-compl" placeholder="Ex: Casa" class="w-full p-2.5 bg-dark-card border border-dark-border rounded-xl text-xs text-white focus:border-brand-500 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 pt-2 border-t border-dark-border">
                    <label class="block text-xs font-bold text-slate-300">Forma de Pagamento</label>
                    <select id="forma-pagamento" class="w-full p-2.5 bg-dark-card border border-dark-border rounded-xl text-xs text-white focus:border-brand-500 focus:outline-none">
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão na Entrega</option>
                        <option value="dinheiro">Dinheiro</option>
                    </select>
                </div>
            </div>

            <div class="p-4 bg-dark-card border-t border-dark-border space-y-3">
                <div class="flex justify-between text-sm font-extrabold text-white">
                    <span>Total:</span>
                    <span id="resumo-total" class="text-brand-400 text-base">R$ 0,00</span>
                </div>
                <button onclick="enviarPedido()" id="btn-finalizar" class="w-full py-3.5 bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white font-extrabold rounded-2xl text-sm transition active:scale-95 shadow-lg flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check"></i> Concluir Pedido
                </button>
            </div>
        </div>
    </div>

    <script>
        const estabId = <?= $estabId ?>;
        let lojaAbertaStatus = false;
        const TAXA_ENTREGA = 4.00;
        let carrinho = [];

        const formatBRL = (v) => (parseFloat(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        // Consulta a API e atualiza a badge em tempo real
        async function sincronizarStatusLoja() {
            try {
                const res = await fetch(`api/status_loja.php?estab=${estabId}&t=${Date.now()}`);
                const data = await res.json();
                
                if (data && data.sucesso) {
                    lojaAbertaStatus = (data.status === 1 || data.status === 'aberto' || data.aberto === true);
                    const container = document.getElementById('container-status-loja');
                    if (container) {
                        if (lojaAbertaStatus) {
                            container.innerHTML = `
                                <span class="px-3.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 inline-flex items-center gap-1.5 shadow-sm shadow-emerald-950/50">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Aberto agora
                                </span>
                            `;
                        } else {
                            container.innerHTML = `
                                <span class="px-3.5 py-1 rounded-full text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20 inline-flex items-center gap-1.5 shadow-sm shadow-red-950/50">
                                    <span class="w-2 h-2 rounded-full bg-red-400"></span> Fechado no momento
                                </span>
                            `;
                        }
                    }
                }
            } catch(e) {
                console.error(e);
            }
        }

        function adicionarAoCarrinho(p) {
            const item = carrinho.find(i => i.id === p.id);
            if (item) item.qtd++;
            else carrinho.push({ id: p.id, nome: p.nome, preco_base: parseFloat(p.preco), qtd: 1 });
            atualizarTotais();
            Swal.fire({ title: 'Adicionado!', text: `${p.nome} na sacola`, icon: 'success', toast: true, position: 'bottom-end', timer: 1200, showConfirmButton: false });
        }

        function alterarQtd(idx, delta) {
            if (carrinho[idx]) {
                carrinho[idx].qtd += delta;
                if (carrinho[idx].qtd <= 0) carrinho.splice(idx, 1);
            }
            atualizarTotais();
            renderizarItensSacola();
        }

        function atualizarTotais() {
            let qtd = 0, sub = 0;
            carrinho.forEach(i => { qtd += i.qtd; sub += (i.preco_base * i.qtd); });
            const tipo = document.querySelector('input[name="tipo_entrega"]:checked')?.value || 'delivery';
            const taxa = (tipo === 'delivery') ? TAXA_ENTREGA : 0;

            document.getElementById('qtd-itens-barra').innerText = qtd;
            document.getElementById('total-sacola-barra').innerText = formatBRL(sub);
            document.getElementById('resumo-total').innerText = formatBRL(sub + (sub > 0 ? taxa : 0));

            const box = document.getElementById('box-endereco');
            if (tipo === 'retirada') box.classList.add('hidden');
            else box.classList.remove('hidden');
        }

        function renderizarItensSacola() {
            const c = document.getElementById('lista-itens-sacola');
            c.innerHTML = '';
            if (carrinho.length === 0) { c.innerHTML = '<p class="text-center text-xs text-slate-500 py-6">Sacola vazia.</p>'; return; }
            carrinho.forEach((it, idx) => {
                const el = document.createElement('div');
                el.className = 'p-3 bg-dark-card border border-dark-border rounded-xl flex items-center justify-between';
                el.innerHTML = `
                    <div><p class="text-xs font-bold text-white">${it.nome}</p><p class="text-[11px] font-mono text-brand-400">${formatBRL(it.preco_base * it.qtd)}</p></div>
                    <div class="flex items-center gap-2">
                        <button onclick="alterarQtd(${idx}, -1)" class="w-6 h-6 rounded bg-white/5 text-white text-xs">-</button>
                        <span class="text-xs font-bold text-white">${it.qtd}</span>
                        <button onclick="alterarQtd(${idx}, 1)" class="w-6 h-6 rounded bg-white/5 text-white text-xs">+</button>
                    </div>
                `;
                c.appendChild(el);
            });
        }

        function abrirModalSacola() {
            if (!lojaAbertaStatus) {
                Swal.fire({
                    title: 'Loja Fechada',
                    text: 'Nosso estabelecimento está fechado no momento. Horário: 18:00 às 22:30.',
                    icon: 'warning',
                    confirmButtonColor: '#9333ea'
                });
                return;
            }
            if (carrinho.length === 0) {
                Swal.fire('Sacola Vazia', 'Adicione itens antes.', 'info');
                return;
            }
            renderizarItensSacola();
            document.getElementById('modal-sacola').classList.remove('hidden');
        }

        function fecharModalSacola() { document.getElementById('modal-sacola').classList.add('hidden'); }

        async function enviarPedido() {
            const nome = document.getElementById('cli-nome').value.trim();
            const wpp = document.getElementById('cli-wpp').value.trim();
            const tipo = document.querySelector('input[name="tipo_entrega"]:checked')?.value || 'delivery';
            const end = document.getElementById('cli-endereco').value.trim();
            const bairro = document.getElementById('cli-bairro').value.trim();
            const forma = document.getElementById('forma-pagamento').value;

            if (!nome || !wpp) { Swal.fire('Atenção', 'Informe Nome e WhatsApp.', 'warning'); return; }
            if (tipo === 'delivery' && (!end || !bairro)) { Swal.fire('Atenção', 'Informe Endereço e Bairro.', 'warning'); return; }

            let sub = 0; carrinho.forEach(i => sub += (i.preco_base * i.qtd));
            const taxa = (tipo === 'delivery') ? TAXA_ENTREGA : 0;

            const res = await fetch('api/criar_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    estabelecimento_id: estabId,
                    cliente: { nome, whatsapp: wpp, endereco: end, bairro },
                    tipo_entrega: tipo,
                    taxa_entrega: taxa,
                    subtotal: sub,
                    total: sub + taxa,
                    forma_pagamento: forma,
                    itens: carrinho
                })
            });
            const data = await res.json();
            if (data.sucesso) {
                Swal.fire('Pedido Confirmado! 🎉', 'Seu pedido foi registrado.', 'success').then(() => {
                    carrinho = [];
                    atualizarTotais();
                    fecharModalSacola();
                });
            } else {
                Swal.fire('Erro', data.erro, 'error');
            }
        }

        sincronizarStatusLoja();
        setInterval(sincronizarStatusLoja, 3000);
    </script>
</body>
</html>