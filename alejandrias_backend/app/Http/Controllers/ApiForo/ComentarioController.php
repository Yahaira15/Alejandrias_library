<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use App\Models\Comentario;
use App\Models\Publicacion;
use Illuminate\Http\Request;

class ComentarioController extends Controller
{
    /**
     * Listar comentarios de una publicación
     */
    public function index($publicacionId)
    {
        // Validar que la publicación exista
        $publicacion = Publicacion::find($publicacionId);

        if (!$publicacion) {
            return response()->json(['message' => 'Publicación no encontrada'], 404);
        }

        $comentarios = Comentario::where('comentario_publicacion_id', $publicacionId)
            ->with('usuario')
            ->get();

        return response()->json($comentarios, 200);
    }

    /**
     * Crear comentario en una publicación
     */
    public function store(Request $request, $publicacionId)
    {
        // Validar que la publicación exista
        $publicacion = Publicacion::find($publicacionId);

        if (!$publicacion) {
            return response()->json(['message' => 'Publicación no encontrada'], 404);
        }

        $data = $request->validate([
            'comentario_usuario_id' => 'required|exists:usuario,usuario_id',
            'comentario_contenido' => 'required|string'
        ]);

        // Asignar automáticamente la publicación
        $data['comentario_publicacion_id'] = $publicacionId;

        $comentario = Comentario::create($data);

        return response()->json($comentario, 201);
    }

    /**
     * Mostrar un comentario específico
     */
    public function show($id)
    {
        $comentario = Comentario::with(['usuario', 'publicacion'])->find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        return response()->json($comentario, 200);
    }

    /**
     * Actualizar comentario
     */
    public function update(Request $request, $id)
    {
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        // 🔥 Verificación de propietario
        if ($comentario->comentario_usuario_id != $request->comentario_usuario_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'comentario_contenido' => 'sometimes|string'
        ]);

        $comentario->update($data);

        return response()->json($comentario, 200);
    }

    /**
     * Eliminar comentario
     */
    public function destroy(Request $request, $id)
    {
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        // 🔥 Verificación de propietario
        if ($comentario->comentario_usuario_id != $request->comentario_usuario_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $comentario->delete();

        return response()->json(['message' => 'Comentario eliminado'], 200);
    }
}