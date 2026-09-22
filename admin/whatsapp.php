<?php
// admin/whatsapp.php — Central WhatsApp: Ligação Bot (QR Code) + Transmissão com Imagem
require_once __DIR__ . '/auth.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

$stmtEstab =$pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id LIMIT 1");
$stmtEstab->execute([':id' =>$estabId]);
$estab =$stmtEstab->fetch(PDO::FETCH_ASSOC);
$nomeEstab =$estab['nome'] ?? 'Drilavy Lanchonete e Pizzaria';

// Lista de clientes únicos para a contagem e disparo
$stmtClientes =$pdo->prepare("
    SELECT DISTINCT cliente_nome, cliente_whatsapp 
    FROM pedidos 
    WHERE estabelecimento_id = :estab AND cliente_whatsapp IS NOT NULL AND cliente_whatsapp != ''
    ORDER BY id DESC
");
$stmtClientes->execute([':estab' =>$estabId]);
$clientes =$stmtClientes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Central WhatsApp - <?= htmlspecialchars($nomeEstab) ?></title>
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
</head>
<body class="min-h-screen bg-dark-base text-slate-100 flex flex-col antialiased">

    <!-- Top Header -->
    <header class="sticky top-0 z-40 bg-dark-surface/90 backdrop-blur-md border-b border-dark-border px-4 sm:px-6 py-3">
        <div class="max-w-5xl mx-auto flex items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <a href="index.php?estab=<?= $estabId ?>" class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 border border-dark-border flex items-center justify-center transition" title="Voltar ao Painel">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </a>
                <h1 class="text-sm font-extrabold text-white tracking-tight flex items-center gap-2">
                    <i class="fa-brands fa-whatsapp text-emerald-400"></i> Central WhatsApp
                </h1>
            </div>

            <!-- Alternador de Abas -->
            <div class="flex items-center gap-1.5 bg-dark-card p-1 rounded-xl border border-dark-border">
                <button onclick="mudarAba('conexao')" id="btn-aba-conexao" class="px-3 py-1.5 rounded-lg text-xs font-bold transition bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    <i class="fa-solid fa-qrcode mr-1"></i> Conexão Bot
                </button>
                <button onclick="mudarAba('transmissao')" id="btn-aba-transmissao" class="px-3 py-1.5 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white">
                    <i class="fa-solid fa-bullhorn mr-1"></i> Transmissão (<?= count($clientes) ?>)
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6">
        
        <!-- SECÇÃO 1: Conexão Bot / QR Code -->
        <div id="secao-conexao" class="space-y-4">
            <div class="bg-dark-surface border border-dark-border rounded-3xl p-6 text-center max-w-md mx-auto space-y-4 shadow-xl">
                
                <div id="box-status" class="py-2 px-4 rounded-xl text-xs font-bold bg-white/5 border border-white/10 text-slate-300 inline-flex items-center gap-2">
                    <i class="fa-solid fa-spinner fa-spin"></i> Verificando servidor...
                </div>

                <div id="box-qrcode" class="w-64 h-64 mx-auto bg-white p-3 rounded-2xl flex items-center justify-center border border-dark-border shadow-inner">
                    <div class="text-xs text-slate-500 flex flex-col items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin text-2xl text-purple-500"></i>
                        <span>Aguardando status...</span>
                    </div>
                </div>

                <p class="text-[11px] text-slate-400 leading-relaxed">
                    Abra o WhatsApp no celular &gt; <b>Aparelhos conectados</b> &gt; <b>Conectar aparelho</b> e aponte a câmara para a tela.
                </p>

                <button onclick="reiniciarBot()" class="w-full py-2.5 rounded-xl text-xs font-bold bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 transition active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-rotate-right"></i> Reiniciar Conexão
                </button>
            </div>
        </div>

        <!-- SECÇÃO 2: Transmissão com Envio de Imagem -->
        <div id="secao-transmissao" class="hidden max-w-2xl mx-auto space-y-4">
            <div class="bg-dark-surface border border-dark-border rounded-3xl p-6 space-y-4 shadow-xl">
                <div class="border-b border-white/5 pb-3">
                    <h2 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-bullhorn text-brand-400"></i> Disparo de Promoções & Avisos
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Esta mensagem será enviada individualmente para os <b><?= count($clientes) ?> clientes</b> da sua base de dados.
                    </p>
                </div>

                <!-- Campo de Imagem -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Imagem da Promoção / Cardápio (Opcional)</label>
                    <div class="border-2 border-dashed border-dark-border rounded-2xl p-4 text-center hover:border-brand-500/50 transition cursor-pointer relative bg-dark-card" onclick="document.getElementById('input-imagem').click()">
                        <input type="file" id="input-imagem" accept="image/*" class="hidden" onchange="processarImagem(this)">
                        <div id="preview-container" class="hidden space-y-2">
                            <img id="img-preview" src="" alt="Preview" class="max-h-48 rounded-xl mx-auto object-contain shadow-md">
                            <button type="button" onclick="event.stopPropagation(); removerImagem()" class="px-2.5 py-1 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 text-[11px] font-bold hover:bg-red-500/30">
                                <i class="fa-solid fa-trash-can mr-1"></i> Remover Imagem
                            </button>
                        </div>
                        <div id="placeholder-upload" class="space-y-1 py-3 text-slate-400">
                            <i class="fa-regular fa-image text-3xl text-brand-400 mb-1"></i>
                            <p class="text-xs font-bold text-white">Clique para selecionar uma foto</p>
                            <p class="text-[10px]">Formatos aceites: JPG, PNG ou WEBP</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Mensagem do Comunicado</label>
                    <textarea id="texto-transmissao" rows="5" placeholder="Ex: Olá! Hoje temos o Especial da Semana preparado para você. Venha conferir o nosso cardápio atualizado!" class="w-full p-3.5 bg-dark-card border border-dark-border rounded-2xl text-xs text-white placeholder-slate-500 outline-none focus:border-brand-500 transition resize-none"></textarea>
                </div>

                <div class="p-3 bg-brand-500/10 border border-brand-500/20 rounded-xl text-[11px] text-brand-300 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-brand-400"></i>
                    <span>Para evitar bloqueios, o envio contém pausas automáticas entre cada cliente.</span>
                </div>

                <button onclick="dispararTransmissao()" id="btn-enviar-transmissao" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold rounded-2xl text-xs transition active:scale-95 flex items-center justify-center gap-2 shadow-lg shadow-emerald-950/50">
                    <i class="fa-solid fa-paper-plane"></i> Iniciar Disparo da Transmissão
                </button>
            </div>
        </div>

    </main>

    <script>
        const estabId = <?= $estabId ?>;
        let imagemBase64 = null;

        function mudarAba(aba) {
            const secaoConexao = document.getElementById('secao-conexao');
            const secaoTransmissao = document.getElementById('secao-transmissao');
            const btnConexao = document.getElementById('btn-aba-conexao');
            const btnTransmissao = document.getElementById('btn-aba-transmissao');

            if (aba === 'conexao') {
                secaoConexao.classList.remove('hidden');
                secaoTransmissao.classList.add('hidden');
                btnConexao.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                btnTransmissao.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white';
            } else {
                secaoConexao.classList.add('hidden');
                secaoTransmissao.classList.remove('hidden');
                btnTransmissao.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition bg-brand-500/20 text-brand-300 border border-brand-500/30';
                btnConexao.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition text-slate-400 hover:text-white';
            }
        }

        // Leitura e status do QR Code compatível com a rota original do bot
        async function checarStatus() {
            try {
                const res = await fetch(`../api/bot_proxy.php?rota=status&estab=${estabId}&t=${Date.now()}`);
                const data = await res.json();

                const boxStatus = document.getElementById('box-status');
                const boxQr = document.getElementById('box-qrcode');

                if (data.status === 'conectado' || data.status === 'CONNECTED' || data.connected === true) {
                    boxStatus.className = 'py-2 px-4 rounded-xl text-xs font-bold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 inline-flex items-center gap-2';
                    boxStatus.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i> Conectado e Pronto para Envios!';
                    boxQr.innerHTML = '<div class="w-24 h-24 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-4xl mx-auto"><i class="fa-solid fa-check"></i></div>';
                } else if ((data.status === 'aguardando_qrcode' || data.status === 'QRCODE') && (data.qrcode || data.qr_code)) {
                    const qrImg = data.qrcode || data.qr_code;
                    boxStatus.className = 'py-2 px-4 rounded-xl text-xs font-bold bg-amber-500/10 border border-amber-500/20 text-amber-300 inline-flex items-center gap-2';
                    boxStatus.innerHTML = '<i class="fa-solid fa-qrcode text-amber-400"></i> Aguardando Leitura do QR Code';
                    boxQr.innerHTML = `<img src="${qrImg}" class="w-56 h-56 rounded-2xl border border-purple-500/30 shadow-lg object-contain" alt="QR Code">`;
                } else {
                    boxStatus.className = 'py-2 px-4 rounded-xl text-xs font-bold bg-red-500/10 border border-red-500/20 text-red-400 inline-flex items-center gap-2';
                    boxStatus.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Microserviço Desconectado';
                    boxQr.innerHTML = `
                        <div class="text-xs text-slate-500 flex flex-col items-center gap-2 text-center p-4">
                            <i class="fa-solid fa-power-off text-2xl text-slate-600"></i>
                            <span>Inicie o servidor Node.js ou reinicie a conexão.</span>
                        </div>
                    `;
                }
            } catch (err) {
                document.getElementById('box-status').className = 'py-2 px-4 rounded-xl text-xs font-bold bg-red-500/10 border border-red-500/20 text-red-400 inline-flex items-center gap-2';
                document.getElementById('box-status').innerHTML = '<i class="fa-solid fa-plug-circle-xmark"></i> Servidor Node.js não iniciado';
            }
        }

        async function reiniciarBot() {
            try {
                await fetch(`../api/bot_proxy.php?rota=restart&estab=${estabId}`);
                Swal.fire({
                    icon: 'info',
                    title: 'Reiniciando...',
                    text: 'Reiniciando conexão do WhatsApp.',
                    timer: 2000,
                    showConfirmButton: false,
                    background: '#141021',
                    color: '#fff'
                });
                setTimeout(checarStatus, 2500);
            } catch(e) {}
        }

        function processarImagem(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagemBase64 = e.target.result;
                    document.getElementById('img-preview').src = imagemBase64;
                    document.getElementById('preview-container').classList.remove('hidden');
                    document.getElementById('placeholder-upload').classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function removerImagem() {
            imagemBase64 = null;
            document.getElementById('input-imagem').value = '';
            document.getElementById('preview-container').classList.add('hidden');
            document.getElementById('placeholder-upload').classList.remove('hidden');
        }

        async function dispararTransmissao() {
            const mensagem = document.getElementById('texto-transmissao').value.trim();
            if (!mensagem && !imagemBase64) {
                Swal.fire({ icon: 'warning', title: 'Adicione uma mensagem ou selecione uma imagem.', background: '#141021', color: '#fff' });
                return;
            }

            const confirmacao = await Swal.fire({
                icon: 'question',
                title: 'Disparar para todos os clientes?',
                text: 'A mensagem será enviada individualmente para a sua lista de clientes.',
                showCancelButton: true,
                confirmButtonText: 'Sim, Enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                background: '#141021',
                color: '#fff'
            });

            if (!confirmacao.isConfirmed) return;

            const btn = document.getElementById('btn-enviar-transmissao');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando envio...';

            try {
                const res = await fetch(`../api/bot_proxy.php?rota=broadcast&estab=${estabId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        mensagem: mensagem,
                        imagem: imagemBase64 
                    })
                });
                const data = await res.json();

                if (data && (data.sucesso || data.status === 'ok')) {
                    Swal.fire({ icon: 'success', title: 'Transmissão iniciada com sucesso!', background: '#141021', color: '#fff' });
                    document.getElementById('texto-transmissao').value = '';
                    removerImagem();
                } else {
                    Swal.fire({ icon: 'error', title: data.erro || 'Falha ao realizar envio.', background: '#141021', color: '#fff' });
                }
            } catch(e) {
                Swal.fire({ icon: 'error', title: 'Erro ao conectar ao bot.', background: '#141021', color: '#fff' });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Iniciar Disparo da Transmissão';
            }
        }

        checarStatus();
        setInterval(checarStatus, 3000);
    </script>
</body>
</html>