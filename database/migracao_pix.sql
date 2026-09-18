-- ============================================================
-- Migração: Pagamento via Pix (Mercado Pago + chave estática)
-- Rode este script uma única vez no phpMyAdmin (ou via linha de
-- comando do MySQL), no banco "mais-lanches".
-- ============================================================

-- Controle de pagamento por pedido
ALTER TABLE pedidos
    ADD COLUMN status_pagamento VARCHAR(20) NOT NULL DEFAULT 'nao_aplicavel' COMMENT 'nao_aplicavel | pendente | pago | recusado',
    ADD COLUMN pagamento_id VARCHAR(60) NULL COMMENT 'ID do pagamento no Mercado Pago (quando aplicável)',
    ADD COLUMN pix_copia_cola TEXT NULL COMMENT 'Payload do Pix (copia e cola) gerado para este pedido',
    ADD COLUMN pix_qr_base64 LONGTEXT NULL COMMENT 'Imagem do QR Code em base64 (só quando vem do Mercado Pago)';

-- Identidade de pagamento por estabelecimento
ALTER TABLE estabelecimentos
    ADD COLUMN chave_pix VARCHAR(140) NULL COMMENT 'Chave Pix da loja (CPF/CNPJ/e-mail/telefone/aleatória) — usada como reserva sem gateway',
    ADD COLUMN nome_pix VARCHAR(30) NULL COMMENT 'Nome do titular exibido no QR Code estático (opcional, usa o nome da loja se vazio)',
    ADD COLUMN cidade_pix VARCHAR(20) NULL COMMENT 'Cidade exibida no QR Code estático (opcional)',
    ADD COLUMN mercadopago_access_token VARCHAR(255) NULL COMMENT 'Access Token da conta do Mercado Pago do estabelecimento — ativa a confirmação automática';

-- Pedidos já existentes que não são Pix continuam com status_pagamento
-- padrão ('nao_aplicavel'), então nada mais precisa ser ajustado neles.
