<?php
// admin/imprimir_pedido.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/conexao.php';

$pedidoId = (int)($_GET['id'] ?? 0);
$estabId = (int)($_GET['estab'] ?? 1);

$stmt = $pdo->prepare("SELECT p.*, e.nome as estab_nome FROM pedidos p INNER JOIN estabelecimentos e ON p.estabelecimento_id = e.id WHERE p.id = :id AND p.estabelecimento_id = :estab LIMIT 1");
$stmt->execute([':id' => $pedidoId, ':estab' => $estabId]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) die("Pedido não encontrado.");
$nomeEstabRecibo = $p['estab_nome'] ?? 'Loja';

$stmtItens = $pdo->prepare("
    SELECT i.*, COALESCE(pr.nome, 'Item Personalizado') as produto_nome
    FROM pedido_itens i
    LEFT JOIN produtos pr ON i.produto_id = pr.id
    WHERE i.pedido_id = :id
");
$stmtItens->execute([':id' => $pedidoId]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Pedido #<?= $p['id'] ?></title>
    <style>
        @page { margin: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
            color: #000;
            font-size: 12px;
            line-height: 1.3;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .linha { border-bottom: 1px dashed #000; margin: 6px 0; }
        .item { margin-bottom: 4px; }
        .bold { font-weight: bold; }
        @media print {
            body { width: 100%; margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="text-center">
        <h2 style="margin:0; font-size:16px;"><?= htmlspecialchars(strtoupper($nomeEstabRecibo)) ?></h2>
        <p style="margin:2px 0;">Delivery & Balcão</p>
        <div class="linha"></div>
        <p class="bold">PEDIDO #<?= $p['id'] ?> - <?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></p>
        <p class="bold" style="font-size:14px; text-transform:uppercase;">
            <?= $p['tipo_entrega'] === 'delivery' ? '🛵 ENTREGA / DELIVERY' : '🏪 RETIRADA NO BALCÃO' ?>
        </p>
    </div>

    <div class="linha"></div>
    <p><b>Cliente:</b> <?= htmlspecialchars($p['cliente_nome']) ?><br>
    <b>WhatsApp:</b> <?= htmlspecialchars($p['cliente_whatsapp']) ?></p>

    <?php if ($p['tipo_entrega'] === 'delivery'): ?>
        <p><b>Endereço:</b> <?= htmlspecialchars($p['cliente_endereco']) ?><br>
        <b>Bairro:</b> <?= htmlspecialchars($p['cliente_bairro'] ?? '') ?><br>
        <?php if (!empty($p['cliente_complemento'])): ?>
            <b>Comp.:</b> <?= htmlspecialchars($p['cliente_complemento']) ?><br>
        <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="linha"></div>
    <div class="bold" style="margin-bottom:4px;">ITENS DO PEDIDO:</div>

    <?php foreach ($itens as $it): ?>
        <div class="item">
            <div><b><?= $it['quantidade'] ?>x</b> <?= htmlspecialchars($it['produto_nome']) ?></div>
            <?php if (!empty($it['observacao'])): ?>
                <div style="font-size:10px; margin-left:10px;">Obs: <?= htmlspecialchars($it['observacao']) ?></div>
            <?php endif; ?>
            <div class="text-right">R$ <?= number_format($it['subtotal'] ?? $it['preco_unitario'], 2, ',', '.') ?></div>
        </div>
    <?php endforeach; ?>

    <div class="linha"></div>
    <div style="display:flex; justify-content:space-between;">
        <span>Subtotal:</span>
        <span>R$ <?= number_format($p['subtotal'], 2, ',', '.') ?></span>
    </div>
    <?php if ($p['tipo_entrega'] === 'delivery'): ?>
        <div style="display:flex; justify-content:space-between;">
            <span>Taxa de Entrega:</span>
            <span>R$ <?= number_format($p['taxa_entrega'], 2, ',', '.') ?></span>
        </div>
    <?php endif; ?>
    <div class="linha"></div>
    <div style="display:flex; justify-content:space-between; font-size:14px;" class="bold">
        <span>TOTAL:</span>
        <span>R$ <?= number_format($p['total'], 2, ',', '.') ?></span>
    </div>

    <div class="linha"></div>
    <p><b>Forma de Pagamento:</b> <?= strtoupper($p['forma_pagamento']) ?></p>
    <?php if (!empty($p['troco_para'])): ?>
        <p><b>Troco para:</b> R$ <?= number_format($p['troco_para'], 2, ',', '.') ?></p>
    <?php endif; ?>

    <?php if (!empty($p['observacoes'])): ?>
        <p><b>Obs do Pedido:</b> <?= htmlspecialchars($p['observacoes']) ?></p>
    <?php endif; ?>

    <div class="linha"></div>
    <p class="text-center" style="font-size:10px;">Obrigado pela preferência!</p>
</body>
</html>