<?php
// admin/index.php
require_once __DIR__ . '/auth.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

$stmt = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $estabId]);
$estab = $stmt->fetch(PDO::FETCH_ASSOC);

$nomeEstab = $estab['nome'] ?? 'Drilavy Lanchonete e Pizzaria';
$lojaAbertaInicial = false;
if ($estab) {
    if (isset($estab['status'])) {
        $st = strtolower(trim((string)$estab['status']));
        $lojaAbertaInicial = ($st === '1' || $st === 'aberto');
    } elseif (isset($estab['aberto'])) {
        $lojaAbertaInicial = ((int)$estab['aberto'] === 1);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel Drilavy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#a855f7', 600: '#9333ea', 700: '#7e22ce' },
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
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
    </style>
</head>
<body class="min-h-screen bg-dark-base text-slate-100 flex flex-col antialiased">

    <audio id="audio-alerta" preload="auto">
        <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
    </audio>

    <!-- Top Header -->
    <header class="sticky top-0 z-40 bg-dark-surface/90 backdrop-blur-md border-b border-dark-border px-4 sm:px-6 py-3">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-2">
            
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-brand-600 to-fuchsia-500 p-0.5 shadow-lg flex items-center justify-center shrink-0">
                    <div class="w-full h-full bg-[#0B0914] rounded-full flex items-center justify-center overflow-hidden">
                        <img src="../assets/img/logo.png" alt="Logo" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='../assets/img/logo.jpg';">
                    </div>
                </div>
                <h1 class="text-sm font-extrabold text-white tracking-tight flex items-center gap-2">
                    <?= htmlspecialchars($nomeEstab) ?>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="alternarStatusLoja()" id="btn-status-loja" class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border active:scale-95">
                    <i class="fa-solid fa-store text-xs"></i>
                    <span id="txt-status-loja">Carregando...</span>
                </button>

                <a href="marketing.php?estab=<?= $estabId ?>" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-brand-500/10 hover:bg-brand-500/20 text-brand-400 border border-brand-500/20 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-bullhorn text-xs"></i>
                    <span class="hidden sm:inline">Transmissão</span>
                </a>

                <a href="whatsapp.php?estab=<?= $estabId ?>" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 transition flex items-center gap-1.5">
                    <i class="fa-brands fa-whatsapp text-xs"></i>
                    <span class="hidden sm:inline">WhatsApp</span>
                </a>

                <a href="cardapio.php?estab=<?= $estabId ?>" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white/5 hover:bg-white/10 text-slate-300 border border-dark-border transition flex items-center gap-1.5">
                    <i class="fa-solid fa-utensils text-xs"></i>
                    <span class="hidden sm:inline">Cardápio</span>
                </a>

                <a href="logout.php" title="Sair" class="w-8 h-8 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 flex items-center justify-center transition">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                </a>
            </div>

        </div>
    </header>

    <!-- Kanban Board -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-3 sm:p-6 overflow-x-auto">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 min-w-[300px]">
            
            <!-- Novos Pedidos -->
            <div class="flex flex-col bg-dark-surface border border-dark-border rounded-3xl p-3.5 min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-dark-border">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shadow-sm shadow-amber-400/50 animate-pulse"></span>
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Novos Pedidos</h2>
                    </div>
                    <span id="count-novo" class="px-2 py-0.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[11px] font-black">0</span>
                </div>
                <div id="col-novo" class="space-y-3 flex-1 overflow-y-auto custom-scrollbar pr-1"></div>
            </div>

            <!-- Na Cozinha / Preparo -->
            <div class="flex flex-col bg-dark-surface border border-dark-border rounded-3xl p-3.5 min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-dark-border">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400 shadow-sm shadow-blue-400/50"></span>
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Na Cozinha / Preparo</h2>
                    </div>
                    <span id="count-em_preparo" class="px-2 py-0.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-300 text-[11px] font-black">0</span>
                </div>
                <div id="col-em_preparo" class="space-y-3 flex-1 overflow-y-auto custom-scrollbar pr-1"></div>
            </div>

            <!-- Em Entrega / Balcão -->
            <div class="flex flex-col bg-dark-surface border border-dark-border rounded-3xl p-3.5 min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-dark-border">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-brand-400 shadow-sm shadow-brand-400/50"></span>
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Em Entrega / Balcão</h2>
                    </div>
                    <span id="count-saiu_entrega" class="px-2 py-0.5 rounded-lg bg-brand-500/10 border border-brand-500/20 text-brand-300 text-[11px] font-black">0</span>
                </div>
                <div id="col-saiu_entrega" class="space-y-3 flex-1 overflow-y-auto custom-scrollbar pr-1"></div>
            </div>

            <!-- Finalizados Hoje -->
            <div class="flex flex-col bg-dark-surface border border-dark-border rounded-3xl p-3.5 min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-dark-border">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400/50"></span>
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Finalizados Hoje</h2>
                    </div>
                    <span id="count-concluido" class="px-2 py-0.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-[11px] font-black">0</span>
                </div>
                <div id="col-concluido" class="space-y-3 flex-1 overflow-y-auto custom-scrollbar pr-1"></div>
            </div>

        </div>
    </main>

    <script>
        const estabId = <?= $estabId ?>;
        let lojaAberta = <?= $lojaAbertaInicial ? 'true' : 'false' ?>;
        let ultimosPedidosIds = new Set();
        let primeiraCarga = true;

        const formatBRL = (val) => (parseFloat(val) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        function atualizarBotaoStatus() {
            const btn = document.getElementById('btn-status-loja');
            const txt = document.getElementById('txt-status-loja');
            if (!btn || !txt) return;

            if (lojaAberta) {
                btn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border-emerald-500/30 active:scale-95 shadow-sm shadow-emerald-950/40';
                txt.innerText = 'Loja Aberta';
            } else {
                btn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border bg-red-500/10 hover:bg-red-500/20 text-red-400 border-red-500/30 active:scale-95 shadow-sm shadow-red-950/40';
                txt.innerText = 'Loja Fechada';
            }
        }

        async function alternarStatusLoja() {
            const vaiAbrir = !lojaAberta;
            
            const confirmacao = await Swal.fire({
                title: vaiAbrir ? 'Abrir a Loja? 🟢' : 'Fechar a Loja? 🔴',
                html: vaiAbrir 
                    ? 'O cardápio será liberado e os clientes poderão fazer pedidos normalmente.' 
                    : 'O cardápio será pausado para novos pedidos.',
                icon: vaiAbrir ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: vaiAbrir ? 'Sim, Abrir' : 'Sim, Fechar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: vaiAbrir ? '#10b981' : '#ef4444',
                cancelButtonColor: '#334155'
            });

            if (!confirmacao.isConfirmed) return;

            const btn = document.getElementById('btn-status-loja');
            btn.disabled = true;

            try {
                const res = await fetch(`../api/status_loja.php?estab=${estabId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: vaiAbrir ? 1 : 0 })
                });
                const data = await res.json();
                
                if (data && data.sucesso) {
                    lojaAberta = (data.status === 1 || data.aberto === true);
                    atualizarBotaoStatus();

                    Swal.fire({
                        title: lojaAberta ? 'Loja Aberta! 🎉' : 'Loja Fechada! 🔒',
                        text: lojaAberta ? 'O cardápio está aberto para novos pedidos.' : 'O cardápio foi fechado.',
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            } catch (e) {
                Swal.fire('Erro', 'Não foi possível alterar o status da loja.', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function tocarAlertaSonoro() {
            try {
                const audio = document.getElementById('audio-alerta');
                if (audio) {
                    audio.currentTime = 0;
                    audio.play().catch(() => {});
                }
            } catch(e) {}
        }

        async function carregarPedidos() {
            try {
                const res = await fetch(`../api/pedidos.php?estab=${estabId}&t=${Date.now()}`);
                const data = await res.json();
                if (data && data.sucesso) renderizarKanban(data.pedidos || []);
            } catch (err) {}
        }

        function renderizarKanban(pedidos) {
            const cols = {
                novo: document.getElementById('col-novo'),
                em_preparo: document.getElementById('col-em_preparo'),
                saiu_entrega: document.getElementById('col-saiu_entrega'),
                concluido: document.getElementById('col-concluido')
            };

            const counts = { novo: 0, em_preparo: 0, saiu_entrega: 0, concluido: 0 };
            let temNovoPedido = false;

            Object.values(cols).forEach(col => { if (col) col.innerHTML = ''; });

            pedidos.forEach(p => {
                const st = (p.status || 'novo').toLowerCase();
                
                // Ignora pedidos arquivados (removidos da tela)
                if (st === 'arquivado') return;

                // Garante que só vá para uma coluna válida
                const colunaDestino = cols[st];
                if (!colunaDestino) return;

                counts[st]++;

                const pedId = parseInt(p.id);
                if (!primeiraCarga && st === 'novo' && !ultimosPedidosIds.has(pedId)) {
                    temNovoPedido = true;
                }

                colunaDestino.appendChild(criarCardPedido(p));
            });

            if (temNovoPedido) {
                tocarAlertaSonoro();
                Swal.fire({
                    title: 'Novo Pedido! 🔔',
                    text: 'Um novo pedido acabou de entrar.',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });
            }

            ultimosPedidosIds = new Set(pedidos.map(p => parseInt(p.id)));
            primeiraCarga = false;

            document.getElementById('count-novo').innerText = counts.novo;
            document.getElementById('count-em_preparo').innerText = counts.em_preparo;
            document.getElementById('count-saiu_entrega').innerText = counts.saiu_entrega;
            document.getElementById('count-concluido').innerText = counts.concluido;
        }

        function criarCardPedido(p) {
            const card = document.createElement('div');
            card.className = 'bg-dark-card border border-dark-border rounded-2xl p-3.5 shadow-lg hover:border-brand-500/40 transition space-y-3';

            let dataFormatada = '--:--';
            if (p.criado_em) {
                try {
                    const dt = new Date(p.criado_em.replace(/-/g, '/'));
                    dataFormatada = dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                } catch(e) {}
            }

            const isDelivery = (p.tipo_entrega || 'delivery') === 'delivery';

            let itensHtml = '';
            if (Array.isArray(p.itens) && p.itens.length > 0) {
                itensHtml = p.itens.map(it => `
                    <div class="text-[11px] text-slate-300 py-1 border-b border-white/5 last:border-0 flex justify-between items-start">
                        <div>
                            <span class="font-bold text-white">${it.quantidade || 1}x</span> ${it.produto_nome || 'Produto'}
                            ${it.observacao ? `<p class="text-[10px] text-brand-400 font-semibold mt-0.5">Obs: ${it.observacao}</p>` : ''}
                        </div>
                        <span class="font-mono text-slate-400 shrink-0 ml-2">${formatBRL(it.subtotal || it.preco_unitario || 0)}</span>
                    </div>
                `).join('');
            }

            let botoesAcao = '';
            const st = (p.status || 'novo').toLowerCase();
            if (st === 'novo') {
                botoesAcao = `
                    <button onclick="mudarStatusPedido(${p.id}, 'em_preparo')" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl text-xs transition active:scale-95 flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-fire-burner"></i> Iniciar Preparo
                    </button>
                `;
            } else if (st === 'em_preparo') {
                botoesAcao = `
                    <button onclick="despacharOuAvisarPronto(${p.id}, '${isDelivery ? 'delivery' : 'retirada'}', '${encodeURIComponent(p.cliente_nome || '')}', '${encodeURIComponent(p.cliente_whatsapp || '')}')" class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white font-extrabold rounded-xl text-xs transition active:scale-95 flex items-center justify-center gap-1.5">
                        <i class="fa-solid ${isDelivery ? 'fa-motorcycle' : 'fa-store'}"></i> ${isDelivery ? 'Despachar Entrega' : 'Pronto para Retirada'}
                    </button>
                `;
            } else if (st === 'saiu_entrega') {
                botoesAcao = `
                    <button onclick="mudarStatusPedido(${p.id}, 'concluido')" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl text-xs transition active:scale-95 flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-check-double"></i> Concluir Pedido
                    </button>
                `;
            }

            const nomeClienteSeguro = (p.cliente_nome || 'Cliente').replace(/'/g, "\\'");

            card.innerHTML = `
                <div class="flex items-start justify-between gap-2 border-b border-dark-border pb-2.5">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold ${isDelivery ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'}">
                                <i class="fa-solid ${isDelivery ? 'fa-motorcycle' : 'fa-store'} text-[9px]"></i> ${isDelivery ? 'Delivery' : 'Retirada no Balcão'}
                            </span>
                        </div>
                        <p class="text-sm font-extrabold text-white mt-1">${p.cliente_nome || 'Cliente'}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-slate-400 font-mono font-bold">${dataFormatada}</span>
                        <!-- Botão de Limpar da Tela -->
                        <button onclick="removerPedidoDaTela(${p.id}, '${nomeClienteSeguro}')" title="Limpar pedido da tela" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-red-500/20 text-slate-400 hover:text-red-400 border border-white/10 flex items-center justify-center transition">
                            <i class="fa-solid fa-xmark text-[11px]"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-[#141021] p-2.5 rounded-xl border border-white/5 space-y-1">
                    ${itensHtml}
                </div>

                <div class="space-y-1 text-[11px] text-slate-300">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Pagamento:</span>
                        <span class="font-bold text-white uppercase">${p.forma_pagamento || 'PIX'} ${p.troco_para ? `<span class="text-amber-400 text-[10px]">(Troco p/ ${formatBRL(p.troco_para)})</span>` : ''}</span>
                    </div>

                    ${isDelivery && p.cliente_endereco ? `
                        <div class="pt-1 text-[10px] text-slate-400 border-t border-white/5">
                            <i class="fa-solid fa-location-dot text-brand-400"></i> ${p.cliente_endereco}${p.cliente_bairro ? `, ${p.cliente_bairro}` : ''} ${p.cliente_complemento ? `(${p.cliente_complemento})` : ''}
                        </div>
                    ` : ''}

                    ${!isDelivery ? `
                        <div class="pt-1 text-[10px] text-amber-300 border-t border-white/5">
                            <i class="fa-solid fa-store text-amber-400"></i> O cliente irá retirar no balcão
                        </div>
                    ` : ''}

                    ${p.observacoes ? `
                        <div class="p-1.5 bg-amber-500/10 border border-amber-500/20 text-amber-300 rounded-lg text-[10px]">
                            <b>Obs:</b> ${p.observacoes}
                        </div>
                    ` : ''}

                    <div class="flex justify-between items-center pt-1.5 border-t border-dark-border">
                        <span class="font-bold text-slate-400">Total:</span>
                        <span class="font-extrabold text-sm text-emerald-400">${formatBRL(p.total || 0)}</span>
                    </div>
                </div>

                ${botoesAcao ? `<div class="pt-1">${botoesAcao}</div>` : ''}
            `;

            return card;
        }

        async function mudarStatusPedido(pedidoId, novoStatus) {
            try {
                const res = await fetch(`../api/pedidos.php?estab=${estabId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pedido_id: pedidoId, status: novoStatus })
                });
                const data = await res.json();
                if (data && data.sucesso) carregarPedidos();
            } catch (err) {}
        }

        // Despacha ou avisa que está pronto no balcão, com mensagem personalizada para o WhatsApp
        async function despacharOuAvisarPronto(pedidoId, tipoEntrega, nomeEncoded, wppEncoded) {
            const nome = decodeURIComponent(nomeEncoded);
            const wpp = decodeURIComponent(wppEncoded).replace(/\D/g, '');
            const isDelivery = (tipoEntrega === 'delivery');

            // Primeiro atualiza o status para a coluna 3 (saiu_entrega)
            await mudarStatusPedido(pedidoId, 'saiu_entrega');

            // Mensagem personalizada conforme o tipo de entrega
            let textoMsg = '';
            if (isDelivery) {
                textoMsg = `Olá, *${nome}*! Seu pedido acabou de sair para entrega e está a caminho da sua casa! 🛵💨`;
            } else {
                textoMsg = `Olá, *${nome}*! Seu pedido já está prontinho e te aguardando para retirada no nosso balcão! 🍔🍕`;
            }

            if (wpp) {
                const linkWpp = `https://api.whatsapp.com/send?phone=55${wpp}&text=${encodeURIComponent(textoMsg)}`;
                
                Swal.fire({
                    title: isDelivery ? 'Pedido Despachado!' : 'Pronto para Retirada!',
                    text: isDelivery ? 'Deseja avisar o cliente no WhatsApp que o pedido saiu?' : 'Deseja avisar o cliente no WhatsApp que já pode buscar no balcão?',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-brands fa-whatsapp"></i> Avisar no WhatsApp',
                    cancelButtonText: 'Não precisa avisar',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#334155'
                }).then((r) => {
                    if (r.isConfirmed) {
                        window.open(linkWpp, '_blank');
                    }
                });
            }
        }

        // Remove o card da tela de forma clara e sem termos técnicos
        async function removerPedidoDaTela(pedidoId, nomeCliente) {
            const conf = await Swal.fire({
                title: `Limpar pedido de ${nomeCliente}?`,
                text: 'O pedido sairá do painel para manter sua tela organizada. Ele continuará registrado no seu histórico de vendas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, Limpar',
                cancelButtonText: 'Voltar',
                confirmButtonColor: '#9333ea',
                cancelButtonColor: '#334155'
            });

            if (!conf.isConfirmed) return;

            try {
                const res = await fetch(`../api/pedidos.php?estab=${estabId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pedido_id: pedidoId, status: 'arquivado' })
                });
                const data = await res.json();
                if (data && data.sucesso) {
                    carregarPedidos();
                }
            } catch(e) {}
        }

        atualizarBotaoStatus();
        carregarPedidos();
        setInterval(carregarPedidos, 3000);
    </script>
</body>
</html>