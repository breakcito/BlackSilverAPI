<?php

namespace App\Console\Commands;

use App\Modules\ContratosEmpleado\Services\ContratosEmpleadoService;
use Illuminate\Console\Command;

/**
 * Job diario de mantenimiento del ciclo de vida de los contratos.
 *
 * Ejecuta tres pasos en orden:
 *  1. Finaliza contratos Vigentes cuya fecha_fin ya pasó (Vigente → Finalizado).
 *  2. Limpia `id_contrato_vigente` de los empleados cuyo vigente fue finalizado.
 *  3. Activa contratos Pendiente cuya fecha_inicio ya llegó (Pendiente → Vigente).
 *
 * Se programa diariamente vía Schedule (routes/console.php).
 */
class ProcesarContratosJob extends Command
{
    protected $signature = 'contratos:procesar-vencimientos-pendientes {--dry-run : Solo contar, sin modificar la BD}';

    protected $description = 'Procesa vencimientos de contratos Vigentes y activa contratos Pendientes cuya fecha_inicio ya comenzó.';

    public function handle(): int
    {
        $dry_run = (bool) $this->option('dry-run');

        $resultado = ContratosEmpleadoService::procesar_vencimientos_y_pendientes(
            fecha_referencia: now()->toDateString(),
            dry_run: $dry_run,
        );

        $this->info(sprintf(
            '[%s] Fecha de referencia: %s | Finalizados: %d | Empleados limpiados: %d | Pendientes activados: %d',
            $dry_run ? 'DRY-RUN' : 'EJECUTADO',
            $resultado['fecha_referencia'],
            $resultado['finalizados'],
            $resultado['empleados_limpiados'],
            $resultado['pendientes_activados'],
        ));

        return self::SUCCESS;
    }
}
