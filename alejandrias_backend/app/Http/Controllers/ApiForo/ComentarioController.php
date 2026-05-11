<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use App\Models\Comentario;
use App\Models\Publicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notificacion;

class ComentarioController extends Controller
{
    public function index($publicacionId)
    {
        $publicacion = Publicacion::find($publicacionId);

        if (!$publicacion) {
            return response()->json(['message' => 'Publicacion no encontrada'], 404);
        }

        if ($publicacion->foro?->foro_privado) {
            $usuario = Auth::guard('sanctum')->user();
            $registrado = $usuario && (
                $publicacion->foro->foro_creador_id == $usuario->usuario_id
                || $publicacion->foro->miembros()
                    ->where('usuario.usuario_id', $usuario->usuario_id)
                    ->exists()
            );

            if (!$registrado) {
                return response()->json(['error' => 'Debes registrarte en el foro antes de ver sus comentarios'], 403);
            }
        }

        $comentarios = Comentario::where('comentario_publicacion_id', $publicacionId)
            ->with('usuario')
            ->orderBy('comentario_id', 'asc')
            ->get();

        return response()->json($comentarios, 200);
    }

    public function store(Request $request, $publicacionId)
    {
        $publicacion = Publicacion::find($publicacionId);

        if (!$publicacion) {
            return response()->json(['message' => 'Publicacion no encontrada'], 404);
        }

        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $foro = $publicacion->foro;
        $registrado = $foro && (
            $foro->foro_creador_id == $usuario->usuario_id
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists()
        );

        if (!$registrado) {
            return response()->json(['error' => 'Debes registrarte en el foro antes de comentar'], 403);
        }

        $data = $request->validate([
            'comentario_contenido' => 'required|string|max:2000',
        ]);

        $data['comentario_publicacion_id'] = $publicacionId;
        $data['comentario_usuario_id'] = $usuario->usuario_id;

        $comentario = Comentario::create($data);

        // 🔔 Notificar dueño de publicación

            $duenoPublicacion = $publicacion->usuario;

            // ❌ Evitar auto notificación
            if ($duenoPublicacion &&
                $duenoPublicacion->usuario_id != $usuario->usuario_id) {

                Notificacion::create([

                    'notificacion_usuario_id' =>
                        $duenoPublicacion->usuario_id,

                    'notificacion_tipo' =>
                        'nuevo_comentario',

                    'notificacion_contenido' =>
                        $usuario->usuario_apodo .
                        ' comentó tu publicación "' .
                        $publicacion->publicacion_titulo . '"',

                    'notificacion_leida' => false,

                    'notificacion_fecha' => now(),

                    'notificacion_url' =>
                        '/foro/' . $foro->foro_id .
                        '/publicacion/' . $publicacion->publicacion_id,

                    'notificacion_referencia_id' =>
                        $publicacion->publicacion_id
                ]);
            }

        return response()->json($comentario->load('usuario'), 201);
    }

    public function show($id)
    {
        $comentario = Comentario::with(['usuario', 'publicacion'])->find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        return response()->json($comentario, 200);
    }

    public function update(Request $request, $id)
    {
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario || $comentario->comentario_usuario_id != $usuario->usuario_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'comentario_contenido' => 'required|string|max:2000'
        ]);

        $comentario->update($data);

        return response()->json($comentario->load('usuario'), 200);
    }

    public function destroy($id)
    {
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario || $comentario->comentario_usuario_id != $usuario->usuario_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $comentario->delete();

        return response()->json(['message' => 'Comentario eliminado'], 200);
    }
}
