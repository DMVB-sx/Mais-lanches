<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= isset($tituloPagina) ? htmlspecialchars($tituloPagina) : 'Mais Lanches' ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/estilo.css">

    <style>
        :root {
            --cor-primaria: <?= !empty($estab['cor_primaria']) ? $estab['cor_primaria'] : '#9333ea' ?>;
            --cor-secundaria: <?= !empty($estab['cor_secundaria']) ? $estab['cor_secundaria'] : '#a855f7' ?>;
            --cor-fundo: <?= !empty($estab['cor_fundo']) ? $estab['cor_fundo'] : '#0b0813' ?>;
        }

        body {
            background-color: var(--cor-fundo);
            color: #f8fafc;
        }

        .btn-gradiente {
            background: linear-gradient(135deg, var(--cor-primaria), var(--cor-secundaria));
        }
        
        .btn-gradiente:hover {
            opacity: 0.95;
            filter: brightness(1.05);
        }
    </style>
</head>
<body class="min-h-screen antialiased selection:bg-purple-600 selection:text-white pb-12">