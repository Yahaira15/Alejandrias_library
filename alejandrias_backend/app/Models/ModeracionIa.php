<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeracionIa extends Model
{
    protected $table = 'moderacion_ia';

    protected $primaryKey = 'moderacion_id';

    public $timestamps = true;

    protected $fillable = [
        'publicacion_id',
        'foro_id',
        'usuario_id',
        'contenido_analizado',
        'categoria_detectada',
        'tipo_riesgo',
        'estado',
        'riesgo',
        'razon',
        'modelo_ia',
        'procesado',
        'revisado',
        'revisado_por',
        'decision_admin',
    ];

    protected $casts = [
        'riesgo' => 'float',
        'procesado' => 'boolean',
        'revisado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
