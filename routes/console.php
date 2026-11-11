<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('pedidos:expirar')->everyMinute()->withoutOverlapping();

if (config('pix.provider')) {
    Schedule::command('pedidos:conciliar')->everyFiveMinutes()->withoutOverlapping();
}
