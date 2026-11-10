<?php

namespace App\Console\Commands;

use App\Enums\StateReport;
use App\Models\Report;
use Illuminate\Console\Command;

class ExpireReports extends Command
{
    protected $signature = 'reports:expire';

    protected $description = 'Marca como vencidos (y elimina lógicamente) los reportes cuya fecha expires_at ya pasó';

    public function handle(): int
    {
        $affected = Report::query()
            ->where('expires_at', '<', now())
            ->update([
                'status' => StateReport::Vencido->value,
                'deleted_at' => now(),
            ]);

        $this->info("{$affected} reportes expirados marcados como vencidos y eliminados");

        return self::SUCCESS;
    }
}
