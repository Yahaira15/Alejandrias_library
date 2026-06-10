<?php

namespace App\Http\Controllers\ApiIa;

use App\Http\Controllers\Controller;
use App\Services\IA\Chat\IaChatClient;
use App\Services\IA\Chat\IaChatHistoryService;
use Illuminate\Http\Request;

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
        ]);

        $payload = [
            'tipo' => $data['tipo'] ?? 'chat',
            'data' => [
                'mensaje' => $data['data']['mensaje'],
                'historial' => $data['data']['historial'] ?? [],
                'usuario_id' => $usuario->usuario_id,
                'usuario_rol' => $usuario->usuario_rol,
            ],
        ];

        $response = $client->send($payload);

        $history->record(
            $usuario->usuario_id,
            trim((string) $data['data']['mensaje']),
            $response
        );

        return response()->json($response, 200);
    }
}
