<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Foro;



class Categoria extends Model
{
    public $timestamps = false;


    protected $table = 'categoria';
    protected $primaryKey = 'categoria_id';
    protected $fillable = [
        'categoria_nombre',
        'categoria_descripcion',
        'categoria_imagen'
    ];

    public function foros()
    {
        return $this->hasMany(Foro::class, 'foro_categoria_id');
    }
}
