<?php

namespace App\Http\Controllers\ApiIa;

use App\Http\Controllers\Controller;
use App\Models\Foro;
use App\Services\IA\Chat\IaChatClient;
use App\Services\IA\Chat\IaChatHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ChatIaController extends Controller
{
    public function store(Request $request, IaChatClient $client, IaChatHistoryService $history)
    {
        $usuario = auth('sanctum')->user();

        if (!$usuario) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->validate([
            'tipo' => 'nullable|string|max:60',
            'data' => 'required|array',
            'data.mensaje' => 'required|string|max:4000',
            'data.historial' => 'nullable|array|max:20',
            'data.historial.*.rol' => 'nullable|string|max:40',
            'data.historial.*.texto' => 'nullable|string|max:4000',
            'data.intereses' => 'nullable|array|max:20',
            'data.intereses.*' => 'nullable|string|max:120',
        ]);

        $foros = $this->forosRecomendablesParaIa();

        $payload = [
            'tipo' => $data['tipo'] ?? 'chat',
            'data' => [
                'mensaje' => $data['data']['mensaje'],
                'historial' => $data['data']['historial'] ?? [],
                'intereses' => $data['data']['intereses'] ?? [],
                'foros' => $foros,
                'usuario_id' => $usuario->usuario_id,
                'usuario_rol' => $usuario->usuario_rol,
            ],
        ];

        Log::info('Chat IA envia contexto real de foros a Django', [
            'usuario_id' => $usuario->usuario_id,
            'pregunta' => $data['data']['mensaje'],
            'foros_enviados' => count($foros),
            'ia_service_url' => config('services.ia.url'),
        ]);

        $response = $client->send($payload);

        $history->record(
            $usuario->usuario_id,
            trim((string) $data['data']['mensaje']),
            $response
        );

        return response()->json($response, 200);
    }

    private function forosRecomendablesParaIa(): array
    {
        $limit = max(1, min((int) config('services.ia.forum_context_limit', 80), 150));

        return Foro::query()
            ->where(function ($query) {
                $query->where('foro_privado', false)
                    ->orWhereNull('foro_privado');
            })
            ->when(Schema::hasColumn('foro', 'foro_visibilidad'), function ($query) {
                $query->where('foro_visibilidad', 'visible');
            })
            ->when(Schema::hasColumn('foro', 'foro_estado_moderacion'), function ($query) {
                $query->where(function ($query) {
                    $query->where('foro_estado_moderacion', 'permitido')
                        ->orWhereNull('foro_estado_moderacion');
                });
            })
            ->with(['categoria', 'subcategoria'])
            ->orderByDesc('foro_id')
            ->limit($limit)
            ->get()
            ->map(fn (Foro $foro) => [
                'foro_id' => $foro->foro_id,
                'titulo' => (string) $foro->foro_titulo,
                'descripcion' => (string) ($foro->foro_descripcion ?? ''),
                'categoria' => (string) ($foro->categoria?->categoria_nombre ?? ''),
                'subcategoria' => (string) ($foro->subcategoria?->subcategoria_nombre ?? ''),
                'estado' => (string) ($foro->foro_estado_moderacion ?? 'permitido'),
                'visibilidad' => (string) ($foro->foro_visibilidad ?? 'visible'),
            ])
            ->filter(fn (array $foro) => filled($foro['foro_id']) && filled($foro['titulo']))
            ->values()
            ->all();
    }
}
