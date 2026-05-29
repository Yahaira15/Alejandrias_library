<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RachaUsuario extends Model
{
    protected $table = 'racha_usuario';
    protected $primaryKey = 'racha_usuario_id';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'dias_consecutivos',
        'ultima_fecha',
        'mejor_racha',
        'recompensa_reclamada',
        'xp_obtenida_hoy',
        'actualizado_en',
    ];

    protected $casts = [
        'ultima_fecha' => 'date',
        'recompensa_reclamada' => 'boolean',
        'actualizado_en' => 'datetime',
    ];
}
