<?php

namespace App\Services\IA\Chat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaChatClient
{
    public function send(array $payload): array
    {
        $baseUrl = $this->normalizeBaseUrl((string) config('services.ia.url', 'http://127.0.0.1:8080'));
        $timeout = (int) config('services.ia.timeout', 12);
        $endpoint = $baseUrl . '/api/ia/chat/';
        $startedAt = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Log::info('Respuesta HTTP recibida desde chat IA', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'duracion_ms' => $durationMs,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (is_array($data)) {
                    return $data;
                }
            }

            Log::warning('Chat IA no devolvio una respuesta JSON valida', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Excepcion conectando con chat IA', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
                'tipo' => get_class($exception),
            ]);
        }

        return [
            'ok' => true,
            'tipo' => $payload['tipo'] ?? 'chat',
            'origen' => 'respaldo_laravel',
            'data' => [
                'mensaje' => 'No fue posible conectar con el servicio IA en este momento. Intenta nuevamente en unos segundos.',
            ],
            'detalle' => 'Servicio IA no disponible desde Laravel.',
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
}
