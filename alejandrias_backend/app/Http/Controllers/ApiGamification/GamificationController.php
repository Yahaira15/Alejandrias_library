<?php

namespace App\Http\Controllers\ApiGamification;

use App\Http\Controllers\Controller;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function panel(Request $request, GamificationService $gamification)
    {
        return response()->json($gamification->panel($request->user()));
    }

    public function racha(Request $request, GamificationService $gamification)
    {
        return response()->json($gamification->recordDailyAccess($request->user()));
    }

    public function reclamarRacha(Request $request, GamificationService $gamification)
    {
        return response()->json([
            'racha' => $gamification->claimStreakReward($request->user()),
            'progreso' => $gamification->profile($request->user()),
        ]);
    }

    public function misiones(Request $request, GamificationService $gamification)
    {
        return response()->json($gamification->dailyMissions($request->user()));
    }

    public function reclamarMision(Request $request, int $usuarioMisionId, GamificationService $gamification)
    {
        return response()->json([
            'mision' => $gamification->claimMission($request->user(), $usuarioMisionId),
            'misiones' => $gamification->dailyMissions($request->user()),
            'progreso' => $gamification->profile($request->user()),
        ]);
    }

    public function ranking(Request $request, GamificationService $gamification)
    {
        $data = $request->validate([
            'tipo' => 'nullable|in:xp,puntos,publicaciones,comentarios',
            'periodo' => 'nullable|in:global,semanal',
            'foro_id' => 'nullable|integer',
            'limite' => 'nullable|integer|min:1|max:50',
        ]);

        return response()->json($gamification->ranking(
            $data['tipo'] ?? 'xp',
            $data['periodo'] ?? 'global',
            (int) ($data['limite'] ?? 10),
            isset($data['foro_id']) ? (int) $data['foro_id'] : null
        ));
    }
}
