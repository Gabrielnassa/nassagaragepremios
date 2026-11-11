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

## Testes

```bash
php artisan test
```

## Organização

- `app/Models` — campanhas, pedidos, pagamentos, números e clientes.
- `lang/pt_BR/termos.php` — vocabulário da interface ("números" ou "títulos"), escolhido pela configuração `vocabulario`.
- `resources/views` — páginas em Blade.
- `public/assets` — CSS, JavaScript e fontes, sem etapa de build.
