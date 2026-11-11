# Nassa Garage — campanhas

Site de campanhas da Nassa Garage: página da campanha, compra por Pix, consulta de números e painel de operação. Laravel 13, PHP 8.3 e MySQL.

## Rodar localmente

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Para desenvolver sem MySQL, troque no `.env` para `DB_CONNECTION=sqlite`, `APP_ENV=local` e `APP_URL=http://127.0.0.1:8000`, e crie o arquivo `database/database.sqlite`.

## Dados de demonstração

```bash
php artisan demo:criar
```

```bash
php artisan demo:limpar
```

O primeiro comando cria campanhas, clientes e pedidos fictícios e se recusa a rodar em produção. O segundo remove tudo o que foi criado.

## Painel

O painel fica em `/painel`. O primeiro usuário é criado pela linha de comando; o segundo fator (aplicativo autenticador) é configurado no primeiro acesso.

```bash
php artisan painel:usuario voce@exemplo.com --nome="Seu nome" --papel=dono
```

Perfis: `dono` (tudo), `operador` (campanhas e pedidos, sem mudar status, apurar, estornar ou configurar) e `leitura`. Para refazer o segundo fator de alguém, rode o mesmo comando com `--zerar-2fa`.

## Pagamento por Pix

O provedor fica atrás da interface `App\Pix\PixProvider` e é escolhido por `PIX_PROVIDER` no `.env`.

- `simulado`: provedor de teste, com um botão na tela do pedido para simular a confirmação. Não movimenta dinheiro e é recusado em produção.
- Sem provedor configurado, o site funciona, mas o checkout responde que o pagamento está indisponível.

As notificações do provedor chegam em `POST /webhooks/pix`, com assinatura validada por `PIX_WEBHOOK_SECRET`.

## Tarefas agendadas

Um único cron por minuto cuida da expiração de pedidos e da conciliação com o provedor:

```bash
php artisan schedule:run
```

Para rodar à mão:

```bash
php artisan pedidos:expirar
```

```bash
php artisan pedidos:conciliar
```

## Testes

```bash
php artisan test
```

O teste de disputa abre vários pedidos ao mesmo tempo sobre os últimos números de uma campanha e confere que nenhum número se repete. Ele precisa de um banco MySQL já migrado e que não seja o de produção:

```bash
php tests/carga/disputa.php 200 50
```

## Organização

- `app/Models` — campanhas, pedidos, pagamentos, números e clientes.
- `app/Services` — criação do pedido, confirmação de pagamento e atribuição dos números.
- `app/Pix` — interface do provedor de Pix e o provedor de teste.
- `lang/pt_BR/termos.php` — vocabulário da interface ("números" ou "títulos"), escolhido pela configuração `vocabulario`.
- `resources/views` — páginas em Blade.
- `public/assets` — CSS, JavaScript e fontes, sem etapa de build.
