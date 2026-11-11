<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function form(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('painel.inicio') : view('painel.entrar');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $key = 'painel-login|'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Espere '.RateLimiter::availableIn($key).' segundos e tente de novo.']);
        }

        if (! Auth::attempt(['email' => $email, 'password' => (string) $request->input('senha'), 'active' => true])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('painel.2fa', false);

        return redirect()->route('painel.verificacao');
    }

    public function verificacao(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('painel.entrar');
        }
        if ($request->session()->get('painel.2fa')) {
            return redirect()->route('painel.inicio');
        }

        if (! $user->totp_confirmed_at) {
            if (! $user->totp_secret) {
                $user->forceFill(['totp_secret' => Totp::secret()])->save();
            }

            return view('painel.verificacao', [
                'configurar' => true,
                'segredo' => $user->totp_secret,
                'uri' => Totp::uri($user->totp_secret, $user->email, config('app.name')),
            ]);
        }

        return view('painel.verificacao', ['configurar' => false]);
    }

    public function verificar(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->totp_secret, 403);

        $key = 'painel-2fa|'.$user->id;
        $code = preg_replace('/\D/', '', (string) $request->input('codigo'));
        $used = 'painel-2fa-usado|'.$user->id.'|'.$code;

        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages(['codigo' => 'Muitas tentativas. Espere um minuto e tente de novo.']);
        }

        if (Cache::has($used) || ! Totp::verify($user->totp_secret, $code)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['codigo' => 'Código incorreto. Confira o aplicativo e digite os 6 dígitos atuais.']);
        }

        Cache::put($used, true, 120);
        RateLimiter::clear($key);

        $user->forceFill(['totp_confirmed_at' => $user->totp_confirmed_at ?? now(), 'last_login_at' => now()])->save();
        $request->session()->regenerate();
        $request->session()->put('painel.2fa', true);
        Auditoria::registrar('login');

        return redirect()->intended(route('painel.inicio'));
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('painel.entrar');
    }
}
