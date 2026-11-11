(function () {
    'use strict';

    var calmo = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    function todos(seletor, raiz) {
        return Array.prototype.slice.call((raiz || document).querySelectorAll(seletor));
    }

    function doisDigitos(n) {
        return n < 10 ? '0' + n : String(n);
    }

    /* Diálogos: menu e checkout */

    todos('[data-abre]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById(botao.dataset.abre).showModal();
        });
    });

    todos('dialog').forEach(function (dialogo) {
        dialogo.addEventListener('click', function (evento) {
            if (evento.target === dialogo || evento.target.closest('[data-fecha]') || evento.target.closest('a[href]')) {
                dialogo.close();
            }
        });
    });

    /* Máscaras */

    var mascaras = {
        cpf: function (d) {
            d = d.slice(0, 11);
            return d.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1-$2');
        },
        data: function (d) {
            d = d.slice(0, 8);
            return d.replace(/^(\d{2})(\d)/, '$1/$2').replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
        },
        telefone: function (d) {
            d = d.slice(0, 11);
            if (d.length <= 2) { return d; }
            if (d.length <= 6) { return '(' + d.slice(0, 2) + ') ' + d.slice(2); }
            var corte = d.length === 11 ? 7 : 6;
            return '(' + d.slice(0, 2) + ') ' + d.slice(2, corte) + '-' + d.slice(corte);
        }
    };

    todos('[data-mascara]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            campo.value = mascaras[campo.dataset.mascara](campo.value.replace(/\D/g, ''));
            campo.removeAttribute('aria-invalid');
        });
    });

    /* Luzes de progresso */

    function acender(luzes, quantas, animar) {
        todos('i', luzes).forEach(function (luz, i) {
            if (animar && i < quantas) {
                luz.classList.remove('on');
                window.setTimeout(function () { luz.classList.add('on'); }, 150 + i * 45);
            } else {
                luz.classList.toggle('on', i < quantas);
            }
        });
    }

    todos('[data-luzes]').forEach(function (luzes) {
        if (!calmo) {
            acender(luzes, Number(luzes.dataset.luzes), true);
        }
    });

    /* Galeria */

    todos('[data-galeria]').forEach(function (galeria) {
        var trilho = galeria.querySelector('[data-trilho]');
        var anterior = galeria.querySelector('[data-anterior]');
        var proxima = galeria.querySelector('[data-proxima]');
        var atual = galeria.querySelector('[data-atual]');
        var miniaturas = todos('[data-ir]', galeria);
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
            miniaturas.forEach(function (m, n) {
                if (n === i) { m.setAttribute('aria-current', 'true'); } else { m.removeAttribute('aria-current'); }
            });
        }

        function ir(alvo) {
            trilho.scrollTo({ left: alvo * trilho.clientWidth, behavior: calmo ? 'auto' : 'smooth' });
        }

        anterior.addEventListener('click', function () { ir(indice() - 1); });
        proxima.addEventListener('click', function () { ir(indice() + 1); });
        miniaturas.forEach(function (m) {
            m.addEventListener('click', function () { ir(Number(m.dataset.ir)); });
        });
        trilho.addEventListener('keydown', function (evento) {
            if (evento.key === 'ArrowLeft') { ir(indice() - 1); }
            if (evento.key === 'ArrowRight') { ir(indice() + 1); }
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

    todos('[data-contador]').forEach(function (relogio) {
        var fim = new Date(relogio.dataset.contador).getTime();
        var partes = {
            dias: relogio.querySelector('[data-dias]'),
            horas: relogio.querySelector('[data-horas]'),
            minutos: relogio.querySelector('[data-minutos]'),
            segundos: relogio.querySelector('[data-segundos]')
        };
        var timer;

        function tique() {
            var resto = Math.max(0, Math.floor((fim - Date.now()) / 1000));
            partes.dias.textContent = doisDigitos(Math.floor(resto / 86400));
            partes.horas.textContent = doisDigitos(Math.floor(resto % 86400 / 3600));
            partes.minutos.textContent = doisDigitos(Math.floor(resto % 3600 / 60));
            partes.segundos.textContent = doisDigitos(resto % 60);
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

        /* Checkout em passos */

        var formulario = checkout.querySelector('[data-checkout]');
        var telefone = formulario.querySelector('#telefone');
        var token = document.querySelector('meta[name="csrf-token"]');
        var passo = 'telefone';
        var enviando = false;

        var opcoes = function (dados) {
            return {};
        };

        var limparErros = function () {
            todos('[data-erro-de]', formulario).forEach(function (el) {
                if (el.dataset.padrao) {
                    el.textContent = el.dataset.padrao;
                    el.classList.remove('dica--erro');
                } else {
                    el.textContent = '';
                    el.hidden = true;
                }
            });
            todos('[aria-invalid]', formulario).forEach(function (el) { el.removeAttribute('aria-invalid'); });
        };

        var mostrarErro = function (campo, mensagem) {
            var etapa = formulario.querySelector('[data-passo="' + passo + '"]');
            var alvo = etapa.querySelector('[data-erro-de="' + campo + '"]') || formulario.querySelector('[data-erro-de="geral"]');
            var entrada = etapa.querySelector('[name="' + campo + '"]');

            alvo.textContent = mensagem;
            alvo.hidden = false;
            if (alvo.classList.contains('dica')) {
                alvo.classList.add('dica--erro');
            }
            if (entrada) {
                entrada.setAttribute('aria-invalid', 'true');
                entrada.focus();
            }
        };

        var mostrarPasso = function (nome) {
            passo = nome;
            limparErros();
            todos('[data-passo]', formulario).forEach(function (el) { el.hidden = el.dataset.passo !== nome; });
            var foco = formulario.querySelector('[data-passo="' + nome + '"] input, [data-passo="' + nome + '"] button');
            if (foco) {
                foco.focus();
            }
        };

        var ocupado = function (sim) {
            enviando = sim;
            formulario.setAttribute('aria-busy', String(sim));
            todos('button[type="submit"]', formulario).forEach(function (b) { b.disabled = sim; });
        };

        telefone.addEventListener('input', function () {
            telefone.value = mascaras.telefone(telefone.value.replace(/\D/g, ''));
            limparErros();
        });

        todos('[data-voltar]', formulario).forEach(function (botao) {
            botao.addEventListener('click', function () { mostrarPasso('telefone'); });
        });

        formulario.addEventListener('submit', function (evento) {
            evento.preventDefault();
            if (enviando) {
                return;
            }
            limparErros();

            var digitos = telefone.value.replace(/\D/g, '');
            if (!((digitos.length === 10 || digitos.length === 11) && Number(digitos.slice(0, 2)) >= 11)) {
                mostrarPasso('telefone');
                mostrarErro('telefone', 'Digite o DDD e o número, por exemplo (11) 90000-0000.');
                return;
            }

            var dados = { telefone: digitos, quantidade: quantidade };
            if (passo === 'novo') {
                dados.nome = formulario.querySelector('#nome').value;
                dados.cpf = formulario.querySelector('#cpf-compra').value;
                dados.nascimento = formulario.querySelector('#nascimento-compra').value;
                dados.email = formulario.querySelector('#email').value;
                dados.aceite = formulario.querySelector('#aceite').checked;
            }

            ocupado(true);
            fetch(passo === 'telefone' ? formulario.dataset.identificar : formulario.dataset.criar, opcoes(dados))
                .then(function (resposta) {
                    return resposta.json().catch(function () { return {}; }).then(function (corpo) {
                        return { ok: resposta.ok, status: resposta.status, corpo: corpo };
                    });
                })
                .then(function (r) {
                    if (r.ok && r.corpo.url) {
                        window.location.href = r.corpo.url;
                        return;
                    }
                    ocupado(false);
                    if (r.ok && passo === 'telefone') {
                        formulario.querySelector('[data-nome]').textContent = r.corpo.nome || '';
                        mostrarPasso(r.corpo.conhecido ? 'conhecido' : 'novo');
                    } else if (r.status === 429) {
                        mostrarErro('geral', 'Muitas tentativas seguidas. Espere um minuto e tente de novo.');
                    } else if (r.status === 419) {
                        mostrarErro('geral', 'A página ficou aberta por muito tempo. Recarregue e tente de novo.');
                    } else {
                        mostrarErro(r.corpo.campo || 'geral', r.corpo.mensagem || 'Não foi possível continuar. Tente de novo em instantes.');
                    }
                })
                .catch(function () {
                    ocupado(false);
                    mostrarErro('geral', 'Sem resposta do servidor. Confira sua internet e tente de novo.');
                });
        });

        definir(quantidade);
    }

    /* Atualização periódica: percentual, estoque e líder */

    var percentual = campanha.querySelector('[data-percentual]');
    var restantes = campanha.querySelector('[data-restantes]');
    var luzes = campanha.querySelector('[data-luzes]');
    var lider = campanha.querySelector('[data-lider]');
    var semLider = campanha.querySelector('[data-sem-lider]');

    function aplicar(dados) {
        if (percentual) { percentual.textContent = dados.percent_label; }
        if (restantes) { restantes.textContent = dados.remaining_label; }
        if (luzes) { acender(luzes, Math.max(dados.percent > 0 ? 1 : 0, Math.floor(dados.percent / 5)), false); }

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
