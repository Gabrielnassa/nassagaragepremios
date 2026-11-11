<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoLimpar extends Command
{
    protected $signature = 'demo:limpar';

    protected $description = 'Remove todos os dados de demonstração';

    public function handle(): int
    {
        DB::transaction(function () {
            $campaigns = Campaign::where('is_demo', true)->pluck('id');
            $customers = Customer::where('is_demo', true)->pluck('id');

            Order::whereIn('campaign_id', $campaigns)->orWhereIn('customer_id', $customers)->delete();
            Campaign::whereIn('id', $campaigns)->delete();
            Customer::whereIn('id', $customers)->delete();
        });

        Storage::disk('public')->deleteDirectory('demo');

        $this->info('Dados de demonstração removidos.');

        return self::SUCCESS;
    }
}
