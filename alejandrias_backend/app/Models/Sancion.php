<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sancion extends Model
{
    protected $table = 'sancion';
    protected $primaryKey = 'sancion_id';
    public $timestamps = false;

    protected $fillable = [
        'sancion_usuario_id',
        'sancion_tipo',
        'sancion_nivel',
        'sancion_motivo',
        'sancion_fecha_inicio',
        'sancion_fecha_fin',
        'sancion_activa',
        'sancion_admin_id',
        'sancion_reporte_id',
        'bloquea_comentar',
        'bloquea_publicar',
        'bloquea_login',
    ];

    protected $casts = [
        'sancion_fecha_inicio' => 'datetime',
        'sancion_fecha_fin' => 'datetime',
        'sancion_activa' => 'boolean',
        'bloquea_comentar' => 'boolean',
        'bloquea_publicar' => 'boolean',
        'bloquea_login' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'sancion_usuario_id', 'usuario_id');
    }

    public function admin()
    {
        return $this->belongsTo(Usuario::class, 'sancion_admin_id', 'usuario_id');
    }

    public function reporte()
    {
        return $this->belongsTo(Reporte::class, 'sancion_reporte_id', 'reporte_id');
    }
}
