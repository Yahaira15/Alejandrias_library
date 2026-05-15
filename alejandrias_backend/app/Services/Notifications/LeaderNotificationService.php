<?php

namespace App\Services\Notifications;

use App\Models\Foro;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

class LeaderNotificationService
{
    public function notifyRelevantLeaders(
        Foro $foro,
        string $tipo,
        string $mensaje,
        string $url,
        int|string|null $referenciaId,
        ?int $actorId = null,
        int $limit = 5
    ): void {
        $categoriaId = $foro->foro_categoria_id;

        if (!$categoriaId) {
            return;
        }

        try {
            $leaders = Usuario::where('usuario_rol', 'lider')
                ->where('usuario_id', '!=', $actorId)
                ->whereHas('forosCreados', function ($query) use ($categoriaId) {
                    $query->where('foro_categoria_id', $categoriaId);
                })
                ->limit($limit)
                ->get();

            foreach ($leaders as $leader) {
                Notificacion::create([
                    'notificacion_usuario_id' => $leader->usuario_id,
                    'notificacion_tipo' => $tipo,
                    'notificacion_contenido' => $mensaje,
                    'notificacion_leida' => false,
                    'notificacion_fecha' => now(),
                    'notificacion_url' => $url,
                    'notificacion_referencia_id' => $referenciaId,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudieron notificar lideres relevantes', [
                'foro_id' => $foro->foro_id,
                'tipo' => $tipo,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
