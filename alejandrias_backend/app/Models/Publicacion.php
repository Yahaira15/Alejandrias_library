<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publicacion extends Model
{
    protected $table = 'publicacion';
    protected $primaryKey = 'publicacion_id';
    public $timestamps = false;

    protected $fillable = [
        'publicacion_foro_id',
        'publicacion_usuario_id',
        'publicacion_titulo',
        'publicacion_contenido',
        'publicacion_destacada',
        'publicacion_likes',
        'publicacion_fecha_creacion',
        'publicacion_fecha_actualizacion',
        'estado_moderacion',
        'ia_riesgo',
        'ia_razon',
        'ia_fecha_analisis',
    ];

    protected $casts = [
        'ia_riesgo' => 'float',
        'ia_fecha_analisis' => 'datetime',
    ];

    public function foro()
    {
        return $this->belongsTo(Foro::class, 'publicacion_foro_id', 'foro_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'publicacion_usuario_id', 'usuario_id');
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'comentario_publicacion_id');
    }

    public function likes()
    {
        return $this->belongsToMany(Usuario::class, 'publicacion_like', 'publicacion_id', 'usuario_id');
    }
}
