<?php
// admin/cardapio.php — Gestão de cardápio (produtos): criar, editar, excluir,
// marcar disponível/indisponível, ajustar preço e definir destaque do dia/semana.
require_once __DIR__ . '/auth.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

$stmtEstab = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id LIMIT 1");
$stmtEstab->execute([':id' => $estabId]);
$estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
$nomeEstab = $estab['nome'] ?? 'Drilavy Lanchonete e Pizzaria';

$stmtCat = $pdo->prepare("SELECT id, nome FROM categorias WHERE estabelecimento_id = :estab ORDER BY ordem ASC, id ASC");
$stmtCat->execute([':estab' => $estabId]);
$categoriasIniciais = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

$stmtProd = $pdo->prepare("SELECT * FROM produtos WHERE estabelecimento_id = :estab ORDER BY categoria_id ASC, nome ASC");
$stmtProd->execute([':estab' => $estabId]);
$produtosIniciais = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestão de Cardápio - <?= htmlspecialchars($nomeEstab) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
        if (file_exists(__DIR__ . '/../includes/tema.php')) {
            require_once __DIR__ . '/../includes/tema.php';
            if (function_exists('tema_imprimirTailwindConfig')) {
                tema_imprimirTailwindConfig($estab);
            }
        }
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        .switch-toggle { appearance: none; -webkit-appearance: none; width: 38px; height: 22px; border-radius: 999px; background: rgba(255,255,255,0.12); position: relative; cursor: pointer; transition: .2s; flex-shrink: 0; }
        .switch-toggle:checked { background: #16a34a; }
        .switch-toggle::before { content: ''; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; top: 2px; left: 2px; transition: .2s; }
        .switch-toggle:checked::before { left: 18px; }
        .categoria-header { position: sticky; top: 57px; z-index: 20; }
        .aba-categoria { scroll-snap-align: start; }
        .aba-categoria.ativa { background: linear-gradient(90deg, #9333ea, #c026d3); color: #fff; border-color: transparent; }
        .categoria-conteudo { display: grid; grid-template-rows: 1fr; transition: grid-template-rows .25s ease; }
        .categoria-conteudo.recolhido { grid-template-rows: 0fr; }
        .categoria-conteudo > div { overflow: hidden; }
        .chevron-categoria { transition: transform .2s; }
        .chevron-categoria.recolhido { transform: rotate(-90deg); }
    </style>
</head>
<body class="min-h-screen bg-dark-base text-slate-100 flex flex-col antialiased">

    <!-- Header Superior -->
    <header class="sticky top-0 z-40 bg-dark-surface/90 backdrop-blur-md border-b border-dark-border px-4 sm:px-6 py-3">
        <div class="max-w-5xl mx-auto flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0">
                <a href="index.php?estab=<?= $estabId ?>" class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-dark-border flex items-center justify-center transition shrink-0">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </a>
                <h1 class="text-sm font-extrabold text-white tracking-tight truncate">
                    Gestão de Cardápio
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <a href="../cardapio.php?estab=<?= $estabId ?>" target="_blank" class="px-3 py-2 rounded-xl text-xs font-bold bg-white/5 hover:bg-white/10 text-slate-300 border border-dark-border transition flex items-center gap-1.5 shrink-0">
                    <i class="fa-solid fa-eye text-xs"></i>
                    <span class="hidden sm:inline">Ver Cardápio</span>
                </a>

                <button onclick="abrirModalProduto()" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white transition active:scale-95 flex items-center gap-1.5 shrink-0 shadow-lg shadow-purple-950/40">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Novo Item</span>
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto p-3 sm:p-6">
        
        <div class="mb-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl p-3 text-xs text-amber-300 flex items-center gap-2.5">
            <i class="fa-solid fa-star text-amber-400 text-sm shrink-0"></i>
            <span>Para lanches semanais (Pastel, Yakisoba, etc.), cadastre na categoria <b>Especiais da Semana</b> ou ative o botão de <b>Especial da Semana</b> no modal.</span>
        </div>

        <div id="busca-container" class="mb-3">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" id="input-busca" oninput="renderizarProdutos()" placeholder="Buscar item pelo nome..." class="w-full pl-9 p-3 bg-dark-surface border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
            </div>
        </div>

        <div id="abas-categorias" class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-3 mb-1 -mx-1 px-1"></div>

        <div id="lista-categorias" class="space-y-4"></div>

        <div id="estado-vazio" class="hidden text-center py-16 text-slate-500">
            <i class="fa-solid fa-box-open text-3xl mb-3"></i>
            <p class="text-xs">Nenhum item encontrado.</p>
        </div>
    </main>

    <!-- Modal Criar/Editar Produto Remodelado -->
    <div id="modal-produto" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-dark-surface border border-dark-border rounded-3xl p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between pb-2 border-b border-white/5">
                <div>
                    <h2 id="modal-titulo" class="text-sm font-extrabold text-white">Novo Item</h2>
                    <p class="text-[11px] text-slate-400">Preencha os dados do produto para o cardápio</p>
                </div>
                <button onclick="fecharModalProduto()" class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <input type="hidden" id="produto-id" value="">

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Nome do item *</label>
                <input type="text" id="produto-nome" placeholder="Ex: Yakisoba Tradicional / Pastel Especial" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Categoria do Cardápio *</label>
                <select id="produto-categoria" onchange="verificarCategoriaEspecial(this.value)" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
                </select>
            </div>

            <!-- Bloco Destacar no Topo -->
            <div class="p-3 bg-amber-500/10 border border-amber-500/25 rounded-xl flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-base">⭐</span>
                    <div>
                        <p class="text-xs font-bold text-amber-300 leading-tight">Especial da Semana</p>
                        <p class="text-[10px] text-amber-200/70 leading-tight">Exibir no banner de destaque no topo</p>
                    </div>
                </div>
                <input type="checkbox" id="produto-destaque" class="switch-toggle">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Descrição / Ingredientes</label>
                <textarea id="produto-descricao" rows="2" placeholder="Ex: Carne, frango, legumes frescos e molho especial..." class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition resize-none"></textarea>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Preço de Venda (R$) *</label>
                <input type="number" id="produto-preco" step="0.01" min="0" placeholder="0.00" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
            </div>

            <button onclick="salvarProduto()" class="w-full bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white font-extrabold p-3.5 rounded-xl text-xs transition active:scale-95 shadow-lg shadow-purple-950/60 flex items-center justify-center gap-2">
                <i class="fa-solid fa-check"></i> Salvar Item
            </button>
        </div>
    </div>

    <script>
        const estabId = <?= $estabId ?>;
        let categorias = <?= json_encode($categoriasIniciais) ?>;
        let produtos = <?= json_encode($produtosIniciais) ?>;

        const formatBRL = (val) => (parseFloat(val) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        async function chamarApi(payload) {
            const res = await fetch(`../api/gerenciar_produto.php?estab=${estabId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            return res.json();
        }

        async function recarregarDados() {
            try {
                const [resCat, resProd] = await Promise.all([
                    fetch(`../api/gerenciar_produto.php?estab=${estabId}&acao=categorias`),
                    fetch(`../api/gerenciar_produto.php?estab=${estabId}&acao=produtos`)
                ]);
                const dadosCat = await resCat.json();
                const dadosProd = await resProd.json();
                if (dadosCat.sucesso) categorias = dadosCat.categorias;
                if (dadosProd.sucesso) produtos = dadosProd.produtos;
                renderizarProdutos();
            } catch (e) {
                console.error('Erro ao recarregar dados:', e);
            }
        }

        let categoriasRecolhidas = new Set();

        function renderizarAbas() {
            const abasContainer = document.getElementById('abas-categorias');
            const contagens = {};
            produtos.forEach(p => { contagens[p.categoria_id] = (contagens[p.categoria_id] || 0) + 1; });

            abasContainer.innerHTML = `
                <button onclick="irParaTopo()" class="aba-categoria shrink-0 px-3 py-1.5 rounded-xl text-[11px] font-bold bg-dark-surface border border-dark-border text-slate-300 hover:bg-white/10 transition">
                    <i class="fa-solid fa-list-ul mr-1"></i> Todas
                </button>
                ${categorias.map(cat => `
                    <button onclick="irParaCategoria(${cat.id})" class="aba-categoria shrink-0 px-3 py-1.5 rounded-xl text-[11px] font-bold bg-dark-surface border border-dark-border text-slate-300 hover:bg-white/10 transition">
                        ${escapeHtml(cat.nome)} <span class="opacity-60">(${contagens[cat.id] || 0})</span>
                    </button>
                `).join('')}
            `;
        }

        function irParaTopo() {
            document.getElementById('busca-container').scrollIntoView({ behavior: 'smooth' });
        }

        function irParaCategoria(catId) {
            categoriasRecolhidas.delete(catId);
            renderizarProdutos();
            requestAnimationFrame(() => {
                const alvo = document.getElementById(`categoria-bloco-${catId}`);
                if (alvo) alvo.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        function toggleCategoriaColapso(catId) {
            if (categoriasRecolhidas.has(catId)) {
                categoriasRecolhidas.delete(catId);
            } else {
                categoriasRecolhidas.add(catId);
            }
            renderizarProdutos();
        }

        function renderizarProdutos() {
            const termo = (document.getElementById('input-busca').value || '').toLowerCase().trim();
            const container = document.getElementById('lista-categorias');
            const estadoVazio = document.getElementById('estado-vazio');
            container.innerHTML = '';

            renderizarAbas();

            let totalVisivel = 0;

            categorias.forEach(cat => {
                const itensDaCat = produtos.filter(p => {
                    const mesmaCategoria = String(p.categoria_id) === String(cat.id);
                    const bateBusca = !termo || p.nome.toLowerCase().includes(termo);
                    return mesmaCategoria && bateBusca;
                });

                if (itensDaCat.length === 0) return;
                totalVisivel += itensDaCat.length;

                const recolhido = categoriasRecolhidas.has(cat.id);
                const disponiveisNaCat = itensDaCat.filter(p => String(p.disponivel) === '1').length;

                const bloco = document.createElement('div');
                bloco.id = `categoria-bloco-${cat.id}`;
                bloco.className = 'bg-dark-surface/40 border border-dark-border rounded-2xl p-2.5 sm:p-3';
                bloco.innerHTML = `
                    <button onclick="toggleCategoriaColapso(${cat.id})" class="categoria-header w-full flex items-center justify-between gap-2 bg-dark-base/95 backdrop-blur-sm rounded-xl px-2.5 py-2 -mt-0.5">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="fa-solid fa-chevron-down chevron-categoria ${recolhido ? 'recolhido' : ''} text-brand-400 text-[10px]"></i>
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-brand-400 truncate">${escapeHtml(cat.nome)}</h3>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 bg-white/5 px-2 py-0.5 rounded-full shrink-0">${disponiveisNaCat}/${itensDaCat.length} ativos</span>
                    </button>
                    <div class="categoria-conteudo ${recolhido ? 'recolhido' : ''}">
                        <div class="space-y-2.5 pt-2.5">
                            ${itensDaCat.map(p => cardProduto(p)).join('')}
                        </div>
                    </div>
                `;
                container.appendChild(bloco);
            });

            estadoVazio.classList.toggle('hidden', totalVisivel > 0);
        }

        function cardProduto(p) {
            const disponivel = String(p.disponivel) === '1';
            const destaque = String(p.destaque_dia || '0') === '1';

            return `
                <div class="bg-dark-surface border ${destaque ? 'border-amber-500/50 shadow-md shadow-amber-950/20' : 'border-dark-border'} rounded-2xl p-3.5 flex items-start gap-3 ${disponivel ? '' : 'opacity-50'}">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="text-xs font-bold text-white truncate">${escapeHtml(p.nome)}</h4>
                            ${destaque ? `
                                <span class="text-[9px] font-extrabold bg-amber-500/20 text-amber-300 border border-amber-500/30 px-1.5 py-0.5 rounded-md flex items-center gap-1">
                                    <i class="fa-solid fa-star text-[8px]"></i> Especial Ativo
                                </span>
                            ` : ''}
                        </div>
                        ${p.descricao ? `<p class="text-[11px] text-slate-400 mt-0.5 line-clamp-2">${escapeHtml(p.descricao)}</p>` : ''}
                        <div class="mt-2 flex items-center gap-2">
                            <span class="text-[11px] text-slate-500">R$</span>
                            <input type="number" step="0.01" min="0" value="${parseFloat(p.preco).toFixed(2)}"
                                onchange="salvarPrecoInline(${p.id}, this.value)"
                                class="w-20 px-2 py-1 bg-dark-card border border-dark-border rounded-lg text-white text-[11px] font-bold outline-none focus:border-brand-500 transition">
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <div class="flex items-center gap-2">
                            <button onclick="toggleDestaque(${p.id}, ${destaque ? 0 : 1})" title="${destaque ? 'Remover do Destaque' : 'Destacar como Especial da Semana'}" class="w-7 h-7 rounded-lg flex items-center justify-center transition border ${destaque ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-white/5 hover:bg-white/10 text-slate-500 hover:text-amber-300 border-white/5'}">
                                <i class="fa-solid fa-star text-[10px]"></i>
                            </button>

                            <input type="checkbox" class="switch-toggle" ${disponivel ? 'checked' : ''} onchange="toggleDisponivel(${p.id}, this.checked)" title="Disponível">
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button onclick='abrirModalProduto(${JSON.stringify(p)})' class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 flex items-center justify-center transition">
                                <i class="fa-solid fa-pen text-[10px]"></i>
                            </button>
                            <button onclick="excluirProduto(${p.id}, '${escapeHtml(p.nome).replace(/'/g, "\\'")}')" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 flex items-center justify-center transition">
                                <i class="fa-solid fa-trash text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        function escapeHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto ?? '';
            return div.innerHTML;
        }

        function preencherSelectCategorias(categoriaSelecionadaId) {
            const select = document.getElementById('produto-categoria');
            select.innerHTML = categorias.map(c => {
                const ehEspecial = c.nome.toLowerCase().includes('especial');
                return `<option value="${c.id}" ${String(c.id) === String(categoriaSelecionadaId) ? 'selected' : ''}>
                    ${ehEspecial ? '⭐ ' : ''}${escapeHtml(c.nome)}
                </option>`;
            }).join('');
        }

        function verificarCategoriaEspecial(catId) {
            const cat = categorias.find(c => String(c.id) === String(catId));
            if (cat && cat.nome.toLowerCase().includes('especial')) {
                document.getElementById('produto-destaque').checked = true;
            }
        }

        function abrirModalProduto(produto) {
            document.getElementById('modal-titulo').textContent = produto ? 'Editar Item' : 'Novo Item';
            document.getElementById('produto-id').value = produto ? produto.id : '';
            document.getElementById('produto-nome').value = produto ? produto.nome : '';
            document.getElementById('produto-descricao').value = produto ? (produto.descricao || '') : '';
            document.getElementById('produto-preco').value = produto ? parseFloat(produto.preco).toFixed(2) : '';
            
            const destaque = produto ? (String(produto.destaque_dia) === '1') : false;
            document.getElementById('produto-destaque').checked = destaque;

            preencherSelectCategorias(produto ? produto.categoria_id : (categorias[0] ? categorias[0].id : ''));
            document.getElementById('modal-produto').classList.remove('hidden');
        }

        function fecharModalProduto() {
            document.getElementById('modal-produto').classList.add('hidden');
        }

        async function salvarProduto() {
            const id = document.getElementById('produto-id').value;
            const nome = document.getElementById('produto-nome').value.trim();
            const categoriaId = document.getElementById('produto-categoria').value;
            const descricao = document.getElementById('produto-descricao').value.trim();
            const preco = parseFloat(document.getElementById('produto-preco').value);
            const destaque_dia = document.getElementById('produto-destaque').checked ? 1 : 0;

            if (!nome || !categoriaId || !preco || preco <= 0) {
                Swal.fire({ icon: 'warning', title: 'Preencha nome, categoria e um preço válido.', background: '#141021', color: '#fff' });
                return;
            }

            const payload = {
                acao: id ? 'editar_produto' : 'criar_produto',
                id: id || undefined,
                nome, categoria_id: categoriaId, descricao, preco, destaque_dia
            };

            const resultado = await chamarApi(payload);
            if (resultado.sucesso) {
                fecharModalProduto();
                await recarregarDados();
                Swal.fire({ icon: 'success', title: resultado.mensagem || 'Item salvo com sucesso!', background: '#141021', color: '#fff', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: resultado.erro || 'Erro ao salvar.', background: '#141021', color: '#fff' });
            }
        }

        async function toggleDestaque(id, novoDestaque) {
            const resultado = await chamarApi({ acao: 'toggle_destaque', id, destaque: novoDestaque });
            if (resultado.sucesso) {
                produtos.forEach(x => {
                    if (novoDestaque === 1) {
                        x.destaque_dia = (String(x.id) === String(id)) ? 1 : 0;
                    } else if (String(x.id) === String(id)) {
                        x.destaque_dia = 0;
                    }
                });
                renderizarProdutos();
                Swal.fire({
                    icon: novoDestaque ? 'success' : 'info',
                    title: novoDestaque ? '⭐ Especial Ativado!' : 'Destaque Removido',
                    text: novoDestaque ? 'Este item agora é o destaque do cardápio.' : 'O cardápio voltou ao padrão normal.',
                    background: '#141021',
                    color: '#fff',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({ icon: 'error', title: resultado.erro || 'Erro ao atualizar destaque.', background: '#141021', color: '#fff' });
            }
        }

        async function toggleDisponivel(id, disponivel) {
            const resultado = await chamarApi({ acao: 'toggle_status', tipo: 'produto', id, disponivel });
            if (resultado.sucesso) {
                const p = produtos.find(x => String(x.id) === String(id));
                if (p) p.disponivel = disponivel ? 1 : 0;
                renderizarProdutos();
            } else {
                Swal.fire({ icon: 'error', title: resultado.erro || 'Erro ao atualizar.', background: '#141021', color: '#fff' });
            }
        }

        async function salvarPrecoInline(id, valor) {
            const preco = parseFloat(valor);
            if (!preco || preco <= 0) {
                Swal.fire({ icon: 'warning', title: 'Preço inválido.', background: '#141021', color: '#fff' });
                renderizarProdutos();
                return;
            }
            const resultado = await chamarApi({ acao: 'salvar_preco', tipo: 'produto', id, preco });
            if (resultado.sucesso) {
                const p = produtos.find(x => String(x.id) === String(id));
                if (p) p.preco = preco;
            } else {
                Swal.fire({ icon: 'error', title: resultado.erro || 'Erro ao atualizar preço.', background: '#141021', color: '#fff' });
                renderizarProdutos();
            }
        }

        async function excluirProduto(id, nome) {
            const confirmacao = await Swal.fire({
                icon: 'warning',
                title: `Excluir "${nome}"?`,
                text: 'Essa ação não pode ser desfeita.',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
                background: '#141021',
                color: '#fff'
            });
            if (!confirmacao.isConfirmed) return;

            const resultado = await chamarApi({ acao: 'excluir_produto', id });
            if (resultado.sucesso) {
                await recarregarDados();
                Swal.fire({ icon: 'success', title: 'Item removido.', background: '#141021', color: '#fff', timer: 1300, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: resultado.erro || 'Erro ao excluir.', background: '#141021', color: '#fff' });
            }
        }

        renderizarProdutos();
    </script>

</body>
</html> 