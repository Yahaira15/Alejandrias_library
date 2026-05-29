<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioMision extends Model
{
    protected $table = 'usuario_mision';
    protected $primaryKey = 'usuario_mision_id';

    protected $fillable = [
        'usuario_id',
        'mision_diaria_id',
        'progreso',
        'completada',
        'reclamada',
        'completada_en',
        'reclamada_en',
    ];

    protected $casts = [
        'completada' => 'boolean',
        'reclamada' => 'boolean',
        'completada_en' => 'datetime',
        'reclamada_en' => 'datetime',
    ];

    public function mision()
    {
        return $this->belongsTo(MisionDiaria::class, 'mision_diaria_id', 'mision_diaria_id');
    }
}
