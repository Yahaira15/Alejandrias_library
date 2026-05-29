<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioInsignia extends Model
{
    protected $table = 'usuario_insignia';
    protected $primaryKey = 'usuario_insignia_id';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'insignia_id',
        'obtenida_en',
        'snapshot_metricas',
    ];

    protected $casts = [
        'obtenida_en' => 'datetime',
        'snapshot_metricas' => 'array',
    ];

    public function insignia()
    {
        return $this->belongsTo(Insignia::class, 'insignia_id', 'insignia_id');
    }
}
