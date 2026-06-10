<?php

namespace App\Services\IA\Chat;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class IaChatHistoryService
{
    public function record(int|string|null $usuarioId, string $mensajeUsuario, array $response): void
    {
        if (!$usuarioId || !Schema::hasTable('conversacion_ia') || !Schema::hasTable('mensaje_ia')) {
            return;
        }

        $respuestaIa = trim((string) data_get($response, 'data.mensaje', ''));
        if ($mensajeUsuario === '' && $respuestaIa === '') {
            return;
        }

        try {
            DB::transaction(function () use ($usuarioId, $mensajeUsuario, $respuestaIa, $response) {
                $conversacionId = $this->obtenerConversacionId($usuarioId);

                $this->insertarMensaje($conversacionId, $usuarioId, 'usuario', $mensajeUsuario, [
                    'origen' => 'frontend',
                ]);

                if ($respuestaIa !== '') {
                    $this->insertarMensaje($conversacionId, $usuarioId, 'asistente', $respuestaIa, [
                        'origen' => $response['origen'] ?? null,
                        'tipo' => $response['tipo'] ?? 'chat',
                        'detalle' => $response['detalle'] ?? null,
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Log::warning('No se pudo registrar historial IA compartido', [
                'usuario_id' => $usuarioId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function obtenerConversacionId(int|string $usuarioId): int|string|null
    {
        $primaryKey = $this->primaryKeyFor('conversacion_ia', ['conversacion_id', 'id']);

        $query = DB::table('conversacion_ia');
        if (Schema::hasColumn('conversacion_ia', 'usuario_id')) {
            $query->where('usuario_id', $usuarioId);
        }

        $existing = $primaryKey ? $query->orderByDesc($primaryKey)->value($primaryKey) : null;
        if ($existing) {
            $this->touch('conversacion_ia', $primaryKey, $existing);
            return $existing;
        }

        $attributes = $this->filterColumns('conversacion_ia', [
            'usuario_id' => $usuarioId,
            'titulo' => 'Chat IA',
            'estado' => 'activa',
            'metadata' => ['origen' => 'laravel_proxy'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!$attributes) {
            return null;
        }

        if ($primaryKey) {
            return DB::table('conversacion_ia')->insertGetId($attributes, $primaryKey);
        }

        DB::table('conversacion_ia')->insert($attributes);
        return null;
    }

    private function insertarMensaje(int|string|null $conversacionId, int|string $usuarioId, string $rol, string $texto, array $metadata): void
    {
        $attributes = [
            'conversacion_ia_id' => $conversacionId,
            'conversacion_id' => $conversacionId,
            'usuario_id' => $usuarioId,
            'rol' => $rol,
            'emisor' => $rol,
            'contenido' => $texto,
            'texto' => $texto,
            'mensaje' => $texto,
            'metadata' => $metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $filtered = $this->filterColumns('mensaje_ia', $attributes);

        if ($filtered) {
            DB::table('mensaje_ia')->insert($filtered);
        }
    }

    private function filterColumns(string $table, array $attributes): array
    {
        $filtered = [];

        foreach ($attributes as $column => $value) {
            if (!Schema::hasColumn($table, $column)) {
                continue;
            }

            $filtered[$column] = is_array($value) ? json_encode($value) : $value;
        }

        return $filtered;
    }

    private function primaryKeyFor(string $table, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function touch(string $table, ?string $primaryKey, int|string $id): void
    {
        if (!$primaryKey || !Schema::hasColumn($table, 'updated_at')) {
            return;
        }

        DB::table($table)->where($primaryKey, $id)->update(['updated_at' => now()]);
    }
}
