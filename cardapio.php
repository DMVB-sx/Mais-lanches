<?php
// cardapio.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

$stmtEstab = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id");
$stmtEstab->execute([':id' => $estabId]);
$estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);

if (!$estab) {
    die("<div style='min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0B0914; color:#fff; font-family:sans-serif;'><h2>Estabelecimento indisponível.</h2></div>");
}

// Identifica corretamente se está aberto (seja por coluna status 'aberto' ou aberto = 1)
$lojaAbertaInicial = false;
if (isset($estab['status'])) {
    $lojaAbertaInicial = ($estab['status'] === 'aberto' || $estab['status'] === '1' || $estab['status'] === 1);
} elseif (isset($estab['aberto'])) {
    $lojaAbertaInicial = ((int)$estab['aberto'] === 1);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($estab['nome']) ?> - Cardápio & Delivery</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#a855f7',
                            600: '#9333ea',
                            700: '#7e22ce',
                            900: '#3b0764',
                        },
                        dark: {
                            base: '#0B0914',
                            surface: '#141021',
                            card: '#1C172E',
                            border: 'rgba(255, 255, 255, 0.08)'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #0B0914;
            color: #F8FAFC;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        .sheet-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 60;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .sheet-backdrop.active {
            opacity: 1;
            pointer-events: auto;
        }

        .sheet-content {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            max-height: 90vh;
            background: #141021;
            border-top: 1px solid rgba(168, 85, 247, 0.25);
            border-radius: 28px 28px 0 0;
            z-index: 70;
            transform: translateY(100%);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 580px;
            margin: 0 auto;
            box-shadow: 0 -15px 50px rgba(0,0,0,0.8);
            display: flex;
            flex-direction: column;
        }
        .sheet-content.active {
            transform: translateY(0);
        }

        .sheet-handle {
            width: 44px;
            height: 5px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            margin: 12px auto 6px;
        }
    </style>
</head>
<body class="min-h-screen antialiased flex justify-center selection:bg-brand-500 selection:text-white">

    <div class="w-full max-w-lg min-h-screen bg-dark-base relative pb-32 flex flex-col">

        <!-- Topo da Loja -->
        <header class="relative px-4 pt-8 pb-5 text-center bg-gradient-to-b from-purple-950/40 via-dark-surface to-dark-base border-b border-white/5">
            <div class="flex flex-col items-center">
                
                <!-- Selo da Logo Oficial Drilavy -->
                <div class="relative w-28 h-28 rounded-full bg-gradient-to-tr from-brand-600 via-fuchsia-500 to-purple-400 p-[3px] shadow-2xl shadow-purple-950/90 mb-3 flex items-center justify-center">
                    <div class="w-full h-full bg-[#0B0914] rounded-full flex items-center justify-center overflow-hidden">
                        <img src="assets/img/logo.png" 
                             alt="Logo Drilavy" 
                             class="w-full h-full object-cover" 
                             onerror="this.onerror=null; this.src='assets/img/logo.jpg'; this.onerror=() => { this.style.display='none'; document.getElementById('fallback-icon').style.display='block'; };">
                        <span id="fallback-icon" class="text-4xl hidden">🍔</span>
                    </div>
                </div>

                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight"><?= htmlspecialchars($estab['nome']) ?></h1>

                <!-- Badge de Status -->
                <div id="badge-status-container" class="flex items-center gap-2 mt-2.5">
                    <span id="badge-status" class="inline-flex items-center gap-1.5 px-4 py-1 rounded-full text-xs font-bold <?= $lojaAbertaInicial ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
                        <span class="w-2 h-2 rounded-full <?= $lojaAbertaInicial ? 'bg-emerald-400 animate-pulse' : 'bg-red-500' ?>"></span>
                        <span id="texto-status-loja"><?= $lojaAbertaInicial ? 'Aberto agora' : 'Fechado no momento' ?></span>
                    </span>
                </div>

                <!-- Banner Informativo com Horário Corrigido -->
                <div id="banner-fechado" class="<?= $lojaAbertaInicial ? 'hidden' : '' ?> mt-3.5 w-full bg-amber-500/10 border border-amber-500/20 rounded-2xl p-3 text-xs text-amber-300 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-clock text-amber-400"></i>
                    <span>Horário de funcionamento: <strong>18:00 às 22:30</strong></span>
                </div>
            </div>
        </header>

        <!-- Grade de Categorias com Emojis -->
        <div class="sticky top-0 z-40 bg-dark-base/95 backdrop-blur-md border-b border-white/5 py-3.5 px-4">
            <nav class="flex flex-wrap gap-2 justify-center" id="nav-categorias"></nav>
        </div>

        <!-- Lista de Produtos -->
        <main class="flex-1 p-4 space-y-4" id="container-cardapio">
            <div class="text-center py-24 text-slate-500 text-xs flex flex-col items-center gap-2">
                <i class="fa-solid fa-spinner fa-spin text-xl text-brand-500"></i>
                <span>Carregando cardápio...</span>
            </div>
        </main>

    </div>

    <!-- Botão Flutuante da Sacola -->
    <div class="fixed bottom-5 left-0 right-0 px-4 z-50 flex justify-center pointer-events-none">
        <button id="btn-flutuante" class="w-full max-w-md bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white font-bold p-4 rounded-2xl shadow-2xl shadow-purple-950/90 pointer-events-auto hidden justify-between items-center transition-all duration-200 active:scale-95" onclick="abrirModal('gaveta-carrinho')">
            <div class="flex items-center gap-2.5">
                <span id="flutuante-qtd" class="bg-black/30 text-white font-bold text-xs px-2.5 py-1 rounded-xl">0 itens</span>
                <span class="text-sm font-bold flex items-center gap-1.5"><i class="fa-solid fa-bag-shopping"></i> Ver Sacola</span>
            </div>
            <span id="flutuante-total" class="text-base font-extrabold text-white">R$ 0,00</span>
        </button>
    </div>

    <!-- Overlay Global -->
    <div id="modal-overlay" class="sheet-backdrop" onclick="fecharTodosModais()"></div>

    <!-- Modal 1: Montador de Pizza -->
    <div id="gaveta-pizza" class="sheet-content">
        <div class="sheet-handle"></div>
        
        <div class="p-4 border-b border-white/5 flex justify-between items-center">
            <div>
                <h3 id="pizza-modal-titulo" class="text-base font-bold text-white">Monte sua Pizza 🍕</h3>
                <p id="pizza-modal-limite" class="text-xs text-brand-500 font-medium">Escolha até 2 sabores</p>
            </div>
            <button onclick="fecharTodosModais()" class="w-8 h-8 rounded-full bg-white/5 text-slate-400 hover:text-white flex items-center justify-center text-sm transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-4 overflow-y-auto flex-1 space-y-4">
            <div class="p-3 bg-brand-900/30 border border-brand-500/20 rounded-xl text-xs text-brand-100 flex items-center gap-2">
                <i class="fa-solid fa-info-circle text-brand-500 text-sm"></i>
                <span>O valor final é cobrado pelo <strong>sabor de maior valor</strong> escolhido.</span>
            </div>

            <div id="container-sabores-pizza" class="space-y-4"></div>

            <div class="p-4 bg-dark-card border border-dark-border rounded-2xl space-y-3" id="bloco-borda-container">
                <div class="flex justify-between items-center">
                    <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider">Borda Recheada (Opcional)</h4>
                    <span id="badge-borda-inclusa" class="hidden text-[10px] bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-bold px-2 py-0.5 rounded-md">
                        Inclusa na Pizza Drilavy
                    </span>
                </div>

                <div class="space-y-2" id="opcoes-borda-radios">
                    <label class="flex justify-between items-center py-2 border-b border-white/5 text-xs cursor-pointer item-radio-borda">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="borda_pizza" value="Sem borda recheada" data-preco="0" checked onchange="calcularTotalPizza()" class="accent-brand-500">
                            <span class="text-slate-300">Sem borda recheada</span>
                        </div>
                        <span class="text-slate-500">Grátis</span>
                    </label>
                    <label class="flex justify-between items-center py-2 border-b border-white/5 text-xs cursor-pointer item-radio-borda">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="borda_pizza" value="Borda de Catupiry" data-preco="8.00" onchange="calcularTotalPizza()" class="accent-brand-500">
                            <span class="text-slate-300">Borda de Catupiry</span>
                        </div>
                        <strong class="text-brand-500">+ R$ 8,00</strong>
                    </label>
                    <label class="flex justify-between items-center py-2 text-xs cursor-pointer item-radio-borda">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="borda_pizza" value="Borda de Cheddar" data-preco="8.00" onchange="calcularTotalPizza()" class="accent-brand-500">
                            <span class="text-slate-300">Borda de Cheddar</span>
                        </div>
                        <strong class="text-brand-500">+ R$ 8,00</strong>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Alguma observação?</label>
                <textarea id="obs-pizza" placeholder="Ex: Massa fininha, caprichar no orégano..." rows="2" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white placeholder-slate-500 outline-none focus:border-brand-500 transition"></textarea>
            </div>
        </div>

        <div class="p-4 border-t border-white/5 bg-dark-surface">
            <button type="button" class="w-full bg-gradient-to-r from-brand-600 to-fuchsia-600 text-white font-extrabold p-3.5 rounded-xl text-sm flex justify-center items-center gap-2 transition active:scale-95" onclick="confirmarAdicaoPizza()">
                Adicionar • <span id="total-pizza-customizada">R$ 0,00</span>
            </button>
        </div>
    </div>

    <!-- Modal 2: Adicionais Lanche -->
    <div id="gaveta-adicionais" class="sheet-content">
        <div class="sheet-handle"></div>
        
        <div class="p-4 border-b border-white/5 flex justify-between items-center">
            <div>
                <h3 id="modal-item-nome" class="text-base font-bold text-white">Personalizar Item</h3>
                <p id="modal-item-preco-base" class="text-xs text-brand-500 font-medium">R$ 0,00</p>
            </div>
            <button onclick="fecharTodosModais()" class="w-8 h-8 rounded-full bg-white/5 text-slate-400 hover:text-white flex items-center justify-center text-sm transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-4 overflow-y-auto flex-1 space-y-4">
            <div id="container-grupos-adicionais" class="space-y-4"></div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Alguma observação?</label>
                <textarea id="obs-item-customizado" placeholder="Ex: Sem salada, molho à parte..." rows="2" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white placeholder-slate-500 outline-none focus:border-brand-500 transition"></textarea>
            </div>
        </div>

        <div class="p-4 border-t border-white/5 bg-dark-surface">
            <button type="button" class="w-full bg-gradient-to-r from-brand-600 to-fuchsia-600 text-white font-extrabold p-3.5 rounded-xl text-sm flex justify-center items-center gap-2 transition active:scale-95" onclick="confirmarAdicaoItem()">
                Adicionar • <span id="total-item-customizado">R$ 0,00</span>
            </button>
        </div>
    </div>

    <!-- Modal 3: Sacola / Finalização -->
    <div id="gaveta-carrinho" class="sheet-content">
        <div class="sheet-handle"></div>
        
        <div class="p-4 border-b border-white/5 flex justify-between items-center">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-bag-shopping text-brand-500"></i> Sua Sacola
            </h3>
            <button onclick="fecharTodosModais()" class="w-8 h-8 rounded-full bg-white/5 text-slate-400 hover:text-white flex items-center justify-center text-sm transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-4 overflow-y-auto flex-1 space-y-4">
            <div id="lista-carrinho" class="divide-y divide-white/5 space-y-2"></div>

            <form id="form-pedido" onsubmit="finalizarPedido(event)" class="space-y-3 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Seu Nome *</label>
                    <input type="text" id="cli-nome" required placeholder="Como podemos te chamar?" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Seu WhatsApp *</label>
                    <input type="tel" id="cli-whatsapp" required placeholder="DDD + Número (ex: 75988887777)" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Tipo de Entrega</label>
                    <select id="tipo-entrega" onchange="atualizarTotais()" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500 font-medium">
                        <option value="delivery">🛵 Delivery (+ R$ <?= number_format($estab['taxa_entrega_padrao'], 2, ',', '.') ?>)</option>
                        <option value="retirada">🏪 Retirada no Balcão (Grátis)</option>
                    </select>
                </div>

                <div id="bloco-endereco" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Rua e Número *</label>
                        <input type="text" id="cli-endereco" placeholder="Ex: Rua Barão do Rio Branco, 450" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Bairro *</label>
                            <input type="text" id="cli-bairro" placeholder="Ex: Centro" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Complemento</label>
                            <input type="text" id="cli-complemento" placeholder="Apt, Casa..." class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Forma de Pagamento *</label>
                    <select id="forma-pagamento" onchange="alternarTroco()" required class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500 font-medium">
                        <option value="pix">PIX</option>
                        <option value="cartao_credito">Cartão de Crédito</option>
                        <option value="cartao_debito">Cartão de Débito</option>
                        <option value="dinheiro">Dinheiro</option>
                    </select>
                </div>

                <div id="bloco-troco" class="hidden">
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Troco para quanto?</label>
                    <input type="number" step="0.01" id="troco-para" placeholder="Ex: 50.00" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white outline-none focus:border-brand-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Observações Gerais</label>
                    <textarea id="obs-pedido" placeholder="Ex: Tocar a campainha..." rows="2" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-xs text-white placeholder-slate-500 outline-none focus:border-brand-500"></textarea>
                </div>
            </form>
        </div>

        <div class="p-4 border-t border-white/5 bg-dark-surface space-y-3">
            <div class="space-y-1 text-xs text-slate-400">
                <div class="flex justify-between">
                    <span>Subtotal:</span>
                    <span id="modal-subtotal" class="font-semibold text-slate-200">R$ 0,00</span>
                </div>
                <div id="linha-taxa" class="flex justify-between">
                    <span>Taxa de Entrega:</span>
                    <span id="modal-taxa" class="font-semibold text-emerald-400">+ R$ <?= number_format($estab['taxa_entrega_padrao'], 2, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-sm font-extrabold text-white pt-1.5 border-t border-white/5">
                    <span>Total:</span>
                    <span id="modal-total" class="text-brand-500 font-black">R$ 0,00</span>
                </div>
            </div>

            <button type="button" onclick="document.getElementById('form-pedido').requestSubmit()" id="btn-submit" class="w-full bg-gradient-to-r from-brand-600 to-fuchsia-600 text-white font-extrabold p-3.5 rounded-xl text-sm flex justify-center items-center gap-2 transition active:scale-95 shadow-xl shadow-purple-950/60">
                <i class="fa-solid fa-check"></i> Concluir Pedido
            </button>
        </div>
    </div>

    <!-- Script com Sincronização em Tempo Real -->
    <script>
        const estabId = <?= $estab['id'] ?>;
        const taxaPadraoEstab = <?= (float)$estab['taxa_entrega_padrao'] ?>;
        
        let lojaEstaAberta = <?= $lojaAbertaInicial ? 'true' : 'false' ?>;

        let cardapioCompleto = [];
        let pizzaSabores = [];
        let produtosCatalogo = [];
        let categoriaAtivaId = null;
        let carrinho = [];

        let pizzaEmMontagem = null;
        let produtoSendoCustomizado = null;

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500
        });

        function obterEmojiCategoria(nome) {
            const n = nome.toLowerCase();
            if (n.includes('sanduíche') || n.includes('sanduiche') || n.includes('tradicionais')) return '🥪';
            if (n.includes('hambúrguer') || n.includes('hamburguer') || n.includes('artesanais')) return '🍔';
            if (n.includes('sal') || n.includes('sanduba')) return '🥖';
            if (n.includes('combo') || n.includes('porç') || n.includes('porc')) return '🍟';
            if (n.includes('pizza')) return '🍕';
            if (n.includes('bebida') || n.includes('refri')) return '🥤';
            if (n.includes('sobremesa') || n.includes('doce')) return '🍰';
            return '🍽️';
        }

        function aplicarStatusVisual(aberta) {
            lojaEstaAberta = aberta;

            const badge = document.getElementById('badge-status');
            const txt = document.getElementById('texto-status-loja');
            const banner = document.getElementById('banner-fechado');
            if (!badge || !txt) return;

            const dot = badge.querySelector('span:first-child');

            if (aberta) {
                badge.className = 'inline-flex items-center gap-1.5 px-4 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                txt.innerText = 'Aberto agora';
                if (dot) dot.className = 'w-2 h-2 rounded-full bg-emerald-400 animate-pulse';
                if (banner) banner.classList.add('hidden');
            } else {
                badge.className = 'inline-flex items-center gap-1.5 px-4 py-1 rounded-full text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20';
                txt.innerText = 'Fechado no momento';
                if (dot) dot.className = 'w-2 h-2 rounded-full bg-red-500';
                if (banner) banner.classList.remove('hidden');
            }
        }

        async function checarStatusLojaEmTempoReal() {
            try {
                const res = await fetch(`api/status_loja.php?estab=${estabId}&_t=${Date.now()}`);
                const data = await res.json();
                if (data && data.sucesso) {
                    const statusAberto = (data.aberto === true || data.status === 'aberto' || data.status === 1 || data.status === '1');
                    aplicarStatusVisual(statusAberto);
                }
            } catch (err) {}
        }

        function avisoLojaFechada() {
            Swal.fire({
                title: 'Estamos Fechados no Momento 🌙',
                html: 'Nosso horário padrão é das <b>18:00 às 22:30</b>.<br>Aguardamos você para fazer o seu pedido assim que abrirmos!',
                icon: 'info',
                confirmButtonColor: '#9333ea',
                confirmButtonText: 'Entendido'
            });
        }

        function abrirModal(id) {
            fecharTodosModais();
            document.getElementById('modal-overlay').classList.add('active');
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function fecharTodosModais() {
            document.getElementById('modal-overlay').classList.remove('active');
            document.querySelectorAll('.sheet-content').forEach(m => m.classList.remove('active'));
            document.body.style.overflow = '';
        }

        async function carregarCardapio() {
            try {
                const res = await fetch(`api/produtos.php?estab=${estabId}&t=${Date.now()}`);
                const data = await res.json();

                if (!data.sucesso) return;

                cardapioCompleto = data.cardapio;
                pizzaSabores = data.pizza_sabores || [];

                produtosCatalogo = [];
                cardapioCompleto.forEach(cat => {
                    cat.produtos.forEach(p => produtosCatalogo.push(p));
                });

                if (cardapioCompleto.length > 0) {
                    categoriaAtivaId = cardapioCompleto[0].categoria_id;
                }

                renderizarCategorias();
                renderizarProdutos();
            } catch (err) {
                console.error(err);
            }
        }

        function renderizarCategorias() {
            const nav = document.getElementById('nav-categorias');
            nav.innerHTML = '';

            cardapioCompleto.forEach(cat => {
                const btn = document.createElement('button');
                btn.type = 'button';
                const ativa = cat.categoria_id === categoriaAtivaId;
                const emoji = obterEmojiCategoria(cat.categoria_nome);
                const nomeLimpo = cat.categoria_nome.replace(/[\u{1F300}-\u{1FAFF}]/gu, '').trim();

                btn.className = `px-3.5 py-2 rounded-xl text-xs font-bold transition-all duration-200 cursor-pointer flex items-center gap-1.5 ${
                    ativa 
                        ? 'bg-gradient-to-r from-brand-600 to-fuchsia-600 text-white shadow-lg shadow-purple-950/70 scale-105' 
                        : 'bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white border border-white/5'
                }`;
                btn.innerHTML = `<span>${emoji}</span> <span>${nomeLimpo}</span>`;
                btn.onclick = () => {
                    categoriaAtivaId = cat.categoria_id;
                    renderizarCategorias();
                    renderizarProdutos();
                };
                nav.appendChild(btn);
            });
        }

        function renderizarProdutos() {
            const main = document.getElementById('container-cardapio');
            main.innerHTML = '';

            const categoriaAtual = cardapioCompleto.find(c => c.categoria_id === categoriaAtivaId);
            if (!categoriaAtual) return;

            const ehPizza = categoriaAtual.categoria_nome.toLowerCase().includes('pizza');

            let html = '';
            categoriaAtual.produtos.forEach(p => {
                const preco = parseFloat(p.preco).toFixed(2).replace('.', ',');
                const precoTexto = ehPizza ? `A partir de R$ ${preco}` : `R$ ${preco}`;
                const botaoTexto = ehPizza ? `🍕 Montar` : `+ Adicionar`;

                html += `
                    <div class="p-4 bg-dark-card border border-dark-border rounded-2xl flex justify-between items-center gap-3.5 hover:border-brand-500/40 transition cursor-pointer group" onclick="clicouNoProduto(${p.id})">
                        <div class="flex-1 pr-1">
                            <h3 class="text-sm font-bold text-white group-hover:text-brand-500 transition">${p.nome}</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-snug line-clamp-2">${p.descricao || ''}</p>
                            <span class="inline-block mt-2 font-black text-sm text-brand-500">${precoTexto}</span>
                        </div>
                        <button type="button" class="bg-brand-600/15 hover:bg-brand-600 text-brand-500 hover:text-white border border-brand-500/30 text-xs font-bold px-3 py-2 rounded-xl transition flex-shrink-0">
                            ${botaoTexto}
                        </button>
                    </div>
                `;
            });

            const emojiCat = obterEmojiCategoria(categoriaAtual.categoria_nome);
            const nomeCatLimpo = categoriaAtual.categoria_nome.replace(/[\u{1F300}-\u{1FAFF}]/gu, '').trim();

            main.innerHTML = `
                <div class="flex justify-between items-center pb-2 border-b border-white/5">
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                        <span>${emojiCat}</span> <span>${nomeCatLimpo}</span>
                    </h2>
                    <span class="text-[11px] font-bold text-brand-500 bg-brand-900/40 px-2.5 py-0.5 rounded-full border border-brand-500/20">${categoriaAtual.produtos.length} opções</span>
                </div>
                <div class="space-y-3 pt-1">${html}</div>
            `;
        }

        function clicouNoProduto(id) {
            if (!lojaEstaAberta) {
                avisoLojaFechada();
                return;
            }

            const prod = produtosCatalogo.find(p => p.id == id);
            if (!prod) return;

            if (prod.categoria_id == 5 || prod.nome.toLowerCase().includes('pizza')) {
                abrirMontadorPizza(prod);
            } else if (prod.grupos_adicionais && prod.grupos_adicionais.length > 0) {
                abrirModalCustomizacao(prod);
            } else {
                adicionarItemDireto(prod);
            }
        }

        function abrirMontadorPizza(prod) {
            let maxSabores = 2;
            let campoTamanho = 'preco_m';

            if (prod.nome.includes('Família') || prod.nome.includes('Familia')) {
                maxSabores = 3;
                campoTamanho = 'preco_f';
            } else if (prod.nome.includes('Grande')) {
                maxSabores = 2;
                campoTamanho = 'preco_g';
            }

            pizzaEmMontagem = {
                id: prod.id,
                nome: prod.nome,
                maxSabores: maxSabores,
                campoTamanho: campoTamanho,
                precoBase: parseFloat(prod.preco)
            };

            document.getElementById('pizza-modal-titulo').innerText = `${prod.nome} 🍕`;
            document.getElementById('pizza-modal-limite').innerText = `Selecione até ${maxSabores} sabores`;
            document.getElementById('obs-pizza').value = '';

            const container = document.getElementById('container-sabores-pizza');
            container.innerHTML = '';

            const tipos = [
                { key: 'tradicional', nome: 'Sabores Tradicionais 🧀' },
                { key: 'especial', nome: 'Sabores Especiais ⭐' },
                { key: 'premium', nome: 'Sabores Premium 👑' }
            ];

            tipos.forEach(tipo => {
                const sabores = pizzaSabores.filter(s => s.categoria_sabor === tipo.key);
                if (sabores.length === 0) return;

                let itensHtml = '';
                sabores.forEach(s => {
                    const preco = parseFloat(s[campoTamanho]).toFixed(2).replace('.', ',');
                    itensHtml += `
                        <label class="flex justify-between items-center py-2.5 border-b border-white/5 last:border-none text-xs cursor-pointer">
                            <div class="pr-2 flex items-start gap-2.5">
                                <input type="checkbox" name="sabores_pizza" value="${s.id}" data-nome="${s.nome}" data-preco="${s[campoTamanho]}" onchange="validarLimiteSabores(this)" class="mt-0.5 accent-brand-500">
                                <div>
                                    <strong class="text-white">${s.nome}</strong>
                                    <div class="text-[11px] text-slate-400 mt-0.5 leading-tight">${s.ingredientes}</div>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-brand-500 whitespace-nowrap">R$ ${preco}</span>
                        </label>
                    `;
                });

                container.innerHTML += `
                    <div class="p-3.5 bg-dark-card border border-dark-border rounded-2xl space-y-2">
                        <div class="text-xs font-bold text-slate-200 uppercase tracking-wider">${tipo.nome}</div>
                        <div>${itensHtml}</div>
                    </div>
                `;
            });

            const radios = document.querySelectorAll('input[name="borda_pizza"]');
            radios[0].checked = true;
            radios.forEach(r => r.disabled = false);
            document.getElementById('badge-borda-inclusa').classList.add('hidden');
            document.querySelectorAll('.item-radio-borda').forEach(el => el.classList.remove('opacity-40', 'pointer-events-none'));

            calcularTotalPizza();
            abrirModal('gaveta-pizza');
        }

        function validarLimiteSabores(input) {
            const marcados = document.querySelectorAll('input[name="sabores_pizza"]:checked');
            if (marcados.length > pizzaEmMontagem.maxSabores) {
                input.checked = false;
                Toast.fire({ icon: 'warning', title: `Máximo de ${pizzaEmMontagem.maxSabores} sabores!` });
            }
            calcularTotalPizza();
        }

        function calcularTotalPizza() {
            const marcados = Array.from(document.querySelectorAll('input[name="sabores_pizza"]:checked'));
            let maiorPreco = pizzaEmMontagem.precoBase;

            const temSaborDrilavy = marcados.some(i => i.dataset.nome.toLowerCase().includes('drilavy'));
            const badgeInclusa = document.getElementById('badge-borda-inclusa');
            const radiosBorda = document.querySelectorAll('input[name="borda_pizza"]');
            const labelsBorda = document.querySelectorAll('.item-radio-borda');

            let precoBorda = 0;

            if (temSaborDrilavy) {
                badgeInclusa.classList.remove('hidden');
                radiosBorda.forEach(r => {
                    r.disabled = true;
                    if (r.value === 'Borda de Catupiry') r.checked = true;
                });
                labelsBorda.forEach(el => el.classList.add('opacity-40', 'pointer-events-none'));
                precoBorda = 0;
            } else {
                badgeInclusa.classList.add('hidden');
                radiosBorda.forEach(r => r.disabled = false);
                labelsBorda.forEach(el => el.classList.remove('opacity-40', 'pointer-events-none'));

                const bordaSelecionada = document.querySelector('input[name="borda_pizza"]:checked');
                precoBorda = bordaSelecionada ? parseFloat(bordaSelecionada.dataset.preco || 0) : 0;
            }

            if (marcados.length > 0) {
                const precos = marcados.map(i => parseFloat(i.dataset.preco));
                maiorPreco = Math.max(...precos);
            }

            const total = maiorPreco + precoBorda;
            document.getElementById('total-pizza-customizada').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
        }

        function confirmarAdicaoPizza() {
            const marcados = Array.from(document.querySelectorAll('input[name="sabores_pizza"]:checked'));
            if (marcados.length === 0) {
                Swal.fire('Escolha o sabor', 'Selecione ao menos 1 sabor para a pizza.', 'warning');
                return;
            }

            const precos = marcados.map(i => parseFloat(i.dataset.preco));
            const maiorPreco = Math.max(...precos);

            const temSaborDrilavy = marcados.some(i => i.dataset.nome.toLowerCase().includes('drilavy'));
            let precoBorda = 0;
            let textoBorda = '';

            if (temSaborDrilavy) {
                textoBorda = 'Borda de Catupiry (Inclusa)';
                precoBorda = 0;
            } else {
                const borda = document.querySelector('input[name="borda_pizza"]:checked');
                precoBorda = borda ? parseFloat(borda.dataset.preco || 0) : 0;
                if (borda && precoBorda > 0) {
                    textoBorda = `${borda.value} (+R$ ${precoBorda.toFixed(2).replace('.', ',')})`;
                }
            }

            const precoTotal = maiorPreco + precoBorda;
            const nomes = marcados.map(i => i.dataset.nome);
            let texto = nomes.length === 1 ? `1/1 Inteira: ${nomes[0]}` : (nomes.length === 2 ? `1/2 ${nomes[0]} + 1/2 ${nomes[1]}` : `1/3 ${nomes[0]} + 1/3 ${nomes[1]} + 1/3 ${nomes[2]}`);

            if (textoBorda) {
                texto += ` | ${textoBorda}`;
            }

            carrinho.push({
                id: parseInt(pizzaEmMontagem.id),
                nome: pizzaEmMontagem.nome,
                preco_base: maiorPreco,
                preco_total_unitario: precoTotal,
                qtd: 1,
                adicionais_texto: texto,
                obs_item: document.getElementById('obs-pizza').value
            });

            fecharTodosModais();
            atualizarInterface();
            Toast.fire({ icon: 'success', title: 'Pizza adicionada!' });
        }

        function adicionarItemDireto(prod) {
            carrinho.push({
                id: parseInt(prod.id),
                nome: prod.nome,
                preco_base: parseFloat(prod.preco),
                preco_total_unitario: parseFloat(prod.preco),
                qtd: 1,
                adicionais_texto: '',
                obs_item: ''
            });
            atualizarInterface();
            Toast.fire({ icon: 'success', title: `${prod.nome} adicionado!` });
        }

        function abrirModalCustomizacao(prod) {
            produtoSendoCustomizado = prod;
            document.getElementById('modal-item-nome').innerText = prod.nome;
            document.getElementById('modal-item-preco-base').innerText = `R$ ${parseFloat(prod.preco).toFixed(2).replace('.', ',')}`;
            document.getElementById('obs-item-customizado').value = '';

            const container = document.getElementById('container-grupos-adicionais');
            container.innerHTML = '';

            prod.grupos_adicionais.forEach(grp => {
                const tipoInput = grp.maximo === 1 ? 'radio' : 'checkbox';
                let itensHtml = '';

                grp.itens.forEach((ad, index) => {
                    const precoTxt = parseFloat(ad.preco) > 0 ? `+ R$ ${parseFloat(ad.preco).toFixed(2).replace('.', ',')}` : 'Grátis';
                    const checked = (grp.obrigatorio && index === 0 && tipoInput === 'radio') ? 'checked' : '';

                    itensHtml += `
                        <label class="flex justify-between items-center py-2 border-b border-white/5 last:border-none text-xs cursor-pointer">
                            <div class="flex items-center gap-2">
                                <input type="${tipoInput}" name="grupo_${grp.id}" value="${ad.id}" data-nome="${ad.nome}" data-preco="${ad.preco}" ${checked} onchange="calcularTotalCustomizacao()" class="accent-brand-500">
                                <span class="text-slate-200">${ad.nome}</span>
                            </div>
                            <strong class="text-brand-500">${precoTxt}</strong>
                        </label>
                    `;
                });

                container.innerHTML += `
                    <div class="p-4 bg-dark-card border border-dark-border rounded-2xl space-y-2">
                        <div class="text-xs font-bold text-white flex justify-between items-center">
                            <span>${grp.nome}</span>
                            ${grp.obrigatorio ? `<span class="bg-brand-600 text-white text-[10px] px-2 py-0.5 rounded font-bold">Obrigatório</span>` : `<span class="text-slate-400 text-[10px]">Opcional</span>`}
                        </div>
                        <div>${itensHtml}</div>
                    </div>
                `;
            });

            calcularTotalCustomizacao();
            abrirModal('gaveta-adicionais');
        }

        function calcularTotalCustomizacao() {
            let total = parseFloat(produtoSendoCustomizado.preco);
            document.querySelectorAll('#container-grupos-adicionais input:checked').forEach(i => {
                total += parseFloat(i.dataset.preco || 0);
            });
            document.getElementById('total-item-customizado').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
        }

        function confirmarAdicaoItem() {
            let totalUnitario = parseFloat(produtoSendoCustomizado.preco);
            let textos = [];

            document.querySelectorAll('#container-grupos-adicionais input:checked').forEach(i => {
                const preco = parseFloat(i.dataset.preco);
                totalUnitario += preco;
                textos.push(`${i.dataset.nome}${preco > 0 ? ` (+R$ ${preco.toFixed(2).replace('.', ',')})` : ''}`);
            });

            carrinho.push({
                id: parseInt(produtoSendoCustomizado.id),
                nome: produtoSendoCustomizado.nome,
                preco_base: parseFloat(produtoSendoCustomizado.preco),
                preco_total_unitario: totalUnitario,
                qtd: 1,
                adicionais_texto: textos.join(', '),
                obs_item: document.getElementById('obs-item-customizado').value
            });

            fecharTodosModais();
            atualizarInterface();
            Toast.fire({ icon: 'success', title: `${produtoSendoCustomizado.nome} adicionado!` });
        }

        function removerItem(index) {
            carrinho.splice(index, 1);
            atualizarInterface();
            if (carrinho.length > 0) abrirModal('gaveta-carrinho');
        }

        function alternarTroco() {
            const forma = document.getElementById('forma-pagamento').value;
            document.getElementById('bloco-troco').classList.toggle('hidden', forma !== 'dinheiro');
        }

        function atualizarInterface() {
            const totalQtd = carrinho.reduce((acc, c) => acc + c.qtd, 0);
            const subtotal = carrinho.reduce((acc, c) => acc + (c.preco_total_unitario * c.qtd), 0);
            const btn = document.getElementById('btn-flutuante');

            if (totalQtd > 0) {
                btn.classList.remove('hidden');
                btn.classList.add('flex');
                document.getElementById('flutuante-qtd').innerText = `${totalQtd} ${totalQtd === 1 ? 'item' : 'itens'}`;
                document.getElementById('flutuante-total').innerText = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
            } else {
                btn.classList.add('hidden');
                btn.classList.remove('flex');
                fecharTodosModais();
            }

            atualizarTotais();
        }

        function atualizarTotais() {
            const tipo = document.getElementById('tipo-entrega').value;
            const subtotal = carrinho.reduce((acc, c) => acc + (c.preco_total_unitario * c.qtd), 0);
            const taxa = (tipo === 'delivery') ? taxaPadraoEstab : 0;

            document.getElementById('bloco-endereco').classList.toggle('hidden', tipo !== 'delivery');
            
            const linhaTaxa = document.getElementById('linha-taxa');
            if (tipo === 'delivery') {
                linhaTaxa.classList.remove('hidden');
                linhaTaxa.classList.add('flex');
                document.getElementById('modal-taxa').innerText = `+ R$ ${taxa.toFixed(2).replace('.', ',')}`;
            } else {
                linhaTaxa.classList.add('hidden');
                linhaTaxa.classList.remove('flex');
            }

            const total = subtotal + taxa;
            document.getElementById('modal-subtotal').innerText = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
            document.getElementById('modal-total').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;

            const lista = document.getElementById('lista-carrinho');
            lista.innerHTML = '';
            carrinho.forEach((item, index) => {
                lista.innerHTML += `
                    <div class="py-2.5 flex justify-between items-center text-xs">
                        <div class="flex-1 pr-2">
                            <strong class="text-white">${item.qtd}x ${item.nome}</strong>
                            ${item.adicionais_texto ? `<div class="text-[11px] text-brand-500 mt-0.5">${item.adicionais_texto}</div>` : ''}
                            ${item.obs_item ? `<div class="text-[11px] text-amber-400 mt-0.5">📝 ${item.obs_item}</div>` : ''}
                        </div>
                        <div class="flex items-center gap-3">
                            <strong class="text-white font-bold">R$ ${(item.preco_total_unitario * item.qtd).toFixed(2).replace('.', ',')}</strong>
                            <button type="button" onclick="removerItem(${index})" class="text-red-400 hover:text-red-300 p-1"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </div>
                `;
            });
        }

        async function finalizarPedido(e) {
            e.preventDefault();

            if (!lojaEstaAberta) {
                avisoLojaFechada();
                return;
            }

            const btn = document.getElementById('btn-submit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Registrando...';

            const tipoEntrega = document.getElementById('tipo-entrega').value;
            const subtotal = carrinho.reduce((acc, c) => acc + (c.preco_total_unitario * c.qtd), 0);
            const taxa = (tipoEntrega === 'delivery') ? taxaPadraoEstab : 0;
            const total = subtotal + taxa;

            const payload = {
                estabelecimento_id: estabId,
                cliente: {
                    nome: document.getElementById('cli-nome').value,
                    whatsapp: document.getElementById('cli-whatsapp').value,
                    endereco: tipoEntrega === 'delivery' ? document.getElementById('cli-endereco').value : 'Retirada no Balcão',
                    bairro: tipoEntrega === 'delivery' ? document.getElementById('cli-bairro').value : '',
                    complemento: tipoEntrega === 'delivery' ? document.getElementById('cli-complemento').value : ''
                },
                tipo_entrega: tipoEntrega,
                taxa_entrega: taxa,
                subtotal: subtotal,
                total: total,
                forma_pagamento: document.getElementById('forma-pagamento').value,
                troco_para: document.getElementById('troco-para').value || null,
                observacoes: document.getElementById('obs-pedido').value,
                itens: carrinho
            };

            try {
                const res = await fetch('api/criar_pedido.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const resposta = await res.json();

                if (resposta.sucesso) {
                    fecharTodosModais();
                    carrinho = [];
                    atualizarInterface();

                    Swal.fire({
                        title: 'Pedido Confirmado! 🎉',
                        html: `Seu pedido foi recebido com sucesso e <b>já está sendo preparado</b>!`,
                        icon: 'success',
                        confirmButtonColor: '#9333ea',
                        confirmButtonText: 'Acompanhar Status'
                    }).then(() => {
                        window.location.href = `acompanhar.php?id=${resposta.pedido_id}`;
                    });
                } else {
                    Swal.fire('Atenção', resposta.erro, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Concluir Pedido';
                }
            } catch (err) {
                Swal.fire('Erro', 'Erro ao conectar ao servidor.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Concluir Pedido';
            }
        }

        // Inicialização
        carregarCardapio();
        checarStatusLojaEmTempoReal();
        setInterval(checarStatusLojaEmTempoReal, 3000);
    </script>
</body>
</html>