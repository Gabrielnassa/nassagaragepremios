<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PixWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/campanha/{campaign:slug}', [CampaignController::class, 'show'])->name('campanha');
Route::get('/campanha/{campaign:slug}/ao-vivo', [CampaignController::class, 'live'])
    ->middleware('throttle:ao-vivo')
    ->name('campanha.ao-vivo');

Route::post('/checkout/identificar', [CheckoutController::class, 'identificar'])
    ->middleware('throttle:identificar')
    ->name('checkout.identificar');
Route::post('/campanha/{campaign:slug}/pedido', [CheckoutController::class, 'criar'])
    ->middleware('throttle:pedido')
    ->name('checkout.criar');

Route::get('/pedido/{code}', [PedidoController::class, 'show'])->name('pedido');
Route::get('/pedido/{code}/status', [PedidoController::class, 'status'])
    ->middleware('throttle:status')
    ->name('pedido.status');
Route::post('/pedido/{code}/simular', [PedidoController::class, 'simular'])->name('pedido.simular');

Route::get('/meus-numeros', [ConsultaController::class, 'form'])->name('consulta');
Route::post('/meus-numeros', [ConsultaController::class, 'buscar'])
    ->middleware('throttle:consulta')
    ->name('consulta.buscar');

Route::post('/webhooks/pix', PixWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->middleware('throttle:webhook')
    ->name('webhook.pix');
