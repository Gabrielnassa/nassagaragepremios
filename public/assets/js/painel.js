(function () {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirma]'), function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (!window.confirm(formulario.dataset.confirma)) {
                evento.preventDefault();
            }
        });
    });

    var tela = document.querySelector('canvas[data-qr]');
    if (tela && window.qrcode) {
        var qr = window.qrcode(0, 'M');
        qr.addData(tela.dataset.qr);
        qr.make();

        var modulos = qr.getModuleCount();
        var margem = 2;
        var escala = Math.max(4, Math.floor(800 / (modulos + margem * 2)));
        var lado = escala * (modulos + margem * 2);
        var ctx = tela.getContext('2d');

        tela.width = lado;
        tela.height = lado;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, lado, lado);
        ctx.fillStyle = '#000000';
        for (var linha = 0; linha < modulos; linha++) {
            for (var coluna = 0; coluna < modulos; coluna++) {
                if (qr.isDark(linha, coluna)) {
                    ctx.fillRect((coluna + margem) * escala, (linha + margem) * escala, escala, escala);
                }
            }
        }
    }
})();
