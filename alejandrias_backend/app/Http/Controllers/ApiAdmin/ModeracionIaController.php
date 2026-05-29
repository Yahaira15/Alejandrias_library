<?php

namespace App\Http\Controllers\ApiAdmin;

use App\Http\Controllers\Controller;
use App\Models\Comentario;
use App\Models\Foro;
use App\Models\ModeracionIa;
use App\Models\Publicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ModeracionIaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeReviewer($request);

        $query = ModeracionIa::with(['usuario', 'publicacion.foro', 'foro', 'comentario.publicacion'])
            ->orderBy('moderacion_id', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        } else {
            $query->whereIn('estado', ['revision', 'bloqueado']);
        }

        if ($request->boolean('pendientes', true)) {
            $query->where('revisado', false);
        }

        return response()->json($query->limit((int) $request->integer('limit', 100))->get(), 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeReviewer($request);

        return response()->json(
            ModeracionIa::with(['usuario', 'publicacion.foro', 'foro', 'comentario.publicacion'])->findOrFail($id),
            200
        );
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        $this->authorizeReviewer($request);

        $moderacion = ModeracionIa::findOrFail($id);
        $this->marcarDecision($moderacion, $request->user()->usuario_id, 'aprobado', 'visible');

        return response()->json($moderacion->fresh(['usuario', 'publicacion.foro', 'foro', 'comentario.publicacion']), 200);
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        $this->authorizeReviewer($request);

        $moderacion = ModeracionIa::findOrFail($id);
        $this->marcarDecision($moderacion, $request->user()->usuario_id, 'rechazado', 'bloqueado');

        return response()->json($moderacion->fresh(['usuario', 'publicacion.foro', 'foro', 'comentario.publicacion']), 200);
    }

    private function authorizeReviewer(Request $request): void
    {
        $usuario = $request->user();

        if (!$usuario || !in_array($usuario->usuario_rol, ['admin', 'lider'], true)) {
            abort(403, 'Solo lideres o administradores pueden revisar moderacion IA.');
        }
    }

    private function marcarDecision(ModeracionIa $moderacion, int $reviewerId, string $decision, string $estadoContenido): void
    {
        $moderacion->estado = $estadoContenido;
        $moderacion->revisado = true;
        $moderacion->revisado_por = $reviewerId;
        if (Schema::hasColumn('moderacion_ia', 'moderado_por')) {
            $moderacion->moderado_por = $reviewerId;
        }
        $moderacion->decision_admin = $decision;
        $moderacion->save();

        $this->actualizarContenido($moderacion, $estadoContenido);
    }

    private function actualizarContenido(ModeracionIa $moderacion, string $estadoContenido): void
    {
        if ($moderacion->comentario_id) {
            $this->actualizarComentario((int) $moderacion->comentario_id, $estadoContenido, $moderacion);
            return;
        }

        if ($moderacion->publicacion_id) {
            $this->actualizarPublicacion((int) $moderacion->publicacion_id, $estadoContenido, $moderacion);
            return;
        }

        if ($moderacion->foro_id) {
            $this->actualizarForo((int) $moderacion->foro_id, $estadoContenido);
        }
    }

    private function actualizarPublicacion(int $id, string $estadoContenido, ModeracionIa $moderacion): void
    {
        $publicacion = Publicacion::find($id);
        if (!$publicacion) {
            return;
        }

        if (Schema::hasColumn('publicacion', 'estado_moderacion')) {
            $publicacion->estado_moderacion = $estadoContenido;
        }
        if (Schema::hasColumn('publicacion', 'ia_riesgo')) {
            $publicacion->ia_riesgo = $moderacion->riesgo;
        }
        if (Schema::hasColumn('publicacion', 'ia_razon')) {
            $publicacion->ia_razon = $moderacion->razon;
        }

        $publicacion->save();
    }

    private function actualizarComentario(int $id, string $estadoContenido, ModeracionIa $moderacion): void
    {
        $comentario = Comentario::find($id);
        if (!$comentario) {
            return;
        }

        if (Schema::hasColumn('comentario', 'estado_moderacion')) {
            $comentario->estado_moderacion = $estadoContenido;
        }
        if (Schema::hasColumn('comentario', 'ia_riesgo')) {
            $comentario->ia_riesgo = $moderacion->riesgo;
        }
        if (Schema::hasColumn('comentario', 'ia_razon')) {
            $comentario->ia_razon = $moderacion->razon;
        }

        $comentario->save();
    }

    private function actualizarForo(int $id, string $estadoContenido): void
    {
        $foro = Foro::find($id);
        if (!$foro) {
            return;
        }

        if (Schema::hasColumn('foro', 'foro_estado_moderacion')) {
            $foro->foro_estado_moderacion = $estadoContenido === 'visible' ? 'permitido' : 'bloqueado';
        }
        if (Schema::hasColumn('foro', 'foro_visibilidad')) {
            $foro->foro_visibilidad = $estadoContenido;
        }

        $foro->save();
    }
}
