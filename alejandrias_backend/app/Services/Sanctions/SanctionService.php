<?php

namespace App\Services\Sanctions;

use App\Models\Sancion;
use App\Models\Usuario;

class SanctionService
{
    public function hasActiveBlock(Usuario $usuario, string $accion): bool
    {
        $columna = match ($accion) {
            'comentar' => 'bloquea_comentar',
            'publicar' => 'bloquea_publicar',
            'login' => 'bloquea_login',
            default => null,
        };

        if (!$columna) {
            return false;
        }

        $this->expireOldSanctions($usuario->usuario_id);

        return Sancion::where('sancion_usuario_id', $usuario->usuario_id)
            ->where('sancion_activa', true)
            ->where($columna, true)
            ->where(function ($query) {
                $query->whereNull('sancion_fecha_fin')
                    ->orWhere('sancion_fecha_fin', '>', now());
            })
            ->exists();
    }

    public function expireOldSanctions(?int $usuarioId = null): void
    {
        $query = Sancion::where('sancion_activa', true)
            ->whereNotNull('sancion_fecha_fin')
            ->where('sancion_fecha_fin', '<=', now())
            ->when($usuarioId, fn ($query) => $query->where('sancion_usuario_id', $usuarioId));

        $usuariosConBloqueoLogin = (clone $query)
            ->where('bloquea_login', true)
            ->pluck('sancion_usuario_id')
            ->unique()
            ->values();

        $query->update(['sancion_activa' => false]);

        foreach ($usuariosConBloqueoLogin as $bloqueadoId) {
            $this->syncUsuarioBloqueado((int) $bloqueadoId);
        }
    }

    public function syncUsuarioBloqueado(int $usuarioId): void
    {
        $tieneBloqueoLogin = Sancion::where('sancion_usuario_id', $usuarioId)
            ->where('sancion_activa', true)
            ->where('bloquea_login', true)
            ->where(function ($query) {
                $query->whereNull('sancion_fecha_fin')
                    ->orWhere('sancion_fecha_fin', '>', now());
            })
            ->exists();

        Usuario::where('usuario_id', $usuarioId)->update(['usuario_bloqueado' => $tieneBloqueoLogin]);
    }
}
