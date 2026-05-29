<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    use HasFactory;

    protected $table = 'comentario';
    public $timestamps = false;
    protected $primaryKey = 'comentario_id';

    protected $fillable = [
        'comentario_usuario_id',
        'comentario_publicacion_id',
        'comentario_padre_id',
        'comentario_contenido',
        'estado_moderacion',
        'ia_riesgo',
        'ia_razon',
        'ia_fecha_analisis',
    ];

    protected $casts = [
        'ia_riesgo' => 'float',
        'ia_fecha_analisis' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'comentario_usuario_id');
    }

    public function publicacion()
    {
        return $this->belongsTo(Publicacion::class, 'comentario_publicacion_id');
    }

    public function padre()
    {
        return $this->belongsTo(Comentario::class, 'comentario_padre_id', 'comentario_id');
    }

    public function respuestas()
    {
        return $this->hasMany(Comentario::class, 'comentario_padre_id', 'comentario_id');
    }
}
