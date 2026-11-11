<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Winner;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    private const LEGAL = [
        'legal_company' => 'Entidade de Teste', 'legal_cnpj' => '11.222.333/0001-81', 'legal_modality' => 'sorteio_filantropico',
        'legal_process' => '00000.000000/0000-00', 'legal_beneficiary' => 'Entidade de Teste',
        'draw_criterion' => 'Critério de teste', 'delivery_deadline' => '30 dias',
    ];

    private function usuario(string $papel = 'dono'): AdminUser
    {
        return AdminUser::create(['name' => 'Teste', 'email' => "$papel@teste.local", 'password' => 'senha-de-teste-123', 'role' => $papel])->fresh();
    }

    private function logado(string $papel = 'dono'): AdminUser
    {
        $user = $this->usuario($papel);
        $this->actingAs($user)->withSession(['painel.2fa' => true]);

        return $user;
    }

    private function campanha(array $dados = []): Campaign
    {
        return Campaign::create($dados + ['slug' => 'teste', 'title' => 'Campanha de teste', 'status' => Campaign::RASCUNHO, 'price_cents' => 4990, 'total_numbers' => 100, 'digits' => 2]);
    }

    private function formulario(array $dados = []): array
    {
        return $dados + ['title' => 'BMW de teste', 'price' => '49,90', 'total_numbers' => 10000, 'min_per_order' => 1, 'max_per_order' => 100, 'reservation_minutes' => 15];
    }

    public function test_painel_exige_login_e_segundo_fator(): void
    {
        $this->get('/painel')->assertRedirect('/painel/entrar');

        $user = $this->usuario();
        $this->post('/painel/entrar', ['email' => $user->email, 'senha' => 'errada'])->assertSessionHasErrors('email');
        $this->post('/painel/entrar', ['email' => $user->email, 'senha' => 'senha-de-teste-123'])->assertRedirect('/painel/verificacao');

        $this->get('/painel')->assertRedirect('/painel/verificacao');
        $this->get('/painel/verificacao')->assertOk()->assertSee('QR code');

        $segredo = $user->fresh()->totp_secret;
        $this->post('/painel/verificacao', ['codigo' => '000000'])->assertSessionHasErrors('codigo');
        $this->get('/painel')->assertRedirect('/painel/verificacao');

        $this->post('/painel/verificacao', ['codigo' => Totp::code($segredo)])->assertRedirect('/painel');
        $this->get('/painel')->assertOk()->assertSee('Visão geral');
        $this->assertNotNull($user->fresh()->totp_confirmed_at);
        $this->assertSame('login', AuditLog::firstOrFail()->action);
    }

    public function test_login_bloqueia_apos_tentativas_erradas(): void
    {
        $user = $this->usuario();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/painel/entrar', ['email' => $user->email, 'senha' => 'errada']);
        }

        $this->post('/painel/entrar', ['email' => $user->email, 'senha' => 'senha-de-teste-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_perfis_limitam_as_acoes(): void
    {
        $campanha = $this->campanha();

        $this->logado('leitura');
        $this->get('/painel/campanhas')->assertOk();
        $this->get('/painel/campanhas/nova')->assertForbidden();
        $this->post('/painel/campanhas', $this->formulario())->assertForbidden();

        $this->logado('operador');
        $this->get('/painel/campanhas/nova')->assertOk();
        $this->post("/painel/campanhas/{$campanha->id}/status", ['status' => 'em_breve'])->assertForbidden();
        $this->get('/painel/configuracoes')->assertForbidden();
    }

    public function test_dono_cria_campanha_com_foto_premio_e_regulamento(): void
    {
        Storage::fake('public');
        $this->logado();

        $this->post('/painel/campanhas', $this->formulario(self::LEGAL + [
            'packages' => [1, 5, 10], 'featured' => 1,
            'prize_title' => ['BMW de teste', '', 'R$ 1.000 no Pix'],
            'photos' => [UploadedFile::fake()->image('carro.jpg', 2400, 1600)],
            'regulation' => UploadedFile::fake()->create('regulamento.pdf', 120, 'application/pdf'),
            'draw_at' => '2026-12-20T19:00',
        ]))->assertRedirect();

        $campanha = Campaign::firstOrFail();
        $this->assertSame([Campaign::RASCUNHO, 'bmw-de-teste', 4990, 10000, 4], [$campanha->status, $campanha->slug, $campanha->price_cents, $campanha->total_numbers, $campanha->digits]);
        $this->assertSame('11222333000181', $campanha->legal_cnpj);
        $this->assertSame('2026-12-20 22:00', $campanha->draw_at->format('Y-m-d H:i'));
        $this->assertSame([['qty' => 1, 'featured' => false], ['qty' => 5, 'featured' => true], ['qty' => 10, 'featured' => false]], $campanha->packages);
        $this->assertSame(['BMW de teste', 'R$ 1.000 no Pix'], $campanha->prizes->pluck('title')->all());

        $foto = $campanha->images()->firstOrFail();
        $this->assertSame(1600, $foto->width);
        $this->assertStringEndsWith('.webp', $foto->path);
        Storage::disk('public')->assertExists($foto->path);
        Storage::disk('public')->assertExists($campanha->legal_regulation_path);
        $this->assertSame([], $campanha->legalMissing());

        $this->get("/painel/campanhas/{$campanha->id}")->assertOk()->assertSee('Ativar vendas');
        $this->post("/painel/campanhas/{$campanha->id}/status", ['status' => 'ativa'])->assertSessionHas('ok');
        $this->assertSame(Campaign::ATIVA, $campanha->fresh()->status);
        $this->get('/campanha/bmw-de-teste')->assertOk()->assertSee('Entidade de Teste');
    }

    public function test_campanha_nao_ativa_sem_bloco_legal(): void
    {
        $this->logado();
        $campanha = $this->campanha();

        $this->post("/painel/campanhas/{$campanha->id}/status", ['status' => 'ativa'])->assertSessionHas('erro');
        $this->assertSame(Campaign::RASCUNHO, $campanha->fresh()->status);

        $this->post("/painel/campanhas/{$campanha->id}/status", ['status' => 'apurada'])->assertSessionHas('erro');
    }

    public function test_preco_e_total_travam_depois_de_ativar(): void
    {
        $this->logado();
        $campanha = $this->campanha(['status' => Campaign::ATIVA]);

        $this->put("/painel/campanhas/{$campanha->id}", $this->formulario(['title' => 'Novo título', 'price' => '1,00', 'total_numbers' => 50]))->assertRedirect();

        $this->assertSame(['Novo título', 4990, 100], [$campanha->fresh()->title, $campanha->fresh()->price_cents, $campanha->fresh()->total_numbers]);
    }

    private function venda(Campaign $campanha, int $numero, string $status = Order::PAGO): Order
    {
        $cliente = new Customer(['phone' => '1198765'.random_int(1000, 9999), 'name' => 'Fulano de Tal', 'birth_date' => '1990-03-05']);
        $cliente->setCpf('529.982.247-25');
        $cliente->save();

        $pedido = Order::create(['code' => 'ABCDEFGHJK', 'campaign_id' => $campanha->id, 'customer_id' => $cliente->id, 'quantity' => 1, 'amount_cents' => 4990, 'status' => $status, 'paid_at' => $status === Order::PAGO ? now() : null, 'expires_at' => now()->addMinutes(15)]);

        if ($status === Order::PAGO) {
            Ticket::create(['campaign_id' => $campanha->id, 'number' => $numero, 'order_id' => $pedido->id]);
            $pedido->payments()->create(['provider' => 'simulado', 'provider_id' => 'sim_teste', 'status' => 'pago', 'amount_cents' => 4990]);
            $campanha->update(['sold_count' => 1]);
        } else {
            $campanha->update(['reserved_count' => 1]);
        }

        return $pedido;
    }

    public function test_busca_de_pedidos_por_codigo_cpf_telefone_e_numero(): void
    {
        $this->logado('leitura');
        $campanha = $this->campanha(['status' => Campaign::ATIVA]);
        $pedido = $this->venda($campanha, 42);

        foreach (['abcdefghjk', '529.982.247-25', $pedido->customer->phone] as $termo) {
            $this->get('/painel/pedidos?q='.urlencode($termo))->assertOk()->assertSee('ABCDEFGHJK');
        }
        $this->get("/painel/pedidos?campanha={$campanha->id}&q=42")->assertSee('ABCDEFGHJK');
        $this->get("/painel/pedidos?campanha={$campanha->id}&q=43")->assertDontSee('ABCDEFGHJK');

        $this->get("/painel/pedidos/{$pedido->id}")->assertOk()->assertSee('529.982.247-25');
        $this->assertSame('pedido.visto', AuditLog::latest('id')->firstOrFail()->action);
    }

    public function test_cancelar_e_estornar_pedem_motivo_e_ajustam_estoque(): void
    {
        $this->logado();
        $campanha = $this->campanha(['status' => Campaign::ATIVA]);
        $pendente = $this->venda($campanha, 0, Order::PENDENTE);

        $this->post("/painel/pedidos/{$pendente->id}/cancelar", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post("/painel/pedidos/{$pendente->id}/cancelar", ['motivo' => 'Pedido duplicado'])->assertSessionHas('ok');
        $this->assertSame([Order::CANCELADO, 0], [$pendente->fresh()->status, $campanha->fresh()->reserved_count]);

        $pendente->delete();
        Customer::query()->delete();
        $pago = $this->venda($campanha, 7);

        $this->post("/painel/pedidos/{$pago->id}/estornar", ['motivo' => 'Pedido do cliente'])->assertSessionHas('ok');
        $this->assertSame([Order::ESTORNADO, 0, 0], [$pago->fresh()->status, $campanha->fresh()->sold_count, Ticket::count()]);
        $this->assertSame('pedido.estornado', AuditLog::latest('id')->firstOrFail()->action);
    }

    public function test_apuracao_registra_ganhador_e_so_publica_com_consentimento(): void
    {
        $this->logado();
        $campanha = $this->campanha(['status' => Campaign::ATIVA]);
        $campanha->prizes()->create(['position' => 1, 'title' => 'BMW de teste']);
        $this->venda($campanha, 42);

        $this->post("/painel/campanhas/{$campanha->id}/apuracao", ['numero' => 42, 'resultado' => 'Extração 1', 'confirmo' => 1])->assertSessionHas('erro');

        $campanha->update(['status' => Campaign::ENCERRADA]);
        $this->get("/painel/campanhas/{$campanha->id}/apuracao?numero=42&resultado=Extra")->assertOk()->assertSee('Fulano de Tal');
        $this->get("/painel/campanhas/{$campanha->id}/apuracao?numero=41&resultado=Extra")->assertSee('Nenhum pedido pago tem o número 41');

        $this->post("/painel/campanhas/{$campanha->id}/apuracao", ['numero' => 41, 'resultado' => 'Extração 1', 'confirmo' => 1])->assertSessionHas('erro');
        $this->post("/painel/campanhas/{$campanha->id}/apuracao", ['numero' => 42, 'resultado' => 'Extração 1', 'confirmo' => 1])->assertSessionHas('ok');

        $this->assertSame([Campaign::APURADA, 42], [$campanha->fresh()->status, $campanha->fresh()->winning_number]);
        $ganhador = Winner::firstOrFail();
        $this->assertSame(['Fulano T.', 'BMW de teste', null], [$ganhador->display_name, $ganhador->prize, $ganhador->published_at]);
        $this->get('/campanha/teste')->assertOk()->assertDontSee('Fulano T.');

        $this->post("/painel/campanhas/{$campanha->id}/apuracao/publicar", [])->assertSessionHasErrors('consentimento');
        $this->post("/painel/campanhas/{$campanha->id}/apuracao/publicar", ['consentimento' => 1])->assertSessionHas('ok');
        $this->get('/campanha/teste')->assertSee('Fulano T.')->assertDontSee('Fulano de Tal');
    }

    public function test_encerrar_vendas_libera_pedidos_pendentes(): void
    {
        $this->logado();
        $campanha = $this->campanha(['status' => Campaign::ATIVA]);
        $pendente = $this->venda($campanha, 0, Order::PENDENTE);

        $this->post("/painel/campanhas/{$campanha->id}/status", ['status' => 'encerrada'])->assertSessionHas('ok');

        $this->assertSame([Order::CANCELADO, 0], [$pendente->fresh()->status, $campanha->fresh()->reserved_count]);
    }

    public function test_configuracoes_aparecem_no_site(): void
    {
        $this->logado();

        $this->put('/painel/configuracoes', ['whatsapp_suporte' => '(11) 98765-4321', 'instagram' => '@nssgrg', 'legal_company' => 'Entidade de Teste', 'legal_cnpj' => '11222333000181', 'vocabulario' => 'titulos'])->assertSessionHas('ok');

        $this->get('/')->assertSee('wa.me/5511987654321')->assertSee('11.222.333/0001-81')->assertSee('Meus títulos');
    }

    public function test_log_de_auditoria_nao_pode_ser_alterado(): void
    {
        $registro = AuditLog::create(['action' => 'teste', 'created_at' => now()]);

        $this->expectException(\LogicException::class);
        $registro->update(['action' => 'outra']);
    }
}
