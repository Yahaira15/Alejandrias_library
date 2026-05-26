<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insignia extends Model
{
    protected $table = 'insignia';
    protected $primaryKey = 'insignia_id';

    protected $fillable = [
        'insignia_slug',
        'insignia_ruta',
        'insignia_nivel',
        'insignia_nombre',
        'insignia_emoji',
        'insignia_descripcion',
        'insignia_requisito',
        'insignia_criterios',
        'insignia_color',
    ];

    protected $casts = [
        'insignia_criterios' => 'array',
    ];
}
