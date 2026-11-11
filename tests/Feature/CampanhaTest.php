<?php

namespace Tests\Feature;

use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampanhaTest extends TestCase
{
    use RefreshDatabase;

    private function campanha(array $dados = []): Campaign
    {
        return Campaign::create($dados + [
            'slug' => 'teste',
            'title' => 'Campanha de teste',
            'status' => Campaign::ATIVA,
            'price_cents' => 4990,
            'total_numbers' => 10000,
            'digits' => 4,
        ]);
    }

    public function test_pagina_da_campanha_ativa_mostra_compra(): void
    {
        $this->campanha(['sold_count' => 6288]);

        $this->get('/campanha/teste')
            ->assertOk()
            ->assertSee('Campanha de teste')
            ->assertSee('62,88%')
            ->assertSee('Garantir números')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_rascunho_nao_e_publico(): void
    {
        $this->campanha(['status' => Campaign::RASCUNHO]);

        $this->get('/campanha/teste')->assertNotFound();
        $this->get('/campanha/teste/ao-vivo')->assertNotFound();
    }

    public function test_campanha_esgotada_nao_oferece_compra(): void
    {
        $this->campanha(['sold_count' => 10000]);

        $this->get('/campanha/teste')
            ->assertOk()
            ->assertSee('Esgotada')
            ->assertDontSee('Garantir números');
    }

    public function test_estoque_considera_reservas_e_limite_por_pedido(): void
    {
        $campanha = $this->campanha(['sold_count' => 9900, 'reserved_count' => 70, 'max_per_order' => 100]);

        $this->assertSame(30, $campanha->remaining());
        $this->assertSame(30, $campanha->maxPurchasable());
        $this->getJson('/campanha/teste/ao-vivo')->assertOk()->assertJson(['remaining' => 30, 'max' => 30, 'percent' => 99.0]);
    }

    public function test_bloco_legal_lista_o_que_falta(): void
    {
        $campanha = $this->campanha(['legal_modality' => 'capitalizacao', 'legal_company' => 'Empresa de Teste Ltda']);

        $faltando = $campanha->legalMissing();

        $this->assertNotContains('razão social do responsável', $faltando);
        $this->assertContains('CNPJ do responsável', $faltando);
        $this->assertContains('sociedade de capitalização', $faltando);
        $this->assertContains('entidade beneficiada', $faltando);
    }

    public function test_numero_e_formatado_com_os_digitos_da_campanha(): void
    {
        $this->assertSame('0042', $this->campanha()->formatNumber(42));
    }

    public function test_inicio_sem_campanhas_mostra_estado_vazio(): void
    {
        $this->get('/')->assertOk()->assertSee('Nenhuma campanha aberta agora');
    }
}
