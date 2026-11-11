<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\InstantPrize;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Winner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoCriar extends Command
{
    protected $signature = 'demo:criar';

    protected $description = 'Cria campanhas, clientes e pedidos fictícios para desenvolvimento';

    private const NOMES = ['Fulano', 'Fulana', 'Beltrano', 'Beltrana', 'Sicrano', 'Sicrana'];

    private const SOBRENOMES = ['de Tal', 'Exemplo', 'Teste', 'Modelo', 'Fictício', 'Demonstração'];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Dados de demonstração não podem ser criados em produção.');

            return self::FAILURE;
        }

        if (Campaign::where('is_demo', true)->exists()) {
            $this->error('Já existem dados de demonstração. Rode "php artisan demo:limpar" antes.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            $customers = $this->customers();
            $this->carCampaign($customers);
            $this->finishedCampaign($customers);
            $this->upcomingCampaign();
        });

        $this->info('Dados de demonstração criados.');

        return self::SUCCESS;
    }

    private function customers(): array
    {
        $ids = [];
        $i = 0;

        foreach (self::NOMES as $nome) {
            foreach (self::SOBRENOMES as $sobrenome) {
                $i++;
                $customer = new Customer([
                    'phone' => '1190000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'name' => "$nome $sobrenome",
                    'birth_date' => '1990-01-01',
                    'terms_accepted_at' => now(),
                    'is_demo' => true,
                ]);
                $customer->setCpf($this->cpf($i));
                $customer->save();
                $ids[] = $customer->id;
            }
        }

        return $ids;
    }

    private function carCampaign(array $customers): void
    {
        $today = CarbonImmutable::now('America/Sao_Paulo');

        $campaign = Campaign::create([
            'slug' => 'bmw-135i-estoril-blue',
            'title' => 'BMW 135i Estoril Blue',
            'subtitle' => 'Campanha de demonstração. Carro, valores e compradores são fictícios.',
            'description' => "Este texto é um exemplo. Aqui entra a ficha do carro: ano, motor, quilometragem, histórico de revisões e o que foi feito na oficina.\n\nO texto final vem do painel e deve repetir o que está no regulamento.",
            'status' => Campaign::ATIVA,
            'price_cents' => 4990,
            'total_numbers' => 10000,
            'digits' => 4,
            'min_per_order' => 1,
            'max_per_order' => 100,
            'reservation_minutes' => 15,
            'draw_at' => $today->addDays(12)->setTime(19, 0)->utc(),
            'draw_criterion' => 'Texto de exemplo: o número contemplado é formado pelos quatro últimos algarismos do 1º prêmio da extração da Loteria Federal na data indicada, conforme o regulamento.',
            'delivery_deadline' => 'Texto de exemplo: até 30 dias após a apuração.',
            'is_demo' => true,
        ]);

        $shots = ['Frente 3/4', 'Traseira 3/4', 'Interior', 'Motor'];
        foreach ($shots as $i => $shot) {
            $path = 'demo/foto-'.($i + 1).'.svg';
            Storage::disk('public')->put($path, $this->placeholder($i + 1, $shot));
            $campaign->images()->create([
                'path' => $path,
                'alt' => "Foto de exemplo: $shot",
                'width' => 1200,
                'height' => 800,
                'position' => $i,
            ]);
        }

        $campaign->prizes()->createMany([
            ['position' => 1, 'title' => 'BMW 135i Estoril Blue', 'description' => 'Exemplo. A descrição do prêmio principal vem do regulamento.'],
            ['position' => 2, 'title' => 'R$ 5.000 no Pix', 'description' => 'Exemplo de segundo prêmio.'],
        ]);

        $numbers = range(0, 9999);
        shuffle($numbers);
        $sold = array_slice($numbers, 0, 6288);
        $free = array_slice($numbers, 6288);

        $start = $today->subDays(6)->startOfDay();
        $span = max(1, $today->subMinutes(20)->getTimestamp() - $start->getTimestamp());
        $this->orders($campaign, $customers, $sold, fn () => $start->addSeconds(random_int(0, $span))->utc());

        $campaign->timedPromos()->create([
            'type' => 'maior_numero',
            'title' => 'Maior número do dia',
            'rules' => 'Exemplo de regra: quem tiver o maior número entre as compras pagas hoje, até 23h59, leva o prêmio.',
            'prize' => 'R$ 1.000 no Pix',
            'starts_at' => $today->startOfDay()->utc(),
            'ends_at' => $today->endOfDay()->utc(),
        ]);

        $instant = array_merge(array_slice($sold, 0, 4), array_slice($free, 0, 6));
        sort($instant);
        $salt = Str::random(32);
        $list = array_map(fn ($n) => ['numero' => $campaign->formatNumber($n), 'premio' => 'R$ 500 no Pix'], $instant);

        foreach ($instant as $number) {
            $ticket = Ticket::where('campaign_id', $campaign->id)->where('number', $number)->first();
            InstantPrize::create([
                'campaign_id' => $campaign->id,
                'number' => $number,
                'prize' => 'R$ 500 no Pix',
                'ticket_id' => $ticket?->id,
                'won_at' => $ticket?->created_at,
            ]);
        }

        $campaign->update([
            'instant_salt' => $salt,
            'instant_hash' => hash('sha256', $salt.json_encode($list, JSON_UNESCAPED_UNICODE)),
        ]);
    }

    private function finishedCampaign(array $customers): void
    {
        $day = CarbonImmutable::now('America/Sao_Paulo')->subDays(20);

        $campaign = Campaign::create([
            'slug' => 'premio-em-pix-exemplo',
            'title' => 'R$ 10.000 no Pix',
            'subtitle' => 'Campanha de demonstração já apurada.',
            'status' => Campaign::APURADA,
            'price_cents' => 990,
            'total_numbers' => 1000,
            'digits' => 3,
            'max_per_order' => 50,
            'draw_at' => $day->setTime(19, 0)->utc(),
            'drawn_at' => $day->setTime(19, 30)->utc(),
            'official_result' => 'Exemplo de extração',
            'draw_criterion' => 'Texto de exemplo do critério de apuração.',
            'is_demo' => true,
        ]);

        $campaign->prizes()->create(['position' => 1, 'title' => 'R$ 10.000 no Pix']);

        $numbers = range(0, 999);
        shuffle($numbers);
        $start = $day->subDays(5);
        $this->orders($campaign, $customers, $numbers, fn () => $start->addSeconds(random_int(0, 5 * 86400 - 3600))->utc());

        $ticket = Ticket::where('campaign_id', $campaign->id)->inRandomOrder()->first();
        $order = Order::with('customer')->find($ticket->order_id);

        $campaign->update(['winning_number' => $ticket->number]);

        Winner::create([
            'campaign_id' => $campaign->id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'type' => 'principal',
            'display_name' => $order->customer->publicName(),
            'prize' => 'R$ 10.000 no Pix',
            'number' => $ticket->number,
            'awarded_on' => $day->toDateString(),
            'consent_at' => now(),
            'published_at' => now(),
        ]);
    }

    private function upcomingCampaign(): void
    {
        Campaign::create([
            'slug' => 'proxima-campanha-exemplo',
            'title' => 'Próxima campanha',
            'subtitle' => 'Exemplo de campanha anunciada antes da abertura das vendas.',
            'status' => Campaign::EM_BREVE,
            'price_cents' => 2990,
            'total_numbers' => 10000,
            'digits' => 4,
            'starts_at' => CarbonImmutable::now('America/Sao_Paulo')->addDays(9)->setTime(12, 0)->utc(),
            'is_demo' => true,
        ]);
    }

    private function orders(Campaign $campaign, array $customers, array $numbers, callable $paidAt): void
    {
        $sizes = [1, 1, 1, 2, 2, 2, 3, 3, 5, 5, 10, 20];
        $tickets = [];
        $offset = 0;
        $total = count($numbers);

        while ($offset < $total) {
            $qty = min($sizes[array_rand($sizes)], $total - $offset);
            $paid = $paidAt();

            $order = Order::create([
                'code' => Str::upper(Str::random(10)),
                'campaign_id' => $campaign->id,
                'customer_id' => $customers[array_rand($customers)],
                'quantity' => $qty,
                'amount_cents' => $qty * $campaign->price_cents,
                'status' => Order::PAGO,
                'paid_at' => $paid,
                'created_at' => $paid,
                'updated_at' => $paid,
            ]);

            foreach (array_slice($numbers, $offset, $qty) as $number) {
                $tickets[] = ['campaign_id' => $campaign->id, 'number' => $number, 'order_id' => $order->id, 'created_at' => $paid];
            }
            $offset += $qty;
        }

        foreach (array_chunk($tickets, 500) as $chunk) {
            Ticket::insert($chunk);
        }

        $campaign->update(['sold_count' => $total]);
    }

    private function cpf(int $seed): string
    {
        $base = array_map('intval', str_split(str_pad((string) (100000000 + $seed * 7919), 9, '0', STR_PAD_LEFT)));

        foreach ([10, 11] as $weight) {
            $sum = 0;
            foreach ($base as $i => $digit) {
                $sum += $digit * ($weight - $i);
            }
            $base[] = ($sum * 10 % 11) % 10;
        }

        return implode('', $base);
    }

    private function placeholder(int $index, string $label): string
    {
        $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" width="1200" height="800">
<defs><pattern id="h" width="28" height="28" patternUnits="userSpaceOnUse" patternTransform="rotate(-24)"><rect width="28" height="28" fill="#14161A"/><rect width="1" height="28" fill="#1F232A"/></pattern></defs>
<rect width="1200" height="800" fill="url(#h)"/>
<rect x="64" y="64" width="1072" height="672" fill="none" stroke="#2B2F37" stroke-width="2"/>
<text x="104" y="640" font-family="Arial Narrow, Arial, sans-serif" font-size="150" font-weight="700" fill="#2B2F37">$n</text>
<text x="104" y="692" font-family="Arial, sans-serif" font-size="30" fill="#9299A5">Foto do prêmio: $label</text>
</svg>
SVG;
    }
}
