<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioProgreso extends Model
{
    protected $table = 'usuario_progreso';
    protected $primaryKey = 'usuario_progreso_id';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'ruta_principal',
        'xp_total',
        'nivel_lider',
        'nivel_explorador',
        'metricas',
        'actualizado_en',
    ];

    protected $casts = [
        'metricas' => 'array',
        'actualizado_en' => 'datetime',
    ];
}
