<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia a los activos con los que cuenta la empresa, como
 * por ejemplo: vehiculos, maquinarias, equipos electrogenos, compresoras, etc
 */
class ActivoFijo extends Model
{
    protected $table = 'activo_fijo';

    public $timestamps = false;

    protected $casts = [
        'especificaciones' => 'array',
        'total_horas' => 'decimal:2',
        'total_kilometros' => 'decimal:2',
        'total_vueltas' => 'decimal:2',
        'proxima_advertencia_horas' => 'decimal:2',
        'proxima_advertencia_kilometros' => 'decimal:2',
        'proxima_advertencia_vueltas' => 'decimal:2',
        'intervalo_mantenimiento_horas' => 'decimal:2',
        'intervalo_mantenimiento_kilometros' => 'decimal:2',
        'intervalo_mantenimiento_vueltas' => 'decimal:2',
    ];

    protected $fillable = [
        'id_producto', // que producto es este activo
        // En que lugar se encuentra, solo una de ellas debe de tener valor
        'id_almacen', // opcional - en que almacen se encuentra almacenado este activo
        'id_mina', // opcional - en que mina se encuentra siendo usado este activo
        'id_marca', // de que marca - opcional
        'id_empleado_responsable', // opcional - es el empleado/operador encargado/responsable del activo
        // referencias para saber de que compra proviene
        'id_orden_compra_recepcion_detalle', // de que recepcion de la compra proviene
        'id_orden_compra_detalle', // de que detalle de la compra proviene ya que podrian haberse pedido mas de 1 vez el mismo producto pero con precios diferentes
        //
        'codigo', // opcional - lo pone el usuario
        'correlativo', // como prefijo utiliza el que ha sido dado en el modulo de productos
        'numero_correlativo',
        //
        // Para saber de que compra provino en caso no haya venido desde el modulo de ordenes de compra
        'serie_factura_compra',// opcional
        'numero_factura_compra',// opcional
        //
        'costo_promedio_base', // el costo promedio del producto al momento del registro
        'costo_compra', // Cuanto costó este activo cuando se compró
        'numero_serie', // identificador del fabricante
        'modelo', // nombre del modelo
        'yearcito_modelo', // año del modelo
        'descripcion', // opcional
        'serie_placa', // opcional
        'numero_placa', // opcional
        // 
        'especificaciones', // Columna JSON para almacenar especificaciones dinámicas
        // [{"clave": "", "valor": ""} ... ]
        //
        'fecha_hora_ingreso', // fecha en la que el activo ingresa a la empresa
        // Totales de tracking de uso
        'total_horas',
        'total_kilometros',
        'total_vueltas',
        // Umbrales para proxima advertencia de mantenimiento
        'proxima_advertencia_horas',
        'proxima_advertencia_kilometros',
        'proxima_advertencia_vueltas',
        // Intervalos de configuracion para la frecuencia de mantenimiento
        'intervalo_mantenimiento_horas',
        'intervalo_mantenimiento_kilometros',
        'intervalo_mantenimiento_vueltas',
        
        'created_at', // fecha en la que se registro en el sistema
        'estado' // EstadoActivoFijo - En Uso, En Mantenimiento, En Almacen, Dado de Baja
    ];
}
