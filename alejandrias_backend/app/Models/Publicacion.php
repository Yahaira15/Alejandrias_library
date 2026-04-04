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
        'publicacion_destacado',
        'publicacion_fecha_creacion',
        'publicacion_fecha_actualizacion'
    ];

    // 🔗 RELACIONES

    public function foro()
    {
        return $this->belongsTo(Foro::class, 'publicacion_foro_id', 'foro_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'publicacion_usuario_id', 'usuario_id');
    }
}