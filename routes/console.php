<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Todos los días borra los tokens de Sanctum que vencieron hace más de 24 horas,
// para que la tabla personal_access_tokens no crezca sin límite.
Schedule::command('sanctum:prune-expired --hours=24')->daily();