<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Notificacion;

class NotificacionController extends Controller
{
    // 🔹 LISTAR
    public function index()
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json([
                'error' => 'No autenticado'
            ], 401);
        }

        $notificaciones = Notificacion::where(
                'notificacion_usuario_id',
                $usuario->usuario_id
            )
            ->orderBy('notificacion_fecha', 'desc')
            ->get();

        return response()->json($notificaciones, 200);
    }

    // 🔹 MARCAR COMO LEÍDA
    public function marcarLeida($id)
{
    $usuario = Auth::guard('sanctum')->user();

    if (!$usuario) {
        return response()->json([
            'error' => 'No autenticado'
        ], 401);
    }

    $notificacion = Notificacion::find($id);

    if (!$notificacion) {
        return response()->json([
            'error' => 'Notificación no encontrada'
        ], 404);
    }

    if (
        $notificacion->notificacion_usuario_id
        != $usuario->usuario_id
    ) {
        return response()->json([
            'error' => 'No autorizado'
        ], 403);
    }

    $notificacion->notificacion_leida = true;

    $notificacion->save();

    return response()->json([
        'mensaje' => 'Notificación leída'
    ]);
}

    // 🔹 CONTADOR
    public function contador()
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json([
                'cantidad' => 0
            ], 200);
        }

        $cantidad = Notificacion::where(
                'notificacion_usuario_id',
                $usuario->usuario_id
            )
            ->where('notificacion_leida', false)
            ->count();

        return response()->json([
            'cantidad' => $cantidad
        ], 200);
    }
}