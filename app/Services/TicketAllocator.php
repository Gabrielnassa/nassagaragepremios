<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketAllocator
{
    private const CHUNK = 500;

    /**
     * Atribui números livres ao pedido. Precisa rodar dentro da transação que confirma o pagamento.
     * A chave única (campanha, número) descarta repetidos; o que faltar é sorteado de novo.
     */
    public function allocate(Order $order, Campaign $campaign): void
    {
        $need = $order->quantity - $this->count($order);
        $rounds = 0;

        while ($need > 0) {
            if (++$rounds > 40) {
                throw new RuntimeException("Não foi possível atribuir os números do pedido {$order->id}.");
            }

            $issued = DB::table('tickets')->where('campaign_id', $campaign->id)->count();
            $crowded = $rounds > 3 || ($issued + $need) > $campaign->total_numbers * 0.8;

            $candidates = $crowded
                ? $this->fromFree($campaign, $need)
                : $this->random($campaign->total_numbers, $need);

            if ($candidates === []) {
                throw new RuntimeException("Campanha {$campaign->id} sem números livres para o pedido {$order->id}.");
            }

            sort($candidates);
            $now = now();

            foreach (array_chunk($candidates, self::CHUNK) as $chunk) {
                DB::table('tickets')->insertOrIgnore(array_map(fn (int $number) => [
                    'campaign_id' => $campaign->id,
                    'number' => $number,
                    'order_id' => $order->id,
                    'created_at' => $now,
                ], $chunk));
            }

            $need = $order->quantity - $this->count($order);
        }
    }

    private function count(Order $order): int
    {
        return DB::table('tickets')->where('order_id', $order->id)->count();
    }

    private function random(int $total, int $need): array
    {
        $picked = [];
        while (count($picked) < $need) {
            $picked[random_int(0, $total - 1)] = true;
        }

        return array_keys($picked);
    }

    private function fromFree(Campaign $campaign, int $need): array
    {
        $taken = str_repeat("\0", $campaign->total_numbers);

        DB::table('tickets')->where('campaign_id', $campaign->id)->orderBy('id')
            ->select(['id', 'number'])
            ->chunkById(5000, function ($rows) use (&$taken) {
                foreach ($rows as $row) {
                    $taken[(int) $row->number] = "\1";
                }
            });

        $free = [];
        for ($i = 0; $i < $campaign->total_numbers; $i++) {
            if ($taken[$i] === "\0") {
                $free[] = $i;
            }
        }

        $count = count($free);
        $take = min($need, $count);

        for ($i = 0; $i < $take; $i++) {
            $j = random_int($i, $count - 1);
            [$free[$i], $free[$j]] = [$free[$j], $free[$i]];
        }

        return array_slice($free, 0, $take);
    }
}
