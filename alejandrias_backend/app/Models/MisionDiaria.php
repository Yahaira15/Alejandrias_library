<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MisionDiaria extends Model
{
    protected $table = 'misiones_diarias';
    protected $primaryKey = 'mision_diaria_id';

    protected $fillable = [
        'mision_slug',
        'mision_titulo',
        'mision_descripcion',
        'mision_tipo',
        'objetivo',
        'xp_recompensa',
        'puntos_recompensa',
        'insignia_temporal',
        'fecha',
        'activa',
    ];

    protected $casts = [
        'fecha' => 'date',
        'activa' => 'boolean',
    ];
}
