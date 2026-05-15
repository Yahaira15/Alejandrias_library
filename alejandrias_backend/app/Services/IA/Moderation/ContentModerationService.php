<?php

namespace App\Services\IA\Moderation;

use App\Models\ModeracionIa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ContentModerationService
{
    public function __construct(private IaModerationClient $client)
    {
    }

    public function analyze(array $payload): array
    {
        return $this->client->analyze($payload);
    }

    public function applyToModel(Model $model, array $analysis, string $prefix): void
    {
        if (Schema::hasColumn($model->getTable(), 'estado_moderacion')) {
            $model->estado_moderacion = $this->visibilityFor($analysis['estado']);
        }

        if (Schema::hasColumn($model->getTable(), 'ia_riesgo')) {
            $model->ia_riesgo = $analysis['riesgo'];
        }

        if (Schema::hasColumn($model->getTable(), 'ia_razon')) {
            $model->ia_razon = $analysis['razon'];
        }

        if (Schema::hasColumn($model->getTable(), 'ia_fecha_analisis')) {
            $model->ia_fecha_analisis = now();
        }

        $legacyEstadoColumn = $prefix . '_estado_moderacion';
        $legacyVisibilidadColumn = $prefix . '_visibilidad';

        if (Schema::hasColumn($model->getTable(), $legacyEstadoColumn)) {
            $model->{$legacyEstadoColumn} = $analysis['estado'];
        }

        if (Schema::hasColumn($model->getTable(), $legacyVisibilidadColumn)) {
            $model->{$legacyVisibilidadColumn} = $this->visibilityFor($analysis['estado']);
        }
    }

    public function record(
        string $tipoContenido,
        int|string|null $referenciaId,
        ?int $usuarioId,
        array $analysis,
        array $payload
    ): bool {
        if (!Schema::hasTable('moderacion_ia')) {
            Log::warning('No existe la tabla moderacion_ia; no se pudo registrar el analisis IA', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
            ]);
            return false;
        }

        try {
            ModeracionIa::create([
                'publicacion_id' => $this->publicacionIdFor($tipoContenido, $referenciaId, $payload),
                'foro_id' => $this->foroIdFor($tipoContenido, $referenciaId, $payload),
                'usuario_id' => $usuarioId,
                'contenido_analizado' => $this->contenidoAnalizado($payload),
                'categoria_detectada' => $analysis['categoria'] ?? 'otro',
                'tipo_riesgo' => $this->tipoRiesgo($analysis),
                'estado' => $this->visibilityFor($analysis['estado']),
                'riesgo' => $analysis['riesgo'],
                'razon' => $analysis['razon'],
                'modelo_ia' => $analysis['modelo_ia'] ?? $analysis['origen'] ?? 'desconocido',
                'procesado' => true,
                'revisado' => false,
                'revisado_por' => null,
                'decision_admin' => null,
            ]);

            Log::info('Analisis de moderacion IA registrado', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
                'estado' => $analysis['estado'],
                'riesgo' => $analysis['riesgo'],
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo registrar el analisis de moderacion IA', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
                'columnas_esperadas' => [
                    'publicacion_id',
                    'foro_id',
                    'usuario_id',
                    'contenido_analizado',
                    'categoria_detectada',
                    'tipo_riesgo',
                    'estado',
                    'riesgo',
                    'razon',
                    'modelo_ia',
                    'procesado',
                    'revisado',
                    'revisado_por',
                    'decision_admin',
                    'created_at',
                    'updated_at',
                ],
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function visibilityFor(string $estado): string
    {
        return match ($estado) {
            'permitido' => 'visible',
            'bloqueado' => 'bloqueado',
            default => 'revision',
        };
    }

    private function publicacionIdFor(string $tipoContenido, int|string|null $referenciaId, array $payload): int|string|null
    {
        if ($tipoContenido === 'publicacion') {
            return $referenciaId;
        }

        return $payload['contexto']['publicacion_id'] ?? null;
    }

    private function foroIdFor(string $tipoContenido, int|string|null $referenciaId, array $payload): int|string|null
    {
        if ($tipoContenido === 'foro') {
            return $referenciaId;
        }

        return $payload['contexto']['foro_id'] ?? null;
    }

    private function contenidoAnalizado(array $payload): string
    {
        $contenido = $payload['contenido'] ?? [];

        return trim(implode("\n", array_filter([
            $contenido['nombre'] ?? null,
            $contenido['titulo'] ?? null,
            $contenido['texto'] ?? null,
        ])));
    }

    private function tipoRiesgo(array $analysis): string
    {
        $categoria = (string) ($analysis['categoria'] ?? 'otro');

        if (($analysis['estado'] ?? 'revision') === 'permitido' && in_array($categoria, ['educativo', 'conversacional', 'ocio', 'tecnologia', 'otro'], true)) {
            return 'ninguno';
        }

        return $categoria;
    }
}
