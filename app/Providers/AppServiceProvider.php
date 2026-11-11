<?php

namespace App\Providers;

use App\Pix\PixProvider;
use App\Pix\SimuladoProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PixProvider::class, function () {
            $provider = config('pix.provider');

            if ($provider === 'simulado' && ! $this->app->isProduction()) {
                return new SimuladoProvider((string) config('pix.webhook_secret'));
            }

            throw new RuntimeException('Nenhum provedor de Pix configurado.');
        });
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());

        $limits = ['ao-vivo' => 60, 'identificar' => 12, 'pedido' => 8, 'status' => 90, 'consulta' => 8, 'webhook' => 300];

        foreach ($limits as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)->by($name.'|'.$request->ip()));
        }
    }
}
