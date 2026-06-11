<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Models\Publicacion;
use App\Models\Comentario;
use App\Models\PuntuacionForo;

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
        'foro_privado',
        'foro_password',
        'foro_imagen',
        'subcategoria_id',
        'foro_estado_moderacion',
        'foro_visibilidad'
    ];

    protected $hidden = [
        'foro_password',
    ];

    protected $appends = [
        'foro_imagen_url',
    ];

    public function getForoImagenUrlAttribute(): ?string
    {
        return $this->normalizarImagenPublica($this->foro_imagen);
    }

    private function normalizarImagenPublica(?string $imagen): ?string
    {
        $imagen = trim((string) $imagen);

        if ($imagen === '') {
            return null;
        }

        if (preg_match('/^(data:|blob:)/i', $imagen)) {
            return $imagen;
        }

        if (preg_match('/^https?:\/\//i', $imagen)) {
            $path = parse_url($imagen, PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, '/storage/')) {
                return $path;
            }

            return $imagen;
        }

        if (str_starts_with($imagen, '/storage/')) {
            return $imagen;
        }

        $imagen = ltrim($imagen, '/');

        return str_starts_with($imagen, 'storage/')
            ? '/' . $imagen
            : '/storage/' . $imagen;
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'foro_creador_id', 'usuario_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'foro_categoria_id', 'categoria_id');
    }

    public function subcategoria()
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id', 'subcategoria_id');
    }

    public function miembros()
    {
        return $this->belongsToMany(Usuario::class, 'foro_usuario', 'foro_id', 'usuario_id');
    }

    public function usuariosFavoritos()
    {
        return $this->belongsToMany(Usuario::class, 'foro_favorito', 'foro_id', 'usuario_id')
            ->withPivot('fecha_creacion');
    }

    public function publicaciones()
    {
        return $this->hasMany(Publicacion::class, 'publicacion_foro_id', 'foro_id');
    }

    public function comentarios()
    {
        return $this->hasManyThrough(
            Comentario::class,
            Publicacion::class,
            'publicacion_foro_id',
            'comentario_publicacion_id',
            'foro_id',
            'publicacion_id'
        );
    }

    public function puntuaciones()
    {
        return $this->hasMany(PuntuacionForo::class, 'foro_id', 'foro_id');
    }
}
