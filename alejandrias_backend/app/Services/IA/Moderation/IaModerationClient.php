<?php

namespace App\Services\IA\Moderation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaModerationClient
{
    public function analyze(array $payload): array
    {
        $baseUrl = $this->normalizeBaseUrl((string) config('services.ia.url', 'http://127.0.0.1:8080'));
        $timeout = (int) config('services.ia.timeout', 12);
        $endpoint = $baseUrl . '/api/ia/moderacion/';
        $startedAt = microtime(true);

        try {
            Log::info('Enviando contenido a moderacion IA', [
                'endpoint' => $endpoint,
                'tipo_contenido' => $payload['tipo_contenido'] ?? 'contenido',
                'timeout_segundos' => $timeout,
                'payload' => $payload,
            ]);

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Log::info('Respuesta HTTP recibida desde servicio Django IA', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'duracion_ms' => $durationMs,
                'content_type' => $response->header('Content-Type'),
                'body' => $response->body(),
            ]);

            if ($response->successful() && $response->json('ok') === true) {
                $analysis = $this->normalize($response->json('data') ?? [], $response->json('origen', 'modelo'));

                Log::info('Moderacion IA recibida', [
                    'tipo_contenido' => $payload['tipo_contenido'] ?? 'contenido',
                    'estado' => $analysis['estado'],
                    'riesgo' => $analysis['riesgo'],
                    'origen' => $analysis['origen'],
                    'modelo_ia' => $analysis['modelo_ia'],
                    'debug_django' => $response->json('debug'),
                ]);

                return $analysis;
            }

            Log::warning('Punto exacto de fallback Laravel: Django IA no respondio ok=true con HTTP exitoso', [
                'status' => $response->status(),
                'duracion_ms' => $durationMs,
                'body' => $response->body(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Punto exacto de fallback Laravel: excepcion conectando con Django IA', [
                'error' => $exception->getMessage(),
                'tipo' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        return [
            'estado' => 'revision',
            'riesgo' => 0.50,
            'categoria' => 'otro',
            'razon' => 'No fue posible completar la moderacion automatica; requiere revision humana.',
            'accion_recomendada' => 'enviar_revision',
            'valor_educativo' => false,
            'requiere_revision_humana' => true,
            'origen' => 'respaldo_laravel',
            'modelo_ia' => 'respaldo_laravel',
        ];
    }

    private function normalize(array $data, string $origen): array
    {
        $estado = strtolower((string) ($data['estado'] ?? 'revision'));
        if (!in_array($estado, ['permitido', 'revision', 'bloqueado'], true)) {
            $estado = 'revision';
        }

        $riesgo = (float) ($data['riesgo'] ?? 0.5);
        $riesgo = max(0.0, min(1.0, $riesgo));

        return [
            'estado' => $estado,
            'riesgo' => round($riesgo, 2),
            'categoria' => strtolower((string) ($data['categoria'] ?? 'otro')),
            'razon' => trim((string) ($data['razon'] ?? 'Analisis de moderacion completado.')),
            'accion_recomendada' => (string) ($data['accion_recomendada'] ?? $this->defaultAction($estado)),
            'valor_educativo' => (bool) ($data['valor_educativo'] ?? false),
            'requiere_revision_humana' => (bool) ($data['requiere_revision_humana'] ?? $estado === 'revision'),
            'origen' => $origen,
            'modelo_ia' => $origen === 'modelo' ? 'gemini' : $origen,
        ];
    }

    private function normalizeBaseUrl(string $url): string
    {
        $baseUrl = rtrim($url, '/');

        if (str_ends_with($baseUrl, '/api/ia')) {
            return substr($baseUrl, 0, -strlen('/api/ia'));
        }

        return $baseUrl;
    }

    private function defaultAction(string $estado): string
    {
        return match ($estado) {
            'permitido' => 'publicar',
            'bloqueado' => 'bloquear',
            default => 'enviar_revision',
        };
    }
}
