<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/campanha/{campaign:slug}', [CampaignController::class, 'show'])->name('campanha');
Route::get('/campanha/{campaign:slug}/ao-vivo', [CampaignController::class, 'live'])
    ->middleware('throttle:60,1')
    ->name('campanha.ao-vivo');

Route::get('/meus-numeros', [ConsultaController::class, 'form'])->name('consulta');
Route::post('/meus-numeros', [ConsultaController::class, 'buscar'])
    ->middleware('throttle:8,1')
    ->name('consulta.buscar');
