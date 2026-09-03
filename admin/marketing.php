<?php
// admin/marketing.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

// Busca clientes do banco de dados
$stmt = $pdo->prepare("
    SELECT 
        TRIM(cliente_nome) as cliente_nome, 
        cliente_whatsapp, 
        COUNT(id) as total_pedidos
    FROM pedidos 
    WHERE estabelecimento_id = :estab 
      AND cliente_whatsapp IS NOT NULL 
      AND LENGTH(cliente_whatsapp) >= 10
    GROUP BY cliente_whatsapp, TRIM(cliente_nome)
    ORDER BY MAX(criado_em) DESC
");
$stmt->execute([':estab' => $estabId]);
$clientesBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transmissão de Promoções - Drilavy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="min-h-screen bg-[#0B0914] text-slate-100 p-3 sm:p-6 font-sans antialiased pb-20">

    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- Topo -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-4">
            <div class="flex items-center gap-3">
                <a href="index.php?estab=<?= $estabId ?>" class="p-2.5 bg-white/5 hover:bg-white/10 rounded-xl text-slate-300 transition">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-base sm:text-lg font-extrabold text-white">Transmissão Direta WhatsApp</h1>
                    <p class="text-xs text-slate-400">Envie fotos e promoções para contatos do sistema ou da agenda do WhatsApp</p>
                </div>
            </div>

            <!-- Botão Sincronizar Contatos do WhatsApp conectado -->
            <div class="flex items-center gap-2">
                <button onclick="puxarContatosAgendaWhatsApp()" class="px-3 py-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold transition flex items-center gap-1.5 active:scale-95 shadow-sm">
                    <i class="fa-brands fa-whatsapp text-sm"></i> Puxar Agenda do WhatsApp
                </button>
                <span id="badge-total-selecionados" class="px-3.5 py-2 rounded-xl bg-purple-950/60 border border-purple-500/30 text-purple-300 text-xs font-bold shrink-0">
                    0 selecionados
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Coluna 1: Lista de Contatos -->
            <div class="lg:col-span-7 bg-[#141021] border border-white/10 rounded-3xl p-4 sm:p-5 space-y-4">
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 text-xs font-black flex items-center justify-center">1</span>
                        <h2 class="text-sm font-extrabold text-white">Destinatários (<span id="contagem-total-lista">0</span>)</h2>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" onclick="marcarTodos(true)" class="px-2.5 py-1 bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg border border-white/10 font-bold transition">
                            Marcar Todos
                        </button>
                        <button type="button" onclick="marcarTodos(false)" class="px-2.5 py-1 bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg border border-white/10 font-bold transition">
                            Desmarcar
                        </button>
                    </div>
                </div>

                <!-- Busca -->
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" id="filtro-cliente" oninput="filtrarLista()" placeholder="Buscar contato por nome ou telefone..." class="w-full pl-8 pr-3 py-2 bg-[#1C172E] border border-white/10 rounded-xl text-xs text-white focus:outline-none focus:border-purple-500">
                </div>

                <!-- Lista renderizada -->
                <div id="lista-contatos-box" class="space-y-2 max-h-[420px] overflow-y-auto pr-1"></div>

            </div>

            <!-- Coluna 2: Foto, Legenda e Disparo -->
            <div class="lg:col-span-5 bg-[#141021] border border-white/10 rounded-3xl p-4 sm:p-5 space-y-4">
                
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 text-xs font-black flex items-center justify-center">2</span>
                    <h2 class="text-sm font-extrabold text-white">Arte & Mensagem</h2>
                </div>

                <!-- Upload de Imagem -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">Foto da Promoção</label>
                    <div class="flex flex-col items-center justify-center border-2 border-dashed border-white/15 rounded-2xl p-3 hover:border-purple-500/50 transition cursor-pointer relative bg-[#1C172E]" onclick="document.getElementById('input-foto').click()">
                        <input type="file" id="input-foto" accept="image/*" class="hidden" onchange="previewImagem(event)">
                        
                        <div id="box-preview-vazio" class="text-center space-y-1 py-3">
                            <i class="fa-regular fa-image text-2xl text-purple-400"></i>
                            <p class="text-xs font-semibold text-slate-300">Selecionar Imagem</p>
                        </div>

                        <div id="box-preview-img" class="hidden text-center space-y-2">
                            <img id="img-preview" class="max-h-40 rounded-xl mx-auto shadow-md border border-white/10" alt="Preview">
                            <button type="button" onclick="removerImagem(event)" class="px-2.5 py-1 bg-red-500/10 text-red-400 text-[11px] font-bold rounded-lg border border-red-500/20">
                                Trocar Imagem
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Legenda -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Legenda da Foto</label>
                    <textarea id="texto-msg" rows="4" class="w-full p-3 bg-[#1C172E] border border-white/10 rounded-2xl text-xs text-white focus:outline-none focus:border-purple-500 placeholder-slate-500" placeholder="Digite a legenda da foto...">Boa noite, {nome}! 🍔🍕

Confira nossa promoção de hoje na Drilavy!
Faça seu pedido direto pelo nosso cardápio online:
http://localhost/Mais-lanches/cardapio.php?estab=1</textarea>
                    <span class="text-[10px] text-slate-400 block mt-1"><b class="text-purple-400">{nome}</b> = nome do contato.</span>
                </div>

                <!-- Botão Enviar -->
                <button onclick="iniciarDisparo()" id="btn-disparo" class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-fuchsia-600 hover:from-purple-700 hover:to-fuchsia-700 text-white font-extrabold rounded-2xl text-xs sm:text-sm transition active:scale-95 shadow-lg shadow-purple-950/60 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> <span id="btn-texto">Enviar Mensagem</span>
                </button>

            </div>

        </div>

    </div>

    <script>
        let contatosAtuais = <?= json_encode(array_map(fn($c) => ['nome' => $c['cliente_nome'], 'whatsapp' => $c['cliente_whatsapp'], 'origem' => 'Site'], $clientesBanco)) ?>;
        let imagemBase64Global = null;

        function renderizarListaContatos() {
            const container = document.getElementById('lista-contatos-box');
            container.innerHTML = '';

            document.getElementById('contagem-total-lista').innerText = contatosAtuais.length;

            if (contatosAtuais.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-xs text-slate-500">Nenhum contato encontrado.</div>';
                atualizarContador();
                return;
            }

            contatosAtuais.forEach((c, idx) => {
                const item = document.createElement('label');
                item.className = 'item-contato flex items-center justify-between p-3 rounded-2xl bg-[#1C172E]/60 border border-white/5 hover:border-purple-500/30 transition cursor-pointer';
                item.setAttribute('data-nome', (c.nome || '').toLowerCase());
                item.setAttribute('data-wpp', c.whatsapp || '');

                item.innerHTML = `
                    <div class="flex items-center gap-3">
                        <input type="checkbox" class="chk-cliente w-4 h-4 rounded text-purple-600 bg-[#0B0914] border-white/20 focus:ring-purple-500 cursor-pointer" value="${idx}" onchange="atualizarContador()" checked>
                        <div>
                            <p class="text-xs font-bold text-white">${c.nome || 'Sem Nome'}</p>
                            <p class="text-[10px] text-slate-400 font-mono">${c.whatsapp}</p>
                        </div>
                    </div>
                    <span class="text-[10px] ${c.origem === 'WhatsApp' ? 'text-emerald-400 bg-emerald-500/10' : 'text-purple-400 bg-purple-500/10'} px-2 py-0.5 rounded-full font-bold">
                        ${c.origem || 'Site'}
                    </span>
                `;
                container.appendChild(item);
            });

            atualizarContador();
        }

        async function puxarContatosAgendaWhatsApp() {
            try {
                Swal.fire({
                    title: 'Buscando agenda...',
                    text: 'Lendo contatos do WhatsApp conectado...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const res = await fetch('http://localhost:3000/contatos-agenda');
                const data = await res.json();

                if (data.sucesso && data.contatos && data.contatos.length > 0) {
                    const mapaExistentes = new Set(contatosAtuais.map(c => c.whatsapp.replace(/\D/g, '')));

                    let novosAdicionados = 0;
                    data.contatos.forEach(cWpp => {
                        const numLimpo = cWpp.whatsapp.replace(/\D/g, '');
                        if (!mapaExistentes.has(numLimpo)) {
                            contatosAtuais.push({
                                nome: cWpp.nome || numLimpo,
                                whatsapp: numLimpo,
                                origem: 'WhatsApp'
                            });
                            mapaExistentes.add(numLimpo);
                            novosAdicionados++;
                        }
                    });

                    renderizarListaContatos();
                    Swal.fire('Sucesso!', `${data.contatos.length} contatos lidos do aparelho (${novosAdicionados} novos adicionados).`, 'success');
                } else {
                    Swal.fire('Aviso', 'Nenhum contato encontrado ou o WhatsApp acabou de conectar. Aguarde alguns instantes e tente novamente.', 'info');
                }
            } catch (err) {
                Swal.fire('Erro', 'Não foi possível buscar os contatos. O microserviço Node.js está rodando?', 'error');
            }
        }

        function atualizarContador() {
            const checkboxes = document.querySelectorAll('.chk-cliente:checked');
            const total = checkboxes.length;
            document.getElementById('badge-total-selecionados').innerText = `${total} selecionados`;
            document.getElementById('btn-texto').innerText = `Enviar para ${total} Selecionados`;
        }

        function marcarTodos(status) {
            document.querySelectorAll('.chk-cliente').forEach(chk => {
                const item = chk.closest('.item-contato');
                if (item && item.style.display !== 'none') {
                    chk.checked = status;
                }
            });
            atualizarContador();
        }

        function filtrarLista() {
            const termo = document.getElementById('filtro-cliente').value.toLowerCase().trim();
            document.querySelectorAll('.item-contato').forEach(item => {
                const nome = item.getAttribute('data-nome') || '';
                const wpp = item.getAttribute('data-wpp') || '';
                if (nome.includes(termo) || wpp.includes(termo)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function previewImagem(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagemBase64Global = e.target.result;
                    document.getElementById('img-preview').src = imagemBase64Global;
                    document.getElementById('box-preview-vazio').classList.add('hidden');
                    document.getElementById('box-preview-img').classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            }
        }

        function removerImagem(e) {
            e.stopPropagation();
            imagemBase64Global = null;
            document.getElementById('input-foto').value = '';
            document.getElementById('box-preview-vazio').classList.remove('hidden');
            document.getElementById('box-preview-img').classList.add('hidden');
        }

        async function iniciarDisparo() {
            const selecionadosIndices = Array.from(document.querySelectorAll('.chk-cliente:checked')).map(el => parseInt(el.value));
            
            if (selecionadosIndices.length === 0) {
                Swal.fire('Atenção', 'Selecione pelo menos um contato.', 'warning');
                return;
            }

            const texto = document.getElementById('texto-msg').value.trim();
            if (!texto && !imagemBase64Global) {
                Swal.fire('Atenção', 'Escolha uma foto ou digite um texto.', 'warning');
                return;
            }

            const contatosFiltrados = selecionadosIndices.map(idx => contatosAtuais[idx]);

            const confirm = await Swal.fire({
                title: 'Confirmar Envio?',
                text: `Deseja iniciar o envio para ${contatosFiltrados.length} contatos selecionados?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, Iniciar Disparo',
                cancelButtonText: 'Cancelar'
            });

            if (!confirm.isConfirmed) return;

            const btn = document.getElementById('btn-disparo');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Disparando na fila segura...';

            try {
                const res = await fetch('http://localhost:3000/disparar-transmissao', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        contatos: contatosFiltrados,
                        mensagemBase: texto,
                        imagemBase64: imagemBase64Global
                    })
                });
                const data = await res.json();

                if (data.sucesso) {
                    Swal.fire('Transmissão Iniciada!', data.mensagem, 'success');
                } else {
                    Swal.fire('Erro', data.erro, 'error');
                }
            } catch (err) {
                Swal.fire('Erro', 'Verifique se o microserviço Node.js está rodando.', 'error');
            } finally {
                btn.disabled = false;
                atualizarContador();
            }
        }

        renderizarListaContatos();
    </script>
</body>
</html>