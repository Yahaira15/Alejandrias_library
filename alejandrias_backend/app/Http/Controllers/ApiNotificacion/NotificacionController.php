<?php

namespace App\Http\Controllers\ApiNotificacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    // 🔹 LISTAR
    public function index()
    {
        $usuario = Auth::user();

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

    // 🔹 CREAR
    public function store(Request $request)
    {
        $request->validate([
            'notificacion_tipo' => 'required|string',
            'notificacion_contenido' => 'required|string'
        ]);

        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json([
                'error' => 'No autenticado'
            ], 401);
        }

        $notificacion = new Notificacion();

        $notificacion->notificacion_usuario_id = $usuario->usuario_id;
        $notificacion->notificacion_tipo = $request->notificacion_tipo;
        $notificacion->notificacion_contenido = $request->notificacion_contenido;
        $notificacion->notificacion_leida = false;
        $notificacion->notificacion_fecha = now();

        $notificacion->save();

        return response()->json($notificacion, 201);
    }

    // 🔹 MARCAR COMO LEÍDA
    public function marcarLeida($id)
    {
        $usuario = Auth::user();

        $notificacion = Notificacion::find($id);

        if (!$notificacion) {
            return response()->json([
                'error' => 'No encontrada'
            ], 404);
        }

        if ($notificacion->notificacion_usuario_id != $usuario->usuario_id) {
            return response()->json([
                'error' => 'No autorizado'
            ], 403);
        }

        $notificacion->notificacion_leida = true;

        $notificacion->save();

        return response()->json([
            'mensaje' => 'Notificación leída'
        ], 200);
    }

    // 🔹 ELIMINAR
    public function destroy($id)
    {
        $usuario = Auth::user();

        $notificacion = Notificacion::find($id);

        if (!$notificacion) {
            return response()->json([
                'error' => 'No encontrada'
            ], 404);
        }

        if ($notificacion->notificacion_usuario_id != $usuario->usuario_id) {
            return response()->json([
                'error' => 'No autorizado'
            ], 403);
        }

        $notificacion->delete();

        return response()->json([
            'mensaje' => 'Notificación eliminada'
        ], 200);
    }
}
