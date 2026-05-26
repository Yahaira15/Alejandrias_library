<?php

namespace App\Services\Notifications;

use App\Models\Notificacion;
use App\Models\Reporte;
use App\Models\Usuario;
use Illuminate\Support\Str;

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

    public function notifyAiRiskDetected(array $alert): void
    {
        if (!($alert['requiere_alerta'] ?? false)) {
            return;
        }

        $admins = Usuario::where('usuario_rol', 'admin')->get();
        $usuarioNombre = $alert['usuario_nombre'] ?? 'Usuario desconocido';
        $usuarioId = $alert['usuario_id'] ?? 'sin_id';
        $nivel = $alert['nivel'] ?? 'riesgo_alto';
        $tipo = $alert['tipo'] ?? 'riesgo';
        $contenido = Str::limit((string) ($alert['contenido'] ?? ''), 220);
        $fecha = $alert['fecha'] ?? now()->toDateTimeString();

        foreach ($admins as $admin) {
            Notificacion::create([
                'notificacion_usuario_id' => $admin->usuario_id,
                'notificacion_tipo' => 'alerta_ia_' . $nivel,
                'notificacion_contenido' => sprintf(
                    'Alerta IA %s (%s). Usuario: %s [ID: %s]. Fecha: %s. Contenido: %s',
                    $nivel,
                    $tipo,
                    $usuarioNombre,
                    $usuarioId,
                    $fecha,
                    $contenido
                ),
                'notificacion_leida' => false,
                'notificacion_fecha' => now(),
                'notificacion_url' => $alert['url'] ?? '/admin/reportes',
                'notificacion_referencia_id' => $alert['referencia_id'] ?? null,
            ]);
        }
    }
}
