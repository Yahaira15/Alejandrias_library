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
        'comentario_id',
        'usuario_id',
        'contenido_analizado',
        'categoria_detectada',
        'tipo_riesgo',
        'estado',
        'riesgo',
        'razon',
        'modelo_ia',
        'safety_ratings',
        'metadata',
        'procesado',
        'revisado',
        'revisado_por',
        'moderado_por',
        'decision_admin',
    ];

    protected $casts = [
        'riesgo' => 'float',
        'safety_ratings' => 'array',
        'metadata' => 'array',
        'procesado' => 'boolean',
        'revisado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function publicacion()
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id', 'publicacion_id');
    }

    public function foro()
    {
        return $this->belongsTo(Foro::class, 'foro_id', 'foro_id');
    }

    public function comentario()
    {
        return $this->belongsTo(Comentario::class, 'comentario_id', 'comentario_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }

    public function revisor()
    {
        return $this->belongsTo(Usuario::class, 'revisado_por', 'usuario_id');
    }
}
