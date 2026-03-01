<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categoria';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo_requerimiento',
        'clasificacion_bien',
        'estado',
    ];

    public static function get_categorias(?string $tipo_requerimiento = null)
    {
        $query = self::where('estado', EstadoBase::Activo->value);

        if ($tipo_requerimiento) {
            $query->where('tipo_requerimiento', $tipo_requerimiento);
        }

        return $query->get();
    }

    public static function get_categoria_by_id(int $id)
    {
        return self::find($id);
    }
}
