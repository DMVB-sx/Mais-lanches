// assets/js/cardapio.js
const urlParams = new URLSearchParams(window.location.search);
const estabId = urlParams.get('estab') || 1;

let dadosEstabelecimento = null;
let cardapioCompleto = [];
let pizzaSabores = [];
let produtosCatalogo = [];
let categoriaAtivaId = null;
let carrinho = [];

let pizzaEmMontagem = null;
let produtoSendoCustomizado = null;

async function carregarCardapio() {
    try {
        const res = await fetch(`api/produtos.php?estab=${estabId}`);
        const data = await res.json();

        if (!data.sucesso) {
            document.getElementById('container-cardapio').innerHTML = `<div class="text-center py-12 text-[#5A4E40]">Erro ao carregar itens.</div>`;
            return;
        }

        dadosEstabelecimento = data.estabelecimento;
        cardapioCompleto = data.cardapio;
        pizzaSabores = data.pizza_sabores || [];

        produtosCatalogo = [];
        cardapioCompleto.forEach(cat => {
            cat.produtos.forEach(p => produtosCatalogo.push(p));
        });

        if (cardapioCompleto.length > 0) {
            categoriaAtivaId = cardapioCompleto[0].categoria_id;
        }

        renderizarNavCategorias();
        renderizarProdutosDaCategoriaAtiva();
    } catch (err) {
        console.error(err);
        document.getElementById('container-cardapio').innerHTML = `<div class="text-center py-12 text-[#5A4E40]">Erro ao conectar com a API.</div>`;
    }
}

function renderizarNavCategorias() {
    const nav = document.getElementById('nav-categorias');
    nav.innerHTML = '';

    cardapioCompleto.forEach(cat => {
        const chip = document.createElement('button');
        chip.type = 'button';
        const ativa = cat.categoria_id === categoriaAtivaId;
        chip.className = `flex-none px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-all ${
            ativa 
                ? 'bg-gradient-to-r from-purple-600 to-fuchsia-600 text-white shadow-lg shadow-purple-950/60 scale-105' 
                : 'bg-white/5 text-slate-300 hover:bg-white/10 border border-white/5'
        }`;
        chip.innerText = cat.categoria_nome;
        chip.onclick = () => {
            categoriaAtivaId = cat.categoria_id;
            renderizarNavCategorias();
            renderizarProdutosDaCategoriaAtiva();
        };
        nav.appendChild(chip);
    });
}

function renderizarProdutosDaCategoriaAtiva() {
    const main = document.getElementById('container-cardapio');
    main.innerHTML = '';

    const categoriaAtual = cardapioCompleto.find(c => c.categoria_id === categoriaAtivaId);
    if (!categoriaAtual) return;

    const ehCategoriaPizza = categoriaAtual.categoria_nome.toLowerCase().includes('pizza');

    let produtosHtml = '';
    categoriaAtual.produtos.forEach(prod => {
        const precoFormatado = parseFloat(prod.preco).toFixed(2).replace('.', ',');
        const textoPreco = ehCategoriaPizza ? `A partir de R$ ${precoFormatado}` : `R$ ${precoFormatado}`;
        const textoBotao = ehCategoriaPizza ? `🍕 Montar Pizza` : `+ Adicionar`;

        produtosHtml += `
            <article class="bg-[#171124] border border-white/5 hover:border-purple-500/40 rounded-2xl p-4 flex justify-between items-center gap-4 transition shadow-lg shadow-black/20 cursor-pointer group" onclick="clicouNoProduto(${prod.id})">
                <div class="flex-1 pr-2">
                    <h3 class="font-bold text-sm text-white group-hover:text-purple-300 transition">${prod.nome}</h3>
                    <p class="text-xs text-slate-400 mt-1 leading-snug line-clamp-2">${prod.descricao || ''}</p>
                    <span class="inline-block mt-2 font-black text-sm text-fuchsia-400">${textoPreco}</span>
                </div>
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                    <button type="button" class="bg-purple-600/20 hover:bg-purple-600 text-purple-300 hover:text-white border border-purple-500/30 text-xs font-bold px-3.5 py-2 rounded-xl transition flex items-center gap-1.5">
                        ${textoBotao}
                    </button>
                </div>
            </article>
        `;
    });

    main.innerHTML = `
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-white/10">
            <h2 class="text-lg font-black text-white">${categoriaAtual.categoria_nome}</h2>
            <span class="text-xs font-bold text-purple-400 bg-purple-950/60 px-2.5 py-1 rounded-full border border-purple-800/40">${categoriaAtual.produtos.length} opções</span>
        </div>
        <div class="space-y-3">${produtosHtml}</div>
    `;
}

function clicouNoProduto(id) {
    const prod = produtosCatalogo.find(p => p.id == id);
    if (!prod) return;

    if (prod.categoria_id == 5 || prod.nome.toLowerCase().includes('pizza')) {
        abrirMontadorPizza(prod);
    } else if (prod.grupos_adicionais && prod.grupos_adicionais.length > 0) {
        abrirModalCustomizacao(prod);
    } else {
        adicionarItemDireto(prod);
    }
}

function abrirMontadorPizza(prod) {
    let maxSabores = 2;
    let campoTamanho = 'preco_m';

    if (prod.nome.includes('Família') || prod.nome.includes('Familia')) {
        maxSabores = 3;
        campoTamanho = 'preco_f';
    } else if (prod.nome.includes('Grande')) {
        maxSabores = 2;
        campoTamanho = 'preco_g';
    }

    pizzaEmMontagem = {
        id: prod.id,
        nome: prod.nome,
        maxSabores: maxSabores,
        campoTamanho: campoTamanho,
        precoBase: parseFloat(prod.preco)
    };

    document.getElementById('pizza-modal-titulo').innerText = prod.nome;
    document.getElementById('pizza-modal-limite').innerText = `Selecione até ${maxSabores} sabores (ou 1 sabor inteiro)`;
    document.getElementById('obs-pizza').value = '';

    const container = document.getElementById('container-sabores-pizza');
    container.innerHTML = '';

    const tipos = [
        { key: 'tradicional', nome: 'Sabores Tradicionais 🧀' },
        { key: 'especial', nome: 'Sabores Especiais ⭐' },
        { key: 'premium', nome: 'Sabores Premium 👑' }
    ];

    tipos.forEach(tipo => {
        const saboresDoTipo = pizzaSabores.filter(s => s.categoria_sabor === tipo.key);
        if (saboresDoTipo.length === 0) return;

        let htmlItens = '';
        saboresDoTipo.forEach(s => {
            const precoSabor = parseFloat(s[campoTamanho]).toFixed(2).replace('.', ',');
            htmlItens += `
                <label class="flex justify-between items-center py-2 border-b border-dashed border-[#D8CBAE] last:border-none text-xs cursor-pointer">
                    <div class="pr-2">
                        <input type="checkbox" name="sabores_pizza" value="${s.id}" data-nome="${s.nome}" data-preco="${s[campoTamanho]}" onchange="validarLimiteSaboresEPreco(this)">
                        <strong class="ml-1.5 text-[#241C15]">${s.nome}</strong>
                        <div class="text-[11px] text-[#5A4E40] ml-5 leading-tight">${s.ingredientes}</div>
                    </div>
                    <span class="text-xs font-bold text-[#B23A2E] whitespace-nowrap">R$ ${precoSabor}</span>
                </label>
            `;
        });

        container.innerHTML += `
            <div class="text-xs font-bold text-[#8E2C22] pl-1 border-l-2 border-[#B23A2E] marcador mb-1">${tipo.nome}</div>
            <div class="bg-white border border-[#D8CBAE] rounded-lg p-3">${htmlItens}</div>
        `;
    });

    calcularTotalPizza();
    document.getElementById('gaveta-fundo').classList.add('aberta');
    document.getElementById('gaveta-pizza').classList.add('aberta');
}

function validarLimiteSaboresEPreco(input) {
    const marcados = document.querySelectorAll('input[name="sabores_pizza"]:checked');
    if (marcados.length > pizzaEmMontagem.maxSabores) {
        input.checked = false;
        Toast.fire({ icon: 'warning', title: `Máximo de ${pizzaEmMontagem.maxSabores} sabores!` });
    }
    calcularTotalPizza();
}

function calcularTotalPizza() {
    const marcados = Array.from(document.querySelectorAll('input[name="sabores_pizza"]:checked'));
    let maiorPrecoSabor = pizzaEmMontagem.precoBase;

    if (marcados.length > 0) {
        const precos = marcados.map(i => parseFloat(i.dataset.preco));
        maiorPrecoSabor = Math.max(...precos);
    }

    const borda = document.querySelector('input[name="borda_pizza"]:checked');
    const precoBorda = borda ? parseFloat(borda.dataset.preco || 0) : 0;

    const totalFinal = maiorPrecoSabor + precoBorda;
    document.getElementById('total-pizza-customizada').innerText = `R$ ${totalFinal.toFixed(2).replace('.', ',')}`;
}

function confirmarAdicaoPizza() {
    const marcados = Array.from(document.querySelectorAll('input[name="sabores_pizza"]:checked'));
    if (marcados.length === 0) {
        Swal.fire('Escolha o sabor', 'Selecione ao menos 1 sabor para a pizza.', 'warning');
        return;
    }

    const precos = marcados.map(i => parseFloat(i.dataset.preco));
    const maiorPreco = Math.max(...precos);

    const borda = document.querySelector('input[name="borda_pizza"]:checked');
    const precoBorda = borda ? parseFloat(borda.dataset.preco || 0) : 0;
    const precoTotalUnitario = maiorPreco + precoBorda;

    const nomesSabores = marcados.map(i => i.dataset.nome);
    let textoFracionado = '';
    if (marcados.length === 1) {
        textoFracionado = `1/1 Inteira: ${nomesSabores[0]}`;
    } else if (marcados.length === 2) {
        textoFracionado = `1/2 ${nomesSabores[0]} + 1/2 ${nomesSabores[1]}`;
    } else if (marcados.length === 3) {
        textoFracionado = `1/3 ${nomesSabores[0]} + 1/3 ${nomesSabores[1]} + 1/3 ${nomesSabores[2]}`;
    }

    if (borda && precoBorda > 0) {
        textoFracionado += ` | ${borda.value} (+R$ ${precoBorda.toFixed(2).replace('.', ',')})`;
    }

    carrinho.push({
        id: parseInt(pizzaEmMontagem.id),
        nome: pizzaEmMontagem.nome,
        preco_base: maiorPreco,
        preco_total_unitario: precoTotalUnitario,
        qtd: 1,
        adicionais: [],
        adicionais_texto: textoFracionado,
        obs_item: document.getElementById('obs-pizza').value
    });

    fecharTodasGavetas();
    atualizarInterface();
    Toast.fire({ icon: 'success', title: 'Pizza adicionada!' });
}

function adicionarItemDireto(prod) {
    carrinho.push({
        id: parseInt(prod.id),
        nome: prod.nome,
        preco_base: parseFloat(prod.preco),
        preco_total_unitario: parseFloat(prod.preco),
        qtd: 1,
        adicionais: [],
        adicionais_texto: '',
        obs_item: ''
    });
    atualizarInterface();
    Toast.fire({ icon: 'success', title: `${prod.nome} adicionado!` });
}

function abrirModalCustomizacao(prod) {
    produtoSendoCustomizado = prod;
    document.getElementById('modal-item-nome').innerText = prod.nome;
    document.getElementById('modal-item-preco-base').innerText = `R$ ${parseFloat(prod.preco).toFixed(2).replace('.', ',')}`;
    document.getElementById('obs-item-customizado').value = '';

    const container = document.getElementById('container-grupos-adicionais');
    container.innerHTML = '';

    prod.grupos_adicionais.forEach(grp => {
        const tipoInput = grp.maximo === 1 ? 'radio' : 'checkbox';
        let itensHtml = '';

        grp.itens.forEach((ad, index) => {
            const precoTxt = parseFloat(ad.preco) > 0 ? `+ R$ ${parseFloat(ad.preco).toFixed(2).replace('.', ',')}` : 'Grátis';
            const checkedPadrao = (grp.obrigatorio && index === 0 && tipoInput === 'radio') ? 'checked' : '';

            itensHtml += `
                <label class="flex justify-between items-center py-2 border-b border-dashed border-[#D8CBAE] last:border-none text-xs cursor-pointer">
                    <div>
                        <input type="${tipoInput}" name="grupo_${grp.id}" value="${ad.id}" data-nome="${ad.nome}" data-preco="${ad.preco}" ${checkedPadrao} onchange="calcularTotalCustomizacao()">
                        <span class="ml-2 font-medium text-[#241C15]">${ad.nome}</span>
                    </div>
                    <strong class="text-[#B23A2E]">${precoTxt}</strong>
                </label>
            `;
        });

        container.innerHTML += `
            <div class="bg-white border border-[#D8CBAE] rounded-lg p-3 bloco-adicional" data-obrigatorio="${grp.obrigatorio}">
                <h4 class="text-xs font-bold text-[#241C15] mb-2 flex justify-between items-center">
                    <span>${grp.nome}</span>
                    ${grp.obrigatorio ? `<span class="bg-[#B23A2E] text-white text-[10px] px-2 py-0.5 rounded font-bold">Obrigatório</span>` : `<span class="text-[#5A4E40] text-[10px]">Opcional</span>`}
                </h4>
                <div>${itensHtml}</div>
            </div>
        `;
    });

    calcularTotalCustomizacao();
    document.getElementById('gaveta-fundo').classList.add('aberta');
    document.getElementById('gaveta-adicionais').classList.add('aberta');
}

function calcularTotalCustomizacao() {
    let total = parseFloat(produtoSendoCustomizado.preco);
    const selecionados = document.querySelectorAll('#container-grupos-adicionais input:checked');
    selecionados.forEach(input => {
        total += parseFloat(input.dataset.preco || 0);
    });
    document.getElementById('total-item-customizado').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
}

function confirmarAdicaoItem() {
    let precoUnitarioFinal = parseFloat(produtoSendoCustomizado.preco);
    let adicionaisEscolhidos = [];
    let textoAdicionais = [];

    const selecionados = document.querySelectorAll('#container-grupos-adicionais input:checked');
    selecionados.forEach(input => {
        const precoAd = parseFloat(input.dataset.preco);
        precoUnitarioFinal += precoAd;
        adicionaisEscolhidos.push({
            id: input.value,
            nome: input.dataset.nome,
            preco: precoAd
        });
        textoAdicionais.push(`${input.dataset.nome}${precoAd > 0 ? ` (+R$ ${precoAd.toFixed(2).replace('.', ',')})` : ''}`);
    });

    carrinho.push({
        id: parseInt(produtoSendoCustomizado.id),
        nome: produtoSendoCustomizado.nome,
        preco_base: parseFloat(produtoSendoCustomizado.preco),
        preco_total_unitario: precoUnitarioFinal,
        qtd: 1,
        adicionais: adicionaisEscolhidos,
        adicionais_texto: textoAdicionais.join(', '),
        obs_item: document.getElementById('obs-item-customizado').value
    });

    fecharTodasGavetas();
    atualizarInterface();
    Toast.fire({ icon: 'success', title: `${produtoSendoCustomizado.nome} adicionado!` });
}

function removerItemDoCarrinho(index) {
    carrinho.splice(index, 1);
    atualizarInterface();
    abrirGavetaCarrinho();
}

function atualizarInterface() {
    const totalQtd = carrinho.reduce((acc, c) => acc + c.qtd, 0);
    const subtotal = carrinho.reduce((acc, c) => acc + (c.preco_total_unitario * c.qtd), 0);
    const flutuante = document.getElementById('btn-flutuante');

    if (totalQtd > 0) {
        flutuante.classList.remove('hidden');
        flutuante.classList.add('flex');
        document.getElementById('flutuante-qtd').innerText = `${totalQtd} ${totalQtd === 1 ? 'item' : 'itens'}`;
        document.getElementById('flutuante-total').innerText = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
    } else {
        flutuante.classList.add('hidden');
        flutuante.classList.remove('flex');
        fecharTodasGavetas();
    }

    atualizarTotais();
}

function abrirGavetaCarrinho() {
    const lista = document.getElementById('lista-carrinho');
    lista.innerHTML = '';

    carrinho.forEach((item, index) => {
        lista.innerHTML += `
            <div class="flex justify-between items-center py-2.5 text-xs">
                <div class="flex-1 pr-2">
                    <strong class="text-[#241C15]">${item.qtd}x ${item.nome}</strong>
                    ${item.adicionais_texto ? `<div class="text-[11px] text-[#5A4E40] mt-0.5">🍕 ${item.adicionais_texto}</div>` : ''}
                    ${item.obs_item ? `<div class="text-[11px] text-[#E3A73A] mt-0.5">📝 ${item.obs_item}</div>` : ''}
                </div>
                <div class="flex items-center gap-2.5 flex-shrink-0">
                    <strong class="text-[#8E2C22]">R$ ${(item.preco_total_unitario * item.qtd).toFixed(2).replace('.', ',')}</strong>
                    <button type="button" onclick="removerItemDoCarrinho(${index})" class="text-[#B23A2E] text-base font-bold leading-none hover:opacity-75">&times;</button>
                </div>
            </div>
        `;
    });

    document.getElementById('gaveta-fundo').classList.add('aberta');
    document.getElementById('gaveta-carrinho').classList.add('aberta');
    atualizarTotais();
}

function fecharTodasGavetas() {
    document.getElementById('gaveta-fundo').classList.remove('aberta');
    document.getElementById('gaveta-adicionais').classList.remove('aberta');
    document.getElementById('gaveta-pizza').classList.remove('aberta');
    document.getElementById('gaveta-carrinho').classList.remove('aberta');
}

function alternarTroco() {
    const forma = document.getElementById('forma-pagamento').value;
    document.getElementById('bloco-troco').classList.toggle('hidden', forma !== 'dinheiro');
}

function atualizarTotais() {
    const tipo = document.getElementById('tipo-entrega').value;
    const subtotal = carrinho.reduce((acc, c) => acc + (c.preco_total_unitario * c.qtd), 0);
    let taxa = 0;

    if (tipo === 'delivery') {
        taxa = parseFloat(dadosEstabelecimento?.taxa_entrega_padrao || 0);
        document.getElementById('bloco-endereco').classList.remove('hidden');
        document.getElementById('linha-taxa').classList.remove('hidden');
        document.getElementById('linha-taxa').classList.add('flex');
    } else {
        document.getElementById('bloco-endereco').classList.add('hidden');
        document.getElementById('linha-taxa').classList.add('hidden');
        document.getElementById('linha-taxa').classList.remove('flex');
    }

    const total = subtotal + taxa;
    document.getElementById('modal-subtotal').innerText = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
    document.getElementById('modal-taxa').innerText = `R$ ${taxa.toFixed(2).replace('.', ',')}`;
    document.getElementById('modal-total').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
}

async function finalizarPedido(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Registrando Pedido...';

    const tipoEntrega = document.getElementById('tipo-entrega').value;
    const subtotal = carrinho.reduce((acc, c) => acc + (c.preco_total_unitario * c.qtd), 0);
    const taxa = tipoEntrega === 'delivery' ? parseFloat(dadosEstabelecimento.taxa_entrega_padrao) : 0;
    const total = subtotal + taxa;

    const payload = {
        estabelecimento_id: estabId,
        cliente: {
            nome: document.getElementById('cli-nome').value,
            whatsapp: document.getElementById('cli-whatsapp').value,
            endereco: tipoEntrega === 'delivery' ? document.getElementById('cli-endereco').value : 'Retirada no Balcão',
            bairro: tipoEntrega === 'delivery' ? document.getElementById('cli-bairro').value : '',
            complemento: tipoEntrega === 'delivery' ? document.getElementById('cli-complemento').value : ''
        },
        tipo_entrega: tipoEntrega,
        taxa_entrega: taxa,
        subtotal: subtotal,
        total: total,
        forma_pagamento: document.getElementById('forma-pagamento').value,
        troco_para: document.getElementById('troco-para').value || null,
        observacoes: document.getElementById('obs-pedido').value,
        itens: carrinho
    };

    try {
        const res = await fetch('api/criar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const resposta = await res.json();

        if (resposta.sucesso) {
            fecharTodasGavetas();
            carrinho = [];
            atualizarInterface();

            Swal.fire({
                title: 'Pedido Confirmado! 🎉',
                html: `Seu pedido <b>#${resposta.pedido_id}</b> foi registrado e já está na fila de preparo!`,
                icon: 'success',
                confirmButtonColor: '#B23A2E',
                confirmButtonText: 'Acompanhar Pedido',
                allowOutsideClick: false
            }).then(() => {
                window.location.href = `acompanhar.php?id=${resposta.pedido_id}`;
            });

        } else {
            Swal.fire('Atenção', resposta.erro, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Concluir Pedido';
        }
    } catch (err) {
        Swal.fire('Erro', 'Erro ao conectar ao servidor.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Concluir Pedido';
    }
}

carregarCardapio();