<?php
// admin/cardapio.php — Gestão de cardápio (produtos): criar, editar, excluir,
// marcar disponível/indisponível e ajustar preço.
require_once __DIR__ . '/auth.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

$stmtEstab = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id LIMIT 1");
$stmtEstab->execute([':id' => $estabId]);
$estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
$nomeEstab = $estab['nome'] ?? 'Drilavy Lanchonete e Pizzaria';

$stmtCat = $pdo->prepare("SELECT id, nome FROM categorias WHERE estabelecimento_id = :estab ORDER BY ordem ASC");
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
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        .switch-toggle { appearance: none; -webkit-appearance: none; width: 38px; height: 22px; border-radius: 999px; background: rgba(255,255,255,0.12); position: relative; cursor: pointer; transition: .2s; flex-shrink: 0; }
        .switch-toggle:checked { background: #16a34a; }
        .switch-toggle::before { content: ''; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; top: 2px; left: 2px; transition: .2s; }
        .switch-toggle:checked::before { left: 18px; }
    </style>
</head>
<body class="min-h-screen bg-dark-base text-slate-100 flex flex-col antialiased">

    <!-- Top Header -->
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

            <button onclick="abrirModalProduto()" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white transition active:scale-95 flex items-center gap-1.5 shrink-0 shadow-lg shadow-purple-950/40">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Novo Item</span>
            </button>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto p-3 sm:p-6">
        <div id="busca-container" class="mb-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" id="input-busca" oninput="renderizarProdutos()" placeholder="Buscar item pelo nome..." class="w-full pl-9 p-3 bg-dark-surface border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
            </div>
        </div>

        <div id="lista-categorias" class="space-y-6"></div>

        <div id="estado-vazio" class="hidden text-center py-16 text-slate-500">
            <i class="fa-solid fa-box-open text-3xl mb-3"></i>
            <p class="text-xs">Nenhum item encontrado.</p>
        </div>
    </main>

    <!-- Modal Criar/Editar Produto -->
    <div id="modal-produto" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-dark-surface border border-dark-border rounded-3xl p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between">
                <h2 id="modal-titulo" class="text-sm font-extrabold text-white">Novo Item</h2>
                <button onclick="fecharModalProduto()" class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <input type="hidden" id="produto-id" value="">

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Nome do item</label>
                <input type="text" id="produto-nome" placeholder="Ex: X-Salada" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Categoria</label>
                <select id="produto-categoria" class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition">
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Descrição</label>
                <textarea id="produto-descricao" rows="3" placeholder="Ingredientes, detalhes do item..." class="w-full p-3 bg-dark-card border border-dark-border rounded-xl text-white text-xs outline-none focus:border-brand-500 transition resize-none"></textarea>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1.5">Preço (R$)</label>
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

        function renderizarProdutos() {
            const termo = (document.getElementById('input-busca').value || '').toLowerCase().trim();
            const container = document.getElementById('lista-categorias');
            const estadoVazio = document.getElementById('estado-vazio');
            container.innerHTML = '';

            let totalVisivel = 0;

            categorias.forEach(cat => {
                const itensDaCat = produtos.filter(p => {
                    const mesmaCategoria = String(p.categoria_id) === String(cat.id);
                    const bateBusca = !termo || p.nome.toLowerCase().includes(termo);
                    return mesmaCategoria && bateBusca;
                });

                if (itensDaCat.length === 0) return;
                totalVisivel += itensDaCat.length;

                const bloco = document.createElement('div');
                bloco.innerHTML = `
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-brand-400 mb-2.5 px-1">${cat.nome}</h3>
                    <div class="space-y-2.5">
                        ${itensDaCat.map(p => cardProduto(p)).join('')}
                    </div>
                `;
                container.appendChild(bloco);
            });

            estadoVazio.classList.toggle('hidden', totalVisivel > 0);
        }

        function cardProduto(p) {
            const disponivel = String(p.disponivel) === '1';
            return `
                <div class="bg-dark-surface border border-dark-border rounded-2xl p-3.5 flex items-start gap-3 ${disponivel ? '' : 'opacity-50'}">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="text-xs font-bold text-white truncate">${escapeHtml(p.nome)}</h4>
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
                        <input type="checkbox" class="switch-toggle" ${disponivel ? 'checked' : ''} onchange="toggleDisponivel(${p.id}, this.checked)" title="Disponível">
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
            select.innerHTML = categorias.map(c =>
                `<option value="${c.id}" ${String(c.id) === String(categoriaSelecionadaId) ? 'selected' : ''}>${escapeHtml(c.nome)}</option>`
            ).join('');
        }

        function abrirModalProduto(produto) {
            document.getElementById('modal-titulo').textContent = produto ? 'Editar Item' : 'Novo Item';
            document.getElementById('produto-id').value = produto ? produto.id : '';
            document.getElementById('produto-nome').value = produto ? produto.nome : '';
            document.getElementById('produto-descricao').value = produto ? (produto.descricao || '') : '';
            document.getElementById('produto-preco').value = produto ? parseFloat(produto.preco).toFixed(2) : '';
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

            if (!nome || !categoriaId || !preco || preco <= 0) {
                Swal.fire({ icon: 'warning', title: 'Preencha nome, categoria e um preço válido.', background: '#141021', color: '#fff' });
                return;
            }

            const payload = {
                acao: id ? 'editar_produto' : 'criar_produto',
                id: id || undefined,
                nome, categoria_id: categoriaId, descricao, preco
            };

            const resultado = await chamarApi(payload);
            if (resultado.sucesso) {
                fecharModalProduto();
                await recarregarDados();
                Swal.fire({ icon: 'success', title: resultado.mensagem, background: '#141021', color: '#fff', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: resultado.erro || 'Erro ao salvar.', background: '#141021', color: '#fff' });
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
