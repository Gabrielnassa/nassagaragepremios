<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultaTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '529.982.247-25';

    private function compra(): void
    {
        $campanha = Campaign::create([
            'slug' => 'teste', 'title' => 'Campanha de teste', 'status' => Campaign::ATIVA,
            'price_cents' => 4990, 'total_numbers' => 10000, 'digits' => 4,
        ]);

        $cliente = new Customer(['phone' => '11900000001', 'name' => 'Fulano de Tal', 'birth_date' => '1990-03-05']);
        $cliente->setCpf(self::CPF);
        $cliente->save();

        $pedido = Order::create([
            'code' => 'ABCDEFGHIJ', 'campaign_id' => $campanha->id, 'customer_id' => $cliente->id,
            'quantity' => 1, 'amount_cents' => 4990, 'status' => Order::PAGO, 'paid_at' => now(),
        ]);

        Ticket::create(['campaign_id' => $campanha->id, 'number' => 42, 'order_id' => $pedido->id]);
    }

    public function test_valida_digitos_do_cpf(): void
    {
        $this->assertTrue(Cpf::valid(self::CPF));
        $this->assertFalse(Cpf::valid('529.982.247-26'));
        $this->assertFalse(Cpf::valid('111.111.111-11'));
        $this->assertFalse(Cpf::valid('123'));
    }

    public function test_consulta_mostra_numeros_com_cpf_e_nascimento_corretos(): void
    {
        $this->compra();

        $this->post('/meus-numeros', ['cpf' => self::CPF, 'nascimento' => '05/03/1990'])
            ->assertOk()
            ->assertSee('Pedidos de Fulano T.')
            ->assertSee('0042')
            ->assertDontSee('Fulano de Tal');
    }

    public function test_consulta_recusa_nascimento_errado_sem_revelar_o_motivo(): void
    {
        $this->compra();

        $errado = $this->from('/meus-numeros')->post('/meus-numeros', ['cpf' => self::CPF, 'nascimento' => '06/03/1990']);
        $errado->assertSessionHasErrors('cpf');

        $inexistente = $this->from('/meus-numeros')->post('/meus-numeros', ['cpf' => '111.444.777-35', 'nascimento' => '05/03/1990']);
        $inexistente->assertSessionHasErrors('cpf');

        $this->assertSame(session('errors')->first('cpf'), $errado->getSession()->get('errors')->first('cpf'));
    }

    public function test_limites_sao_contados_por_rota(): void
    {
        $this->compra();

        for ($i = 0; $i < 20; $i++) {
            $this->get('/pedido/ABCDEFGHIJ/status')->assertOk();
        }

        $this->post('/meus-numeros', ['cpf' => self::CPF, 'nascimento' => '05/03/1990'])->assertOk();
    }

    public function test_consulta_tem_limite_de_tentativas(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->post('/meus-numeros', ['cpf' => '1', 'nascimento' => '']);
        }

        $this->post('/meus-numeros', ['cpf' => '1', 'nascimento' => ''])->assertStatus(429);
    }
}
