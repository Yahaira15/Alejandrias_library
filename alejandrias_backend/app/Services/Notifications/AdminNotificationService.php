<?php

namespace App\Services\Notifications;

use App\Models\Notificacion;
use App\Models\Reporte;
use App\Models\Usuario;

class AdminNotificationService
{
    public function notifyReportCreated(Reporte $reporte): void
    {
        $admins = Usuario::where('usuario_rol', 'admin')->get();

        foreach ($admins as $admin) {
            Notificacion::create([
                'notificacion_usuario_id' => $admin->usuario_id,
                'notificacion_tipo' => 'nuevo_reporte',
                'notificacion_contenido' => 'Nuevo reporte de ' . $reporte->reporte_tipo . ': ' . $reporte->reporte_motivo,
                'notificacion_leida' => false,
                'notificacion_fecha' => now(),
                'notificacion_url' => '/admin/reportes',
                'notificacion_referencia_id' => $reporte->reporte_id,
            ]);
        }
    }
}
