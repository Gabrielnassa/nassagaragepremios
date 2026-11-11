<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TimedPromo extends Model
{
    public const MAIOR_NUMERO = 'maior_numero';

    public const MAIOR_COMPRADOR = 'maior_comprador';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function isRunning(): bool
    {
        return now()->between($this->starts_at, $this->ends_at);
    }

    /**
     * Líder atual, calculado só com pedidos pagos dentro do período.
     *
     * @return array{value: string, name: string, location: ?string, at: \Carbon\CarbonInterface}|null
     */
    public function leader(): ?array
    {
        $paid = Order::query()
            ->where('orders.campaign_id', $this->campaign_id)
            ->where('orders.status', Order::PAGO)
            ->whereBetween('orders.paid_at', [$this->starts_at, $this->ends_at]);

        if ($this->type === self::MAIOR_NUMERO) {
            $row = (clone $paid)
                ->join('tickets', 'tickets.order_id', '=', 'orders.id')
                ->orderByDesc('tickets.number')
                ->first(['orders.*', 'tickets.number as top_number']);

            if (! $row) {
                return null;
            }

            $value = $this->campaign->formatNumber((int) $row->top_number);
            $order = $row;
        } else {
            $top = (clone $paid)
                ->groupBy('orders.customer_id')
                ->orderByDesc('total')
                ->orderBy('last_paid')
                ->first(['orders.customer_id', DB::raw('SUM(orders.quantity) as total'), DB::raw('MAX(orders.paid_at) as last_paid')]);

            if (! $top) {
                return null;
            }

            $value = number_format((int) $top->total, 0, ',', '.');
            $order = (clone $paid)->where('orders.customer_id', $top->customer_id)->latest('orders.paid_at')->first();
        }

        $customer = Customer::find($order->customer_id);

        return [
            'value' => $value,
            'name' => $customer?->publicName() ?? '',
            'location' => $customer?->publicLocation(),
            'at' => $order->paid_at,
        ];
    }
}
