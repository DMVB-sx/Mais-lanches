<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../config/conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (empty($usuario) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :u LIMIT 1");
        $stmt->execute([':u' => $usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['admin_logado'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_nome'] = $user['nome'];
            $_SESSION['admin_estab'] = $user['estabelecimento_id'];

            header("Location: index.php?estab=" . $user['estabelecimento_id']);
            exit;
        } else {
            $erro = 'Usuário ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login Administrativo - Drilavy</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-dark-base flex items-center justify-center p-4 antialiased selection:bg-brand-500 selection:text-white">

    <div class="w-full max-w-sm bg-dark-surface border border-dark-border rounded-3xl p-6 sm:p-8 shadow-2xl shadow-purple-950/40 space-y-6">
        
        <div class="text-center space-y-2">
            <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-tr from-brand-600 to-fuchsia-500 p-0.5 shadow-lg shadow-purple-950/60 flex items-center justify-center">
                <div class="w-full h-full bg-[#0B0914] rounded-full flex items-center justify-center overflow-hidden">
                    <img src="../assets/img/logo.png" alt="Logo" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='../assets/img/logo.jpg';">
                </div>
            </div>
            <h1 class="text-xl font-extrabold text-white tracking-tight">Painel Drilavy</h1>
            <p class="text-xs text-slate-400">Acesse com suas credenciais de gestão</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($erro) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 text-xs">
            <div>
                <label class="block font-semibold text-slate-300 mb-1.5">Usuário</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="usuario" required placeholder="Digite seu usuário" class="w-full pl-9 p-3.5 bg-dark-card border border-dark-border rounded-xl text-white outline-none focus:border-brand-500 transition">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-300 mb-1.5">Senha</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="senha" required placeholder="Digite sua senha" class="w-full pl-9 p-3.5 bg-dark-card border border-dark-border rounded-xl text-white outline-none focus:border-brand-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-brand-600 to-fuchsia-600 hover:from-brand-700 hover:to-fuchsia-700 text-white font-extrabold p-3.5 rounded-xl text-sm transition active:scale-95 shadow-lg shadow-purple-950/60 flex items-center justify-center gap-2 mt-2">
                <i class="fa-solid fa-right-to-bracket"></i> Entrar no Painel
            </button>
        </form>

    </div>

</body>
</html>