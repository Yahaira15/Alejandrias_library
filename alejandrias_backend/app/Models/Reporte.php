<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    protected $table = 'reporte';
    protected $primaryKey = 'reporte_id';
    public $timestamps = false;

    protected $fillable = [
        'reporte_tipo',
        'reporte_referencia_id',
        'reporte_usuario_reporta_id',
        'reporte_motivo',
        'reporte_descripcion',
        'reporte_estado',
        'reporte_prioridad',
        'reporte_fecha',
        'revisado_por',
        'decision_final',
        'riesgo',
        'ia_detectado',
    ];

    protected $casts = [
        'reporte_fecha' => 'datetime',
        'riesgo' => 'float',
        'ia_detectado' => 'boolean',
    ];

    public function reporta()
    {
        return $this->belongsTo(Usuario::class, 'reporte_usuario_reporta_id', 'usuario_id');
    }

    public function revisor()
    {
        return $this->belongsTo(Usuario::class, 'revisado_por', 'usuario_id');
    }

    public function sanciones()
    {
        return $this->hasMany(Sancion::class, 'sancion_reporte_id', 'reporte_id');
    }
}
