<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PuntuacionForo extends Model
{
    protected $table = 'puntuacion_foro';
    protected $primaryKey = 'puntuacion_foro_id';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'foro_id',
        'puntuacion',
        'fecha_creacion',
        'fecha_actualizacion',
    ];

    protected $casts = [
        'puntuacion' => 'float',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }

    public function foro()
    {
        return $this->belongsTo(Foro::class, 'foro_id', 'foro_id');
    }
}
