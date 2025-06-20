<?php

use App\Console\Commands\Reservation\SetReservationAsInProgressCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();



/*Schedule::command('reservation:start')
    ->daily()
    ->at('07:00');*/

Schedule::command('reservation:send-reminder')
    ->daily()
    ->at('10:00');

Schedule::command('reservation:finish')
    ->daily()
    ->at('16:00');

Schedule::command('refresh:cache')
    ->hourly();
