<?php

use App\Http\Middleware\PainelAcesso;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['painel' => PainelAcesso::class]);
        $middleware->redirectGuestsTo(fn () => route('painel.entrar'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();
