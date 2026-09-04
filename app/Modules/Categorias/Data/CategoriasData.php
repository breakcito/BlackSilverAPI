<?php

namespace App\Modules\Categorias\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CategoriasData
{
    /**
     * Listar o obtener una categoría.
     *
     * El listado excluye las Inactivas (eliminación lógica). Se usa
     * `IFNULL(cat.estado, 'Activo')` porque la columna `estado` es NULLABLE:
     * con un `cat.estado != 'Inactivo'` pelado, las filas con estado NULL
     * darían NULL en la comparación y desaparecerían del listado.
     *
     * Al pedir una categoría puntual (`$id_categoria`) NO se filtra por
     * estado: el flujo de eliminación necesita poder leerla ya Inactiva
     * para devolverla al frontend.
     */
    public static function get_categorias(?int $id_categoria = null, ?EstadoBase $estado = null)
    {
        $sql = '
        SELECT 
            cat.id AS id_categoria,
            cat.nombre,
            cat.descripcion,
            
            cat.tipo_producto, -- bien o servicio
            cat.clasificacion_bien, -- suministro, material o activo fijo
            
            cat.para_transporte,
            cat.control_por_odometro,
            cat.control_por_horometro,
            cat.control_por_vueltas,
            
            -- flags
            cat.es_consumible,
            cat.es_auditable,
            
            -- destinos de uso
            cat.para_cocina,
            cat.para_mina,
            
            cat.cambios_log,
            
            cat.estado
        FROM categoria cat
        WHERE 1=1 
        ';

        $params = [];

        if ($id_categoria != null) {
            $sql .= ' AND cat.id = :id_categoria';
            $params['id_categoria'] = $id_categoria;
            return DB::selectOne($sql, $params);
        }

        if ($estado != null) {
            $sql .= ' AND cat.estado = :estado';
            $params['estado'] = $estado->value;
        }

        // Eliminación lógica: las Inactivas no se listan.
        $sql .= ' AND IFNULL(cat.estado, :estado_activo_fallback) != :estado_inactivo';
        $params['estado_activo_fallback'] = EstadoBase::Activo->value;
        $params['estado_inactivo'] = EstadoBase::Inactivo->value;

        $sql .= ' ORDER BY cat.nombre ASC';
        return DB::select($sql, $params);
    }

    /**
     * Obtener una categoría por su ID
     */
    public static function get_categoria_by_id(int $id_categoria)
    {
        return self::get_categorias(id_categoria: $id_categoria);
    }

    /**
     * Mapeo campo_bd => nombre visible para el log de cambios.
     * Mantener sincronizado con los campos actualizables de actualizar_categoria().
     */
    private const CATEGORIA_CAMBIOS_LABELS = [
        'nombre' => 'Nombre',
        'descripcion' => 'Descripción',
        'tipo_producto' => 'Tipo de Producto',
        'clasificacion_bien' => 'Clasificación',
        'para_transporte' => 'Para Transporte',
        'control_por_odometro' => 'Control por Odómetro',
        'control_por_horometro' => 'Control por Horómetro',
        'control_por_vueltas' => 'Control por Vueltas',
        'es_consumible' => 'Es Consumible',
        'para_cocina' => 'Para Cocina',
        'para_mina' => 'Para Mina',
        'es_auditable' => 'Es Auditable',
    ];

    /**
     * Tipo PHP esperado por cada campo. Se usa SOLO para la normalización del
     * diff, de modo que `false !== 0` (bool vs int de MySQL) no genere falsos
     * positivos en el historial.
     */
    private const CATEGORIA_CAMBIOS_TIPOS = [
        'nombre' => 'string',
        'descripcion' => 'string',
        'tipo_producto' => 'string',
        'clasificacion_bien' => 'string',
        'para_transporte' => 'bool',
        'control_por_odometro' => 'bool',
        'control_por_horometro' => 'bool',
        'control_por_vueltas' => 'bool',
        'es_consumible' => 'bool',
        'para_cocina' => 'bool',
        'para_mina' => 'bool',
        'es_auditable' => 'bool',
    ];

    /**
     * Normaliza un valor al tipo canónico del campo para comparaciones fiables.
     */
    private static function normalizarParaComparar(mixed $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            'bool' => ((bool) $valor) ? 1 : 0,
            'int' => (int) $valor,
            'float' => (float) $valor,
            default => (string) $valor,
        };
    }

    /**
     * Actualizar una categoría existente.
     * Si recibe id_empleado + nombre_empleado, calcula el diff entre la
     * categoría previa y la nueva y lo apendea al array cambios_log (JSON).
     */
    public static function actualizar_categoria(
        int $id_categoria,
        string $nombre,
        string $tipo_producto,
        ?string $clasificacion_bien = null,
        ?string $descripcion = null,
        bool $para_transporte = false,
        bool $control_por_odometro = false,
        bool $control_por_horometro = false,
        bool $control_por_vueltas = false,
        bool $es_consumible = false,
        bool $para_cocina = false,
        bool $para_mina = false,
        bool $es_auditable = false,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ): int {
        $nuevoEstado = [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_producto' => $tipo_producto,
            'clasificacion_bien' => $clasificacion_bien,
            'para_transporte' => $para_transporte ? 1 : 0,
            'control_por_odometro' => $control_por_odometro ? 1 : 0,
            'control_por_horometro' => $control_por_horometro ? 1 : 0,
            'control_por_vueltas' => $control_por_vueltas ? 1 : 0,
            'es_consumible' => $es_consumible ? 1 : 0,
            'para_cocina' => $para_cocina ? 1 : 0,
            'para_mina' => $para_mina ? 1 : 0,
            'es_auditable' => $es_auditable ? 1 : 0,
        ];

        $cambiosLog = null;
        if ($id_empleado !== null && $nombre_empleado !== null) {
            $original = self::get_categorias(id_categoria: $id_categoria);
            $cambiosLog = self::calcularDiffCambiosCategoria($original, $nuevoEstado, $id_empleado, $nombre_empleado);
        }

        $updatePayload = $nuevoEstado;
        if ($cambiosLog !== null) {
            $updatePayload['cambios_log'] = json_encode($cambiosLog, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('categoria')
            ->where('id', $id_categoria)
            ->update($updatePayload);

        return (int) $affected;
    }

    /**
     * Compara la categoría previa (object) con el nuevo estado (array) y devuelve
     * el array cambios_log listo para persistir (apendea una entrada nueva solo
     * si hay al menos un campo modificado).
     */
    private static function calcularDiffCambiosCategoria(
        $original,
        array $nuevoEstado,
        int $id_empleado,
        string $nombre_empleado
    ): array {
        $logPrevio = [];
        if ($original !== null && ! empty($original->cambios_log)) {
            $raw = $original->cambios_log;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $logPrevio = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $logPrevio = $raw;
            }
        }

        $cambios = [];
        foreach (self::CATEGORIA_CAMBIOS_LABELS as $campoBd => $label) {
            if (! array_key_exists($campoBd, $nuevoEstado)) {
                continue;
            }
            $valorAnterior = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valorNuevo = $nuevoEstado[$campoBd];

            $tipo = self::CATEGORIA_CAMBIOS_TIPOS[$campoBd] ?? 'string';
            $anteriorNorm = self::normalizarParaComparar($valorAnterior, $tipo);
            $nuevoNorm = self::normalizarParaComparar($valorNuevo, $tipo);

            if ($anteriorNorm !== $nuevoNorm) {
                $cambios[] = [
                    'campo_bd' => $campoBd,
                    'campo' => $label,
                    // Guardamos el valor normalizado para que el frontend
                    // reciba booleanos como 0/1 de forma consistente.
                    'valor_anterior' => $anteriorNorm,
                    'valor_nuevo' => $nuevoNorm,
                ];
            }
        }

        if (count($cambios) === 0) {
            return $logPrevio;
        }

        $logPrevio[] = [
            'id_empleado' => $id_empleado,
            'nombre_empleado' => $nombre_empleado,
            'motivo' => null,
            'update_at' => now()->toDateTimeString(),
            'cambios' => $cambios,
        ];

        return $logPrevio;
    }

    /**
     * Desactivar (soft delete) una categoría cambiando su estado a Inactivo.
     * No se elimina físicamente para preservar la integridad referencial con
     * `producto` (que la referencia con INNER JOIN) y con el Kardex.
     */
    public static function eliminar_categoria(int $id_categoria): int
    {
        $affected = DB::table('categoria')
            ->where('id', $id_categoria)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
            ]);

        return (int) $affected;
    }

    /**
     * Verificar si ya existe otra categoría activa con el mismo nombre,
     * excluyendo opcionalmente un ID concreto (el que se está editando).
     */
    public static function existe_nombre(string $nombre, ?int $excluir_id = null): bool
    {
        $query = DB::table('categoria')
            ->where('nombre', $nombre)
            ->whereRaw('IFNULL(estado, ?) != ?', [
                EstadoBase::Activo->value,
                EstadoBase::Inactivo->value,
            ]);

        if ($excluir_id !== null) {
            $query->where('id', '!=', $excluir_id);
        }

        return $query->exists();
    }

    /**
     * Cuenta los productos aún activos que apuntan a esta categoría.
     * Se usa para no dejar productos huérfanos apuntando a una categoría
     * Inactiva: el catálogo auxiliar solo ofrece categorías Activas, así que
     * al editar esos productos el Select de categoría quedaría vacío.
     */
    public static function contar_productos_activos(int $id_categoria): int
    {
        return (int) DB::table('producto')
            ->where('id_categoria', $id_categoria)
            ->whereRaw('IFNULL(estado, ?) != ?', [
                EstadoBase::Activo->value,
                EstadoBase::Inactivo->value,
            ])
            ->count();
    }
}
