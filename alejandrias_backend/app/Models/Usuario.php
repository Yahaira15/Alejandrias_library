<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'usuario';
    protected $primaryKey = 'usuario_id';

    public $timestamps = false;

    protected $fillable = [
        'usuario_nombre',
        'usuario_apellido',
        'usuario_apodo',
        'usuario_email',
        'usuario_password',
        'usuario_rol',
        'usuario_bio',
        'usuario_foto_perfil',
        'usuario_bloqueado',
        'usuario_nivel_id',
        'usuario_experiencia',
        'usuario_puntos'
    ];

    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'comentario_usuario_id');
    }

    public function publicaciones()
    {
        return $this->hasMany(Publicacion::class, 'publicacion_usuario_id');
    }
}
