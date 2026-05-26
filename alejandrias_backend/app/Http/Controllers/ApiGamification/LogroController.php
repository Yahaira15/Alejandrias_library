<?php

namespace App\Http\Controllers\ApiGamification;

use App\Http\Controllers\Controller;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\Request;

class LogroController extends Controller
{
    public function perfil(Request $request, GamificationService $gamification)
    {
        return response()->json($gamification->profile($request->user()));
    }

    public function sincronizar(Request $request, GamificationService $gamification)
    {
        return response()->json($gamification->syncProgress($request->user()));
    }

    public function demo(Request $request, GamificationService $gamification)
    {
        return response()->json($gamification->grantDemoBadges($request->user()));
    }

    public function registrarEvento(Request $request, GamificationService $gamification)
    {
        $data = $request->validate([
            'accion' => 'required|string|max:60',
            'metadata' => 'nullable|array',
        ]);

        $accionesCliente = [
            'lectura_publicacion',
            'tiempo_activo_10m',
            'like_dado',
            'seguir_usuario',
        ];

        if (!in_array($data['accion'], $accionesCliente, true)) {
            return response()->json([
                'error' => 'Esta accion solo puede registrarse desde el servidor',
            ], 403);
        }

        $evento = $gamification->award($request->user(), $data['accion'], null, $data['metadata'] ?? []);

        return response()->json([
            'evento' => $evento,
            'progreso' => $gamification->profile($request->user()),
        ], $evento ? 201 : 200);
    }
}
