<?php

namespace App\Console\Commands;

use App\Modules\ContratosEmpleado\Services\ContratosEmpleadoService;
use Illuminate\Console\Command;

/**
 * Inactiva todos los contratos de trabajo que:
 *   - estén Activos,
 *   - NO sean por tiempo indefinido,
 *   - tengan fecha_fin anterior al día actual.
 *
 * Se ejecuta diariamente vía Schedule (routes/console.php).
 * El campo `id_contrato_vigente` del empleado NO se migra: cuando el empleado
 * renueve contrato, el nuevo registro sobrescribirá ese campo de forma natural.
 *
 * Mientras tanto, los filtros que requieran contrato vigente validan
 * explícitamente `contrato.estado = 'Activo'`.
 */
class InactivarContratosVencidos extends Command
{
    protected $signature = 'contratos:inactivar-vencidos {--dry-run : Solo contar, sin modificar la BD}';

    protected $description = 'Inactiva los contratos no indefinidos cuya fecha_fin ya pasó.';

    public function handle(): int
    {
        $dry_run = (bool) $this->option('dry-run');

        $resultado = ContratosEmpleadoService::inactivar_vencidos_no_indefinidos(dry_run: $dry_run);

        if ($dry_run) {
            $this->info(sprintf(
                '[DRY-RUN] Contratos a inactivar: %d (sin escribir en BD)',
                $resultado['total_evaluados']
            ));
        } else {
            $this->info(sprintf(
                'Contratos evaluados: %d | Inactivados: %d',
                $resultado['total_evaluados'],
                $resultado['total_inactivados']
            ));
        }

        return self::SUCCESS;
    }
}
