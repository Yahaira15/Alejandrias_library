<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XpEvento extends Model
{
    protected $table = 'xp_evento';
    protected $primaryKey = 'xp_evento_id';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'accion',
        'xp',
        'origen_tipo',
        'origen_id',
        'metadata',
        'creado_en',
    ];

    protected $casts = [
        'metadata' => 'array',
        'creado_en' => 'datetime',
    ];
}
