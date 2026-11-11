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
        $this->line('Consulta de teste: CPF '.$this->cpf(1).', nascimento 01/01/1990.');

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
            'title' => 'Maior número da semana',
            'rules' => 'Exemplo de regra: quem tiver o maior número entre as compras pagas até o fim do contador leva o prêmio.',
            'prize' => 'R$ 1.000 no Pix',
            'starts_at' => $start->utc(),
            'ends_at' => $today->addDays(6)->endOfDay()->utc(),
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
        $campaign->images()->create(['path' => 'demo/foto-4.svg', 'alt' => 'Foto de exemplo', 'width' => 1200, 'height' => 800]);

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
        $campaign = Campaign::create([
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

        $campaign->images()->create(['path' => 'demo/foto-2.svg', 'alt' => 'Foto de exemplo', 'width' => 1200, 'height' => 800]);
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
        [$zoom, $dx, $dy, $luz] = [[1, 0, 0, 600], [1.55, -40, -330, 380], [1.55, -640, -330, 860], [1.2, -120, -150, 600]][$index - 1];

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" width="1200" height="800">
<defs>
<radialGradient id="luz" cx="$luz" cy="120" r="760" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#3A4352"/><stop offset=".55" stop-color="#171B22"/><stop offset="1" stop-color="#08090B"/></radialGradient>
<linearGradient id="piso" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1A1E25"/><stop offset="1" stop-color="#08090B"/></linearGradient>
<linearGradient id="capa" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5C97E8"/><stop offset=".4" stop-color="#2A61AD"/><stop offset="1" stop-color="#132D52"/></linearGradient>
<radialGradient id="sombra" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#000" stop-opacity=".75"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
</defs>
<rect width="1200" height="800" fill="url(#luz)"/>
<g transform="translate($dx $dy) scale($zoom)">
<g stroke="#FFFFFF" stroke-opacity=".035" stroke-width="2"><path d="M-400 120H1600M-400 190H1600M-400 260H1600M-400 330H1600M-400 400H1600M-400 470H1600M-400 540H1600"/></g>
<rect x="-400" y="610" width="2000" height="400" fill="url(#piso)"/>
<path d="M-400 610H1600" stroke="#2B303A" stroke-width="2"/>
<ellipse cx="620" cy="626" rx="560" ry="34" fill="url(#sombra)"/>
<path d="M150 612C150 560 176 520 232 500C300 478 380 470 440 456C500 402 560 354 660 346C760 340 832 354 890 400C932 430 990 440 1040 462C1076 480 1086 540 1086 612Q1000 624 900 613T700 615T500 612T300 616T150 612Z" fill="url(#capa)"/>
<g fill="none" stroke="#0C1F3B" stroke-opacity=".5" stroke-width="5" stroke-linecap="round"><path d="M660 350C648 430 640 520 656 610"/><path d="M440 460C428 520 446 570 438 610"/><path d="M890 404C908 480 894 560 912 610"/><path d="M300 484C286 530 300 580 290 612"/><path d="M1000 446C1012 500 998 566 1010 610"/><path d="M560 380C548 450 566 540 552 610"/><path d="M780 348C790 430 772 530 786 612"/></g>
<path d="M232 500C300 478 380 470 440 456C500 402 560 354 660 346C760 340 832 354 890 400C932 430 990 440 1040 462" fill="none" stroke="#B9D4FA" stroke-opacity=".7" stroke-width="4" stroke-linecap="round"/>
</g>
<text x="44" y="70" font-family="Arial Narrow, Arial, sans-serif" font-size="30" font-weight="700" fill="#F1F3F6" fill-opacity=".85" letter-spacing="3">FOTO DE EXEMPLO $n</text>
<text x="44" y="104" font-family="Arial, sans-serif" font-size="22" fill="#9BA3B0">$label · a foto real do prêmio entra aqui</text>
</svg>
SVG;
    }
}
