<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_cria_dados_consistentes_e_limpa_tudo(): void
    {
        Storage::fake('public');

        $this->artisan('demo:criar')->assertSuccessful();

        $campanha = Campaign::where('slug', 'bmw-135i-estoril-blue')->firstOrFail();
        $this->assertSame($campanha->sold_count, Ticket::where('campaign_id', $campanha->id)->count());
        $this->assertSame($campanha->sold_count, (int) Order::where('campaign_id', $campanha->id)->sum('quantity'));
        $this->get('/campanha/bmw-135i-estoril-blue')->assertOk()->assertSee('Ambiente de demonstração');

        $this->artisan('demo:limpar')->assertSuccessful();

        $this->assertSame(0, Campaign::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Ticket::count());
    }

    public function test_demo_nao_roda_em_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('demo:criar')->assertFailed();
        $this->assertSame(0, Campaign::count());
    }
}
