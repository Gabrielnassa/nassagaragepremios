<?php

namespace App\Console\Commands;

use App\Services\Payments;
use Illuminate\Console\Command;

class PedidosExpirar extends Command
{
    protected $signature = 'pedidos:expirar';

    protected $description = 'Expira pedidos pendentes fora do prazo e devolve a quantidade ao estoque';

    public function handle(Payments $payments): int
    {
        $this->info($payments->expireDue().' pedido(s) expirado(s).');

        return self::SUCCESS;
    }
}
