<?php

namespace App\Http\Controllers\ApiGamification;

use App\Http\Controllers\Controller;
use App\Models\Publicacion;
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
            'publicacion_id' => 'nullable|integer|exists:publicacion,publicacion_id',
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

        $origen = null;
        if ($data['accion'] === 'lectura_publicacion' && !empty($data['publicacion_id'])) {
            $origen = Publicacion::find((int) $data['publicacion_id']);
        }

        $metadata = $data['metadata'] ?? [];
        if (!empty($data['publicacion_id'])) {
            $metadata['publicacion_id'] = (int) $data['publicacion_id'];
        }

        $evento = $gamification->award($request->user(), $data['accion'], $origen, $metadata);

        return response()->json([
            'evento' => $evento,
            'progreso' => $gamification->profile($request->user()),
        ], $evento ? 201 : 200);
    }
}
