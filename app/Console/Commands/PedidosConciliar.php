<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Pix\PixProvider;
use App\Services\Payments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PedidosConciliar extends Command
{
    protected $signature = 'pedidos:conciliar';

    protected $description = 'Consulta o provedor de Pix para pagamentos ainda pendentes';

    public function handle(Payments $payments, PixProvider $provider): int
    {
        $confirmed = 0;

        Payment::where('status', 'pendente')
            ->where('provider', $provider->name())
            ->where('created_at', '>', now()->subDays(2))
            ->whereHas('order', fn ($q) => $q->whereIn('status', [Order::PENDENTE, Order::EXPIRADO]))
            ->orderBy('id')
            ->chunkById(100, function ($pending) use ($payments, $provider, &$confirmed) {
                foreach ($pending as $payment) {
                    try {
                        $status = $provider->getCharge($payment);
                        if ($status->paid && $payments->confirm($payment, $provider, $status->paidAt) === 'pago') {
                            $confirmed++;
                        }
                    } catch (Throwable $e) {
                        Log::warning('Falha na conciliação', ['payment_id' => $payment->id, 'erro' => $e->getMessage()]);
                    }
                }
            });

        $this->info($confirmed.' pagamento(s) confirmado(s) na conciliação.');

        return self::SUCCESS;
    }
}
