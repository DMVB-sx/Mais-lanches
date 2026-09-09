const express = require('express');
const cors = require('cors');
const QRCode = require('qrcode');
const { 
    default: makeWASocket, 
    useMultiFileAuthState, 
    DisconnectReason 
} = require('@whiskeysockets/baileys');
const pino = require('pino');

const app = express();
app.use(cors());
app.use(express.json({ limit: '30mb' }));
app.use(express.urlencoded({ limit: '30mb', extended: true }));

// Chave compartilhada entre o painel PHP e este serviço. Configure a
// variável de ambiente BOT_SECRET no servidor de produção — sem ela,
// qualquer pessoa que descobrisse a porta 3000 poderia mandar mensagens
// ou puxar a agenda de contatos pelo número da loja.
const BOT_SECRET = process.env.BOT_SECRET || 'troque-esta-chave-antes-de-publicar';
if (!process.env.BOT_SECRET) {
    console.warn('⚠️  BOT_SECRET não definido — usando chave padrão insegura. Configure a variável de ambiente antes de publicar em produção.');
}

app.use((req, res, next) => {
    if (req.get('x-bot-secret') !== BOT_SECRET) {
        return res.status(401).json({ sucesso: false, erro: 'Não autorizado.' });
    }
    next();
});

let sock = null;
let qrCodeData = null;
let connectionStatus = 'desconectado';
let broadcastEmAndamento = false;
let contatosWhatsAppMemoria = new Map();

async function iniciarWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('./auth_info_baileys');

    sock = makeWASocket({
        auth: state,
        logger: pino({ level: 'silent' }),
        syncFullHistory: true
    });

    sock.ev.on('creds.update', saveCreds);

    // Salva contatos sincronizados do aparelho
    sock.ev.on('contacts.upsert', (contacts) => {
        for (const c of contacts) {
            if (c.id && c.id.endsWith('@s.whatsapp.net')) {
                const numero = c.id.replace('@s.whatsapp.net', '');
                const nome = c.name || c.notify || c.verifiedName || numero;
                contatosWhatsAppMemoria.set(numero, { nome, whatsapp: numero });
            }
        }
    });

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            qrCodeData = await QRCode.toDataURL(qr);
            connectionStatus = 'aguardando_qrcode';
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
            connectionStatus = 'desconectado';
            qrCodeData = null;
            if (shouldReconnect) setTimeout(iniciarWhatsApp, 3000);
        } else if (connection === 'open') {
            connectionStatus = 'conectado';
            qrCodeData = null;
            console.log('✔ WhatsApp conectado! Puxando contatos da agenda...');
        }
    });
}

// Rota de status
app.get('/status', (req, res) => {
    res.json({
        status: connectionStatus,
        qrcode: qrCodeData,
        total_contatos_agenda: contatosWhatsAppMemoria.size,
        broadcast_em_andamento: broadcastEmAndamento
    });
});

// Rota para puxar contatos da agenda do celular conectado
app.get('/contatos-agenda', (req, res) => {
    const lista = Array.from(contatosWhatsAppMemoria.values());
    res.json({ sucesso: true, contatos: lista });
});

// Envio individual simples (para pedidos)
app.post('/enviar-mensagem', async (req, res) => {
    try {
        const { numero, mensagem, imagemBase64 } = req.body;
        if (!numero || (!mensagem && !imagemBase64)) {
            return res.status(400).json({ sucesso: false, erro: 'Número e mensagem obrigatórios.' });
        }
        if (connectionStatus !== 'conectado' || !sock) {
            return res.status(503).json({ sucesso: false, erro: 'WhatsApp desconectado.' });
        }

        let numLimpo = numero.replace(/\D/g, '');
        if (!numLimpo.startsWith('55')) numLimpo = '55' + numLimpo;

        let jid = `${numLimpo}@s.whatsapp.net`;
        try {
            const [resultado] = await sock.onWhatsApp(numLimpo);
            if (resultado && resultado.exists) jid = resultado.jid;
        } catch (e) {}

        if (imagemBase64) {
            const buffer = Buffer.from(imagemBase64.replace(/^data:image\/\w+;base64,/, ''), 'base64');
            await sock.sendMessage(jid, { image: buffer, caption: mensagem || '' });
        } else {
            await sock.sendMessage(jid, { text: mensagem });
        }

        return res.json({ sucesso: true, mensagem: 'Mensagem enviada!' });
    } catch (err) {
        return res.status(500).json({ sucesso: false, erro: err.message });
    }
});

// Transmissão com Foto + Legenda + Fila Segura
app.post('/disparar-transmissao', async (req, res) => {
    const { contatos, mensagemBase, imagemBase64 } = req.body;

    if (!Array.isArray(contatos) || contatos.length === 0 || (!mensagemBase && !imagemBase64)) {
        return res.status(400).json({ sucesso: false, erro: 'Lista de contatos e conteúdo são obrigatórios.' });
    }

    if (broadcastEmAndamento) {
        return res.status(409).json({ sucesso: false, erro: 'Já existe um disparo em andamento!' });
    }

    broadcastEmAndamento = true;
    res.json({ sucesso: true, mensagem: `Transmissão iniciada para ${contatos.length} contatos.` });

    (async () => {
        let imageBuffer = null;
        if (imagemBase64) {
            imageBuffer = Buffer.from(imagemBase64.replace(/^data:image\/\w+;base64,/, ''), 'base64');
        }

        for (let i = 0; i < contatos.length; i++) {
            const item = contatos[i];
            let numLimpo = (item.whatsapp || '').replace(/\D/g, '');
            if (!numLimpo.startsWith('55')) numLimpo = '55' + numLimpo;

            let jid = `${numLimpo}@s.whatsapp.net`;
            try {
                const [resultado] = await sock.onWhatsApp(numLimpo);
                if (resultado && resultado.exists) jid = resultado.jid;
            } catch (e) {}

            const primeiroNome = (item.nome || 'Cliente').split(' ')[0];
            const msgPersonalizada = (mensagemBase || '').replace('{nome}', primeiroNome);

            try {
                if (imageBuffer) {
                    await sock.sendMessage(jid, { image: imageBuffer, caption: msgPersonalizada });
                } else {
                    await sock.sendMessage(jid, { text: msgPersonalizada });
                }
                console.log(`[${i + 1}/${contatos.length}] Enviado para ${primeiroNome} (${numLimpo})`);
            } catch (err) {
                console.error(`Falha ao enviar para ${numLimpo}:`, err.message);
            }

            if (i < contatos.length - 1) {
                const delayMs = Math.floor(Math.random() * (16000 - 9000 + 1)) + 9000;
                await new Promise(r => setTimeout(r, delayMs));
            }
        }
        broadcastEmAndamento = false;
    })();
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`🚀 Servidor WhatsApp rodando na porta ${PORT}`);
    iniciarWhatsApp();
});