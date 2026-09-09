<?php
// admin/whatsapp.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/conexao.php';

$estabId = isset($_GET['estab']) ? (int)$_GET['estab'] : 1;

$stmtEstab = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id LIMIT 1");
$stmtEstab->execute([':id' => $estabId]);
$estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
$nomeEstab = $estab['nome'] ?? 'Drilavy Lanchonete e Pizzaria';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conectar WhatsApp - <?= htmlspecialchars($nomeEstab) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
        require_once __DIR__ . '/../includes/tema.php';
        tema_imprimirTailwindConfig($estab);
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-[#0B0914] text-white flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-md bg-[#141021] border border-purple-900/30 rounded-3xl p-6 text-center space-y-5 shadow-2xl">
        <h1 class="text-lg font-bold flex items-center justify-center gap-2 text-emerald-400">
            <i class="fa-brands fa-whatsapp text-2xl"></i> Conexão WhatsApp
        </h1>

        <div id="box-status" class="py-2 px-4 rounded-xl text-xs font-bold bg-white/5 border border-white/10 text-slate-300">
            Verificando servidor...
        </div>

        <div id="box-qrcode" class="flex justify-center items-center min-h-[200px]">
            <div class="text-xs text-slate-500 flex flex-col items-center gap-2">
                <i class="fa-solid fa-spinner fa-spin text-xl text-purple-500"></i>
                <span>Aguardando status...</span>
            </div>
        </div>

        <p class="text-xs text-slate-400">Abra o WhatsApp no celular > Aparelhos conectados > Conectar aparelho e aponte para a tela.</p>

        <a href="index.php?estab=<?= $estabId ?>" class="block w-full py-3 bg-white/5 hover:bg-white/10 rounded-xl text-xs font-bold transition">
            Voltar ao Painel
        </a>
    </div>

    <script>
        async function checarStatus() {
            try {
                const res = await fetch('../api/bot_proxy.php?rota=status');
                const data = await res.json();

                const boxStatus = document.getElementById('box-status');
                const boxQr = document.getElementById('box-qrcode');

                if (data.status === 'conectado') {
                    boxStatus.className = 'py-2 px-4 rounded-xl text-xs font-bold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400';
                    boxStatus.innerHTML = '✔ Conectado e Pronto para Envios!';
                    boxQr.innerHTML = '<div class="w-24 h-24 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-4xl mx-auto"><i class="fa-solid fa-check"></i></div>';
                } else if (data.status === 'aguardando_qrcode' && data.qrcode) {
                    boxStatus.className = 'py-2 px-4 rounded-xl text-xs font-bold bg-amber-500/10 border border-amber-500/20 text-amber-300';
                    boxStatus.innerHTML = 'Aguardando Leitura do QR Code';
                    boxQr.innerHTML = `<img src="${data.qrcode}" class="w-56 h-56 rounded-2xl border border-purple-500/30 shadow-lg" alt="QR Code">`;
                } else {
                    boxStatus.className = 'py-2 px-4 rounded-xl text-xs font-bold bg-red-500/10 border border-red-500/20 text-red-400';
                    boxStatus.innerHTML = 'Microserviço Desconectado';
                }
            } catch (err) {
                document.getElementById('box-status').innerHTML = '⚠ Servidor Node.js não iniciado (rode npm start)';
            }
        }

        checarStatus();
        setInterval(checarStatus, 3000);
    </script>
</body>
</html>