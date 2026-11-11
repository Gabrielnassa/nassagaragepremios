(function () {
    'use strict';

    var calmo = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    function todos(seletor, raiz) {
        return Array.prototype.slice.call((raiz || document).querySelectorAll(seletor));
    }

    /* Diálogos: menu e checkout */

    todos('[data-abre]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById(botao.dataset.abre).showModal();
        });
    });

    todos('dialog').forEach(function (dialogo) {
        dialogo.addEventListener('click', function (evento) {
            if (evento.target === dialogo || evento.target.closest('[data-fecha]')) {
                dialogo.close();
            }
        });
    });

    /* Galeria */

    todos('[data-galeria]').forEach(function (galeria) {
        var trilho = galeria.querySelector('[data-trilho]');
        var anterior = galeria.querySelector('[data-anterior]');
        var proxima = galeria.querySelector('[data-proxima]');
        var atual = galeria.querySelector('[data-atual]');
        var total = trilho.children.length;
        var agendado = false;

        if (!anterior) {
            return;
        }

        function indice() {
            return Math.round(trilho.scrollLeft / trilho.clientWidth);
        }

        function atualizar() {
            agendado = false;
            var i = indice();
            atual.textContent = String(i + 1);
            anterior.disabled = i === 0;
            proxima.disabled = i === total - 1;
        }

        function ir(passo) {
            trilho.scrollTo({ left: (indice() + passo) * trilho.clientWidth, behavior: calmo ? 'auto' : 'smooth' });
        }

        anterior.addEventListener('click', function () { ir(-1); });
        proxima.addEventListener('click', function () { ir(1); });
        trilho.addEventListener('keydown', function (evento) {
            if (evento.key === 'ArrowLeft') { ir(-1); }
            if (evento.key === 'ArrowRight') { ir(1); }
        });
        trilho.addEventListener('scroll', function () {
            if (!agendado) {
                agendado = true;
                window.requestAnimationFrame(atualizar);
            }
        }, { passive: true });
        atualizar();
    });

    /* Filtro dos prêmios instantâneos */

    todos('[data-filtro]').forEach(function (secao) {
        var botoes = todos('[data-ver]', secao);
        botoes.forEach(function (botao) {
            botao.addEventListener('click', function () {
                botoes.forEach(function (b) { b.setAttribute('aria-pressed', String(b === botao)); });
                todos('[data-tipo]', secao).forEach(function (item) {
                    item.hidden = botao.dataset.ver !== 'todos' && item.dataset.tipo !== botao.dataset.ver;
                });
            });
        });
    });

    /* Contador */

    function doisDigitos(n) {
        return n < 10 ? '0' + n : String(n);
    }

    todos('[data-contador]').forEach(function (relogio) {
        var fim = new Date(relogio.dataset.contador).getTime();
        var timer;

        function tique() {
            var resto = Math.max(0, Math.floor((fim - Date.now()) / 1000));
            relogio.textContent = doisDigitos(Math.floor(resto / 3600)) + ':' + doisDigitos(Math.floor(resto % 3600 / 60)) + ':' + doisDigitos(resto % 60);
            if (resto === 0) {
                window.clearInterval(timer);
            }
        }

        timer = window.setInterval(tique, 1000);
        tique();
    });

    /* Página da campanha */

    var campanha = document.querySelector('[data-campanha]');
    if (!campanha) {
        return;
    }

    var barra = campanha.querySelector('[data-barra]');

    function animarBarra() {
        var alvo = Number(barra.getAttribute('value'));
        var inicio = null;
        barra.value = 0;

        function quadro(agora) {
            inicio = inicio || agora;
            var t = Math.min(1, (agora - inicio) / 700);
            barra.value = alvo * (1 - Math.pow(1 - t, 3));
            if (t < 1) {
                window.requestAnimationFrame(quadro);
            }
        }

        window.requestAnimationFrame(quadro);
    }

    if (barra && !calmo) {
        animarBarra();
    }

    var pedido = campanha.querySelector('[data-pedido]');
    var preco = Number(campanha.dataset.preco);
    var minimo = Number(campanha.dataset.min);
    var maximo = Number(campanha.dataset.max);
    var quantidade = minimo;

    if (pedido) {
        var campo = pedido.querySelector('#quantidade');
        var menos = pedido.querySelector('[data-menos]');
        var mais = pedido.querySelector('[data-mais]');
        var limites = pedido.querySelector('[data-limites]');
        var textoLimites = limites.textContent;
        var checkout = document.getElementById('checkout');
        var fixa = document.querySelector('[data-barra-fixa]');
        var cta = pedido.querySelector('[data-cta]');

        var definir = function (valor, escrever) {
            var pedida = isNaN(valor) ? minimo : valor;
            quantidade = Math.min(maximo, Math.max(minimo, pedida));

            if (escrever !== false) {
                campo.value = String(quantidade);
            }
            menos.disabled = quantidade <= minimo;
            mais.disabled = quantidade >= maximo;
            todos('[data-soma]', pedido).forEach(function (b) { b.disabled = quantidade >= maximo; });

            if (pedida > maximo) {
                limites.textContent = 'O máximo por pedido é ' + maximo + '. Ajustamos a quantidade.';
                limites.classList.add('dica--erro');
            } else {
                limites.textContent = textoLimites;
                limites.classList.remove('dica--erro');
            }

            var total = moeda.format(quantidade * preco / 100);
            todos('[data-total]').forEach(function (el) { el.textContent = total; });
            todos('[data-resumo]').forEach(function (el) { el.textContent = quantidade + ' × ' + moeda.format(preco / 100); });
            todos('[data-resumo-qtd]').forEach(function (el) { el.textContent = quantidade + (quantidade === 1 ? ' unidade' : ' unidades'); });
        };

        var abrirCheckout = function () {
            definir(quantidade);
            checkout.showModal();
            checkout.querySelector('#telefone').focus();
        };

        menos.addEventListener('click', function () { definir(quantidade - 1); });
        mais.addEventListener('click', function () { definir(quantidade + 1); });
        todos('[data-soma]', pedido).forEach(function (botao) {
            botao.addEventListener('click', function () { definir(quantidade + Number(botao.dataset.soma)); });
        });
        campo.addEventListener('input', function () {
            campo.value = campo.value.replace(/\D/g, '').slice(0, 6);
            if (campo.value !== '') {
                definir(Number(campo.value), false);
            }
        });
        campo.addEventListener('blur', function () { definir(Number(campo.value || minimo)); });
        campo.addEventListener('focus', function () { campo.select(); });

        pedido.addEventListener('submit', function (evento) {
            evento.preventDefault();
            abrirCheckout();
        });
        document.querySelector('[data-cta-fixo]').addEventListener('click', abrirCheckout);

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entradas) {
                var visivel = entradas[0].isIntersecting;
                fixa.hidden = visivel;
                document.body.classList.toggle('com-barra', !visivel);
            }).observe(cta);
        }

        /* Checkout, passo do telefone */

        var formulario = checkout.querySelector('[data-checkout]');
        var telefone = formulario.querySelector('#telefone');
        var ajuda = formulario.querySelector('[data-telefone-ajuda]');
        var textoAjuda = ajuda.textContent;
        var aviso = formulario.querySelector('[data-checkout-aviso]');

        telefone.addEventListener('input', function () {
            var d = telefone.value.replace(/\D/g, '').slice(0, 11);
            var saida = d;
            if (d.length > 2) { saida = '(' + d.slice(0, 2) + ') ' + d.slice(2); }
            if (d.length > 6) {
                var corte = d.length === 11 ? 7 : 6;
                saida = '(' + d.slice(0, 2) + ') ' + d.slice(2, corte) + '-' + d.slice(corte);
            }
            telefone.value = saida;
            telefone.removeAttribute('aria-invalid');
            ajuda.textContent = textoAjuda;
            ajuda.classList.remove('dica--erro');
            aviso.hidden = true;
        });

        formulario.addEventListener('submit', function (evento) {
            evento.preventDefault();
            var d = telefone.value.replace(/\D/g, '');
            var valido = (d.length === 10 || d.length === 11) && Number(d.slice(0, 2)) >= 11;

            if (!valido) {
                telefone.setAttribute('aria-invalid', 'true');
                ajuda.textContent = 'Digite o DDD e o número, por exemplo (11) 90000-0000.';
                ajuda.classList.add('dica--erro');
                telefone.focus();
                return;
            }

            aviso.hidden = false;
        });

        definir(quantidade);
    }

    /* Atualização periódica: percentual, estoque e líder */

    var percentual = campanha.querySelector('[data-percentual]');
    var restantes = campanha.querySelector('[data-restantes]');
    var lider = campanha.querySelector('[data-lider]');
    var semLider = campanha.querySelector('[data-sem-lider]');

    function aplicar(dados) {
        if (percentual) { percentual.textContent = dados.percent_label; }
        if (restantes) { restantes.textContent = dados.remaining_label; }
        if (barra) { barra.value = dados.percent; }

        if (pedido && dados.max !== maximo) {
            if (dados.max === 0) {
                window.location.reload();
                return;
            }
            maximo = dados.max;
        }

        if (lider) {
            lider.hidden = !dados.leader;
            semLider.hidden = !!dados.leader;
            if (dados.leader) {
                lider.querySelector('[data-lider-valor]').textContent = dados.leader.value;
                lider.querySelector('[data-lider-nome]').textContent = dados.leader.name;
                lider.querySelector('[data-lider-detalhe]').textContent = [dados.leader.location, dados.leader.ago].filter(Boolean).join(' · ');
            }
        }
    }

    function consultar() {
        if (document.hidden) {
            return;
        }
        fetch(campanha.dataset.aoVivo, { headers: { Accept: 'application/json' } })
            .then(function (resposta) { return resposta.ok ? resposta.json() : null; })
            .then(function (dados) { if (dados) { aplicar(dados); } })
            .catch(function () {});
    }

    if (pedido || lider) {
        window.setInterval(consultar, 20000);
        document.addEventListener('visibilitychange', consultar);
    }
})();
