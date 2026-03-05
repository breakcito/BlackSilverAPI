<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenEntrega extends Model
{
    protected $table = 'requerimiento_almacen_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_empleado_entrega',
        'id_empleado_recibe',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];

    public function detalles()
    {
        return $this->hasMany(RequerimientoAlmacenEntregaDetalle::class, 'id_requerimiento_almacen_entrega');
    }
}
