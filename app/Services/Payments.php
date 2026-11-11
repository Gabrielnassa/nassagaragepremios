<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\Payment;
use App\Pix\PixProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Payments
{
    public function __construct(private readonly TicketAllocator $allocator)
    {
    }

    /**
     * Confirma um pagamento. Pode ser chamado várias vezes para o mesmo pagamento sem efeito repetido.
     */
    public function confirm(Payment $payment, PixProvider $provider, ?CarbonInterface $paidAt = null): string
    {
        $paidAt ??= now();

        $campaignId = Order::whereKey($payment->order_id)->value('campaign_id');

        $outcome = DB::transaction(function () use ($payment, $paidAt, $campaignId) {
            $campaign = $this->lockCampaign($campaignId);
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pendente') {
                return 'repetido';
            }

            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $quantity = $order->quantity;

            $payment->update(['status' => 'pago', 'paid_at' => $paidAt]);

            if ($campaign->status === Campaign::APURADA) {
                return 'estornar';
            }

            $fromPending = Order::whereKey($order->id)->where('status', Order::PENDENTE)
                ->update(['status' => Order::PAGO, 'paid_at' => $paidAt, 'closed_at' => null, 'close_reason' => null]);

            if ($fromPending) {
                Campaign::whereKey($campaign->id)->where('reserved_count', '>=', $quantity)->update([
                    'reserved_count' => DB::raw('reserved_count - '.$quantity),
                    'sold_count' => DB::raw('sold_count + '.$quantity),
                ]);
            } elseif (in_array($order->status, [Order::EXPIRADO, Order::CANCELADO], true)) {
                $hasStock = Campaign::whereKey($campaign->id)
                    ->whereRaw('sold_count + reserved_count + ? <= total_numbers', [$quantity])
                    ->update(['sold_count' => DB::raw('sold_count + '.$quantity)]);

                if (! $hasStock) {
                    return 'estornar';
                }

                Order::whereKey($order->id)->update(['status' => Order::PAGO, 'paid_at' => $paidAt, 'closed_at' => null, 'close_reason' => null]);
            } else {
                return 'repetido';
            }

            $this->allocator->allocate($order, $campaign);
            $this->awardInstantPrizes($order);

            return 'pago';
        }, 5);

        if ($outcome === 'estornar') {
            $this->refund($payment->fresh(), $provider, 'Pagamento recebido sem estoque disponível');
        }

        return $outcome;
    }

    public function refund(Payment $payment, PixProvider $provider, string $reason): bool
    {
        try {
            $done = $provider->refund($payment);
        } catch (Throwable $e) {
            $done = false;
            Log::error('Falha ao estornar pagamento', ['payment_id' => $payment->id, 'erro' => $e->getMessage()]);
        }

        if (! $done) {
            Order::whereKey($payment->order_id)->update(['close_reason' => 'Estorno pendente: '.$reason]);

            return false;
        }

        DB::transaction(function () use ($payment, $reason) {
            $payment->update(['status' => 'estornado', 'refunded_at' => now()]);
            Order::whereKey($payment->order_id)->update(['status' => Order::ESTORNADO, 'closed_at' => now(), 'close_reason' => $reason]);
        });

        return true;
    }

    /**
     * Encerra um pedido pendente e devolve a quantidade reservada ao estoque.
     */
    public function release(Order $order, string $status = Order::EXPIRADO, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($order, $status, $reason) {
            $this->lockCampaign($order->campaign_id);

            $closed = Order::whereKey($order->id)->where('status', Order::PENDENTE)
                ->update(['status' => $status, 'closed_at' => now(), 'close_reason' => $reason]);

            if (! $closed) {
                return false;
            }

            Campaign::whereKey($order->campaign_id)->where('reserved_count', '>=', $order->quantity)
                ->update(['reserved_count' => DB::raw('reserved_count - '.(int) $order->quantity)]);

            return true;
        }, 3);
    }

    /**
     * Estorna um pedido pago: devolve o valor pelo provedor, apaga os números e libera o estoque.
     */
    public function refundPaid(Order $order, PixProvider $provider, string $reason): bool
    {
        $payment = $order->payments()->where('status', 'pago')->latest('id')->first();

        if ($order->status !== Order::PAGO || $payment === null) {
            return false;
        }

        try {
            if (! $provider->refund($payment)) {
                return false;
            }
        } catch (Throwable $e) {
            Log::error('Falha ao estornar pagamento', ['payment_id' => $payment->id, 'erro' => $e->getMessage()]);

            return false;
        }

        DB::transaction(function () use ($order, $payment, $reason) {
            $this->lockCampaign($order->campaign_id);

            $closed = Order::whereKey($order->id)->where('status', Order::PAGO)
                ->update(['status' => Order::ESTORNADO, 'closed_at' => now(), 'close_reason' => $reason]);

            if (! $closed) {
                return;
            }

            $tickets = DB::table('tickets')->where('order_id', $order->id)->pluck('id');
            DB::table('instant_prizes')->whereIn('ticket_id', $tickets)->update(['ticket_id' => null, 'won_at' => null]);
            DB::table('tickets')->where('order_id', $order->id)->delete();

            Campaign::whereKey($order->campaign_id)->where('sold_count', '>=', $order->quantity)
                ->update(['sold_count' => DB::raw('sold_count - '.(int) $order->quantity)]);

            $payment->update(['status' => 'estornado', 'refunded_at' => now()]);
        }, 3);

        return true;
    }

    /**
     * Libera todos os pedidos pendentes de uma campanha, usado ao encerrar as vendas.
     */
    public function releaseAll(Campaign $campaign, string $reason): int
    {
        $count = 0;

        Order::where('campaign_id', $campaign->id)->where('status', Order::PENDENTE)->get()
            ->each(function (Order $order) use (&$count, $reason) {
                $count += (int) $this->release($order, Order::CANCELADO, $reason);
            });

        return $count;
    }

    public function expireDue(): int
    {
        $limit = now()->subSeconds((int) config('pix.grace_seconds'));
        $count = 0;

        Order::where('status', Order::PENDENTE)->where('expires_at', '<', $limit)->orderBy('id')
            ->chunkById(200, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $count += (int) $this->release($order, Order::EXPIRADO, 'Prazo de pagamento encerrado');
                }
            });

        return $count;
    }

    public function isOverdue(Order $order): bool
    {
        return $order->status === Order::PENDENTE
            && $order->expires_at !== null
            && $order->expires_at->lt(now()->subSeconds((int) config('pix.grace_seconds')));
    }

    /**
     * A linha da campanha é sempre a primeira a ser travada. Isso mantém a mesma ordem
     * de bloqueio em reserva, confirmação e expiração e evita deadlock no MySQL.
     */
    public function lockCampaign(int $campaignId): Campaign
    {
        return Campaign::whereKey($campaignId)->lockForUpdate()->firstOrFail();
    }

    private function awardInstantPrizes(Order $order): void
    {
        $tickets = DB::table('tickets')->where('order_id', $order->id)->pluck('id', 'number');

        DB::table('instant_prizes')
            ->where('campaign_id', $order->campaign_id)
            ->whereNull('ticket_id')
            ->whereIn('number', $tickets->keys())
            ->get(['id', 'number'])
            ->each(fn ($prize) => DB::table('instant_prizes')->where('id', $prize->id)->whereNull('ticket_id')
                ->update(['ticket_id' => $tickets[$prize->number], 'won_at' => now(), 'updated_at' => now()]));
    }
}
