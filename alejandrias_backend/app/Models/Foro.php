<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;
use App\Models\Categoria;

class Foro extends Model
{
    protected $table = 'foro';
    protected $primaryKey = 'foro_id';

    public $timestamps = false;

    protected $fillable = [
        'foro_titulo',
        'foro_descripcion',
        'foro_categoria_id',
        'foro_creador_id',
        'foro_privado'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'foro_creador_id', 'usuario_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'foro_categoria_id', 'categoria_id');
    }

    public function miembros()
    {
        return $this->belongsToMany(Usuario::class, 'foro_usuario', 'foro_id', 'usuario_id');
    }
}