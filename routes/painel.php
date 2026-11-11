<?php

use App\Http\Controllers\Painel\ApuracaoController;
use App\Http\Controllers\Painel\AuthController;
use App\Http\Controllers\Painel\CampanhaController;
use App\Http\Controllers\Painel\ConfiguracaoController;
use App\Http\Controllers\Painel\DashboardController;
use App\Http\Controllers\Painel\PedidoController;
use Illuminate\Support\Facades\Route;

Route::prefix('painel')->name('painel.')->group(function () {
    Route::get('/entrar', [AuthController::class, 'form'])->name('entrar');
    Route::post('/entrar', [AuthController::class, 'entrar']);
    Route::get('/verificacao', [AuthController::class, 'verificacao'])->name('verificacao');
    Route::post('/verificacao', [AuthController::class, 'verificar']);
    Route::post('/sair', [AuthController::class, 'sair'])->name('sair');

    Route::middleware('painel')->group(function () {
        Route::get('/', DashboardController::class)->name('inicio');
        Route::get('/campanhas', [CampanhaController::class, 'index'])->name('campanhas.index');
        Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
        Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
    });

    Route::middleware('painel:operador')->group(function () {
        Route::get('/campanhas/nova', [CampanhaController::class, 'create'])->name('campanhas.create');
        Route::post('/campanhas', [CampanhaController::class, 'store'])->name('campanhas.store');
        Route::get('/campanhas/{campanha}', [CampanhaController::class, 'edit'])->name('campanhas.edit');
        Route::put('/campanhas/{campanha}', [CampanhaController::class, 'update'])->name('campanhas.update');
        Route::post('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->name('pedidos.cancelar');
        Route::get('/campanhas/{campanha}/apuracao', [ApuracaoController::class, 'show'])->name('apuracao');
    });

    Route::middleware('painel:dono')->group(function () {
        Route::post('/campanhas/{campanha}/status', [CampanhaController::class, 'status'])->name('campanhas.status');
        Route::post('/campanhas/{campanha}/apuracao', [ApuracaoController::class, 'registrar'])->name('apuracao.registrar');
        Route::post('/campanhas/{campanha}/apuracao/publicar', [ApuracaoController::class, 'publicar'])->name('apuracao.publicar');
        Route::post('/pedidos/{pedido}/estornar', [PedidoController::class, 'estornar'])->name('pedidos.estornar');
        Route::get('/configuracoes', [ConfiguracaoController::class, 'edit'])->name('configuracoes');
        Route::put('/configuracoes', [ConfiguracaoController::class, 'update']);
    });
});
