<?php

namespace App\Http\Controllers\ApiReporte;

use App\Http\Controllers\Controller;
use App\Models\Sancion;
use App\Models\Usuario;
use App\Services\Sanctions\SanctionService;
use Illuminate\Http\Request;

class SancionController extends Controller
{
    public function index()
    {
        app(SanctionService::class)->expireOldSanctions();

        return response()->json(
            Sancion::with(['usuario', 'admin', 'reporte'])->orderBy('sancion_id', 'desc')->get(),
            200
        );
    }

    public function store(Request $request)
    {
        $admin = auth('sanctum')->user();
        $data = $this->applyTypeRules($this->validatedData($request));

        $sancion = Sancion::create([
            ...$data,
            'sancion_admin_id' => $admin?->usuario_id,
            'sancion_fecha_inicio' => $data['sancion_fecha_inicio'] ?? now(),
            'sancion_activa' => $data['sancion_activa'] ?? true,
        ]);

        $this->syncUsuarioBloqueado($sancion->sancion_usuario_id);

        return response()->json($sancion->load(['usuario', 'admin', 'reporte']), 201);
    }

    public function update(Request $request, $id)
    {
        $sancion = Sancion::findOrFail($id);
        $data = $this->applyTypeRules($this->validatedData($request));
        $sancion->update($data);

        $this->syncUsuarioBloqueado($sancion->sancion_usuario_id);

        return response()->json($sancion->fresh(['usuario', 'admin', 'reporte']), 200);
    }

    public function destroy($id)
    {
        $sancion = Sancion::findOrFail($id);
        $usuarioId = $sancion->sancion_usuario_id;
        $sancion->delete();

        $this->syncUsuarioBloqueado($usuarioId);

        return response()->json(['mensaje' => 'Sancion eliminada'], 200);
    }

    private function syncUsuarioBloqueado(int $usuarioId): void
    {
        app(SanctionService::class)->syncUsuarioBloqueado($usuarioId);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'sancion_usuario_id' => 'required|exists:usuario,usuario_id',
            'sancion_tipo' => 'required|in:advertencia,restriccion,suspension,ban',
            'sancion_nivel' => 'nullable|integer|min:1|max:4',
            'sancion_motivo' => 'required|string|max:2000',
            'sancion_fecha_inicio' => 'nullable|date',
            'sancion_fecha_fin' => 'nullable|date',
            'sancion_activa' => 'nullable|boolean',
            'sancion_reporte_id' => 'nullable|exists:reporte,reporte_id',
            'bloquea_comentar' => 'nullable|boolean',
            'bloquea_publicar' => 'nullable|boolean',
            'bloquea_login' => 'nullable|boolean',
        ]);
    }

    private function applyTypeRules(array $data): array
    {
        $tipo = $data['sancion_tipo'];

        $rules = match ($tipo) {
            'advertencia' => [
                'sancion_nivel' => 1,
                'bloquea_comentar' => false,
                'bloquea_publicar' => false,
                'bloquea_login' => false,
            ],
            'restriccion' => [
                'sancion_nivel' => 2,
                'sancion_fecha_fin' => $data['sancion_fecha_fin'] ?? now()->addDay(),
                'bloquea_comentar' => true,
                'bloquea_publicar' => true,
                'bloquea_login' => false,
            ],
            'suspension' => [
                'sancion_nivel' => 3,
                'sancion_fecha_fin' => $data['sancion_fecha_fin'] ?? now()->addDays(7),
                'bloquea_comentar' => true,
                'bloquea_publicar' => true,
                'bloquea_login' => true,
            ],
            'ban' => [
                'sancion_nivel' => 4,
                'sancion_fecha_fin' => null,
                'bloquea_comentar' => true,
                'bloquea_publicar' => true,
                'bloquea_login' => true,
            ],
        };

        return [
            ...$data,
            ...$rules,
        ];
    }
}
