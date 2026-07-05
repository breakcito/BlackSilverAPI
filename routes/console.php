<?php

use App\Console\Commands\InactivarContratosVencidos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Inactivar diariamente los contratos no indefinidos cuya fecha_fin ya pasó.
Schedule::command(InactivarContratosVencidos::class)->dailyAt('00:05');
