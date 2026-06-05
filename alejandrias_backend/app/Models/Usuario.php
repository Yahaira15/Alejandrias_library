<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable; // 🔥 IMPORTANTE
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

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
        'usuario_intereses',
        'usuario_bloqueado',
        'usuario_nivel_id',
        'usuario_experiencia',
        'usuario_puntos'
    ];

    protected $casts = [
        'usuario_intereses' => 'array',
    ];

    protected $hidden = [
        'usuario_password',
    ];

    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'comentario_usuario_id');
    }

    public function publicaciones()
    {
        return $this->hasMany(Publicacion::class, 'publicacion_usuario_id');
    }

    public function forosCreados()
    {
        return $this->hasMany(Foro::class, 'foro_creador_id', 'usuario_id');
    }

    public function forosFavoritos()
    {
        return $this->belongsToMany(Foro::class, 'foro_favorito', 'usuario_id', 'foro_id')
            ->withPivot('fecha_creacion');
    }

    public function sanciones()
    {
        return $this->hasMany(Sancion::class, 'sancion_usuario_id', 'usuario_id');
    }
}
