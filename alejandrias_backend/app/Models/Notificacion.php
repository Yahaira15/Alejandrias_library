<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificacion';

    protected $primaryKey = 'notificacion_id';

    public $timestamps = false;

    protected $fillable = [
        'notificacion_usuario_id',
        'notificacion_tipo',
        'notificacion_contenido',
        'notificacion_leida',
        'notificacion_fecha'
    ];

    // 🔹 Relación usuario
    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'notificacion_usuario_id',
            'usuario_id'
        );
    }
}