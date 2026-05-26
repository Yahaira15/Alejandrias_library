<?php

namespace App\Http\Controllers\ApiIa;

use App\Http\Controllers\Controller;
use App\Services\IA\Moderation\ContentModerationService;
use Illuminate\Http\Request;

class ChatRiskAlertController extends Controller
{
    public function store(Request $request)
    {
        $usuario = auth('sanctum')->user();

        if (!$usuario) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->validate([
            'mensaje' => 'required|string|max:4000',
        ]);

        $moderationService = app(ContentModerationService::class);
        $payload = [
            'tipo_contenido' => 'chat_ia',
            'contenido' => [
                'texto' => $data['mensaje'],
            ],
            'contexto' => [
                'usuario_rol' => $usuario->usuario_rol,
                'origen' => 'chat_ia',
            ],
        ];

        $analysis = $moderationService->analyze($payload);
        $moderationService->record(
            'chat_ia',
            null,
            $usuario->usuario_id,
            $analysis,
            $payload
        );

        return response()->json(['ok' => true], 200);
    }
}
