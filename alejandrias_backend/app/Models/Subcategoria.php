<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Subcategoria extends Model
{
    public $timestamps = false;

    protected $table = 'subcategoria';
    protected $primaryKey = 'subcategoria_id';

    protected $fillable = [
        'subcategoria_nombre',
        'subcategoria_descripcion',
        'subcategoria_categoria_id',
        'subcategoria_imagen',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (!Schema::hasTable($this->table) && Schema::hasTable('subcategorias')) {
            $this->table = 'subcategorias';
        }
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'subcategoria_categoria_id', 'categoria_id');
    }

    public function foros()
    {
        return $this->hasMany(Foro::class, 'subcategoria_id', 'subcategoria_id');
    }
}
