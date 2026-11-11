(function () {
    'use strict';

    var pix = document.querySelector('[data-pix]');
    if (!pix) {
        return;
    }

    var codigo = pix.querySelector('[data-codigo]');
    var texto = codigo.value.trim();

    /* QR code */

    var tela = pix.querySelector('[data-qr]');
    var qr = window.qrcode(0, 'M');
    qr.addData(texto);
    qr.make();

    var modulos = qr.getModuleCount();
    var margem = 3;
    var escala = Math.max(4, Math.floor(960 / (modulos + margem * 2)));
    var lado = escala * (modulos + margem * 2);
    var ctx = tela.getContext('2d');

    tela.width = lado;
    tela.height = lado;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, lado, lado);
    ctx.fillStyle = '#0b0c0e';
    for (var linha = 0; linha < modulos; linha++) {
        for (var coluna = 0; coluna < modulos; coluna++) {
            if (qr.isDark(linha, coluna)) {
                ctx.fillRect((coluna + margem) * escala, (linha + margem) * escala, escala, escala);
            }
        }
    }

    /* Copiar */

    var botao = pix.querySelector('[data-copiar]');
    var rotulo = pix.querySelector('[data-copiar-rotulo]');
    var rotuloPadrao = rotulo.textContent;
    var volta;

    function copiado(deuCerto) {
        rotulo.textContent = deuCerto ? 'Código copiado' : 'Selecione o código e copie';
        botao.classList.toggle('cta--feito', deuCerto);
        window.clearTimeout(volta);
        volta = window.setTimeout(function () {
            rotulo.textContent = rotuloPadrao;
            botao.classList.remove('cta--feito');
        }, 2600);
    }

    function copiarAntigo() {
        codigo.focus();
        codigo.select();
        try {
            copiado(document.execCommand('copy'));
        } catch (erro) {
            copiado(false);
        }
    }

    botao.addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(function () { copiado(true); }, copiarAntigo);
        } else {
            copiarAntigo();
        }
    });

    codigo.addEventListener('focus', function () { codigo.select(); });

    /* Prazo */

    var prazo = pix.querySelector('[data-prazo]');
    var fim = Date.now() + 15 * 60 * 1000;

    function doisDigitos(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function tique() {
        var resto = Math.max(0, Math.floor((fim - Date.now()) / 1000));
        prazo.textContent = doisDigitos(Math.floor(resto / 60)) + ':' + doisDigitos(resto % 60);
        pix.classList.toggle('pix--acabando', resto <= 120);
    }

    window.setInterval(tique, 1000);
    tique();

    /* Acompanhamento do pagamento */

    function consultar() {
        if (document.hidden) {
            return;
        }
        fetch(pix.dataset.status, { headers: { Accept: 'application/json' } })
            .then(function (resposta) { return resposta.ok ? resposta.json() : null; })
            .then(function (dados) {
                if (dados && dados.status !== 'pendente') {
                    window.location.reload();
                }
            })
            .catch(function () {});
    }

    window.setInterval(consultar, 4000);
    document.addEventListener('visibilitychange', consultar);
})();
