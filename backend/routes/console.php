<?php

use Illuminate\Support\Facades\Schedule;

// Equivale al @Cron('*/30 * * * *') de ReportsCleanup en NestJS.
// Para que corra hay que ejecutar: php artisan schedule:work (desarrollo)
Schedule::command('reports:expire')->everyThirtyMinutes();
