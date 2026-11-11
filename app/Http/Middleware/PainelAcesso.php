<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PainelAcesso
{
    public function handle(Request $request, Closure $next, string $papel = 'leitura'): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->active) {
            Auth::logout();

            return redirect()->route('painel.entrar');
        }

        if (! $request->session()->get('painel.2fa')) {
            return redirect()->route('painel.verificacao');
        }

        abort_unless($user->pode($papel), 403);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
