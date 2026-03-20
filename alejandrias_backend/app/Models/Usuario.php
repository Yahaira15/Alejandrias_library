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
}
