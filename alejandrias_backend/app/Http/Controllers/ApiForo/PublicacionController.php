<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Publicacion;

class PublicacionController extends Controller
{
    // 🔹 GET /foros/{foroId}/publicaciones
    public function index($foroId)
    {
        $publicaciones = Publicacion::where('publicacion_foro_id', $foroId)
            ->with('usuario')
            ->orderBy('publicacion_fecha_creacion', 'desc')
            ->get();

        return response()->json($publicaciones, 200);
    }

    // 🔹 POST /foros/{foroId}/publicaciones
    public function store(Request $request, $foroId)
    {
        $request->validate([
            'publicacion_usuario_id' => 'required|exists:usuario,usuario_id',
            'publicacion_titulo' => 'required|string|max:255',
            'publicacion_contenido' => 'required|string',
        ]);

        $publicacion = new Publicacion();
        $publicacion->publicacion_foro_id = $foroId; // 🔥 clave
        $publicacion->publicacion_usuario_id = $request->publicacion_usuario_id;
        $publicacion->publicacion_titulo = $request->publicacion_titulo;
        $publicacion->publicacion_contenido = $request->publicacion_contenido;
        $publicacion->publicacion_destacada = $request->publicacion_destacada ?? false;

        $publicacion->save();

        return response()->json($publicacion, 201);
    }

    // 🔹 GET /publicaciones/{id}
    public function show($id)
    {
        $publicacion = Publicacion::with(['usuario', 'foro'])->find($id);

        if (!$publicacion) {
            return response()->json(['error' => 'No encontrada'], 404);
        }

        return response()->json($publicacion, 200);
    }

        // 🔹 PUT /publicaciones/{id}
    public function update(Request $request, $id)
    {
        $publicacion = Publicacion::find($id);

        if (!$publicacion) {
            return response()->json(['error' => 'No encontrada'], 404);
        }

        if ($publicacion->publicacion_usuario_id != $request->publicacion_usuario_id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // 🔥 ASIGNACIÓN MANUAL (CLAVE)
        $publicacion->publicacion_titulo = $request->publicacion_titulo;
        $publicacion->publicacion_contenido = $request->publicacion_contenido;
        $publicacion->publicacion_destacada = $request->publicacion_destacada ? true : false;
        $publicacion->publicacion_fecha_actualizacion = now();

        $publicacion->save();

        return response()->json($publicacion, 200);
    }

    // 🔹 DELETE /publicaciones/{id}
    public function destroy(Request $request, $id)
    {
        $publicacion = Publicacion::find($id);

        if (!$publicacion) {
            return response()->json(['error' => 'No encontrada'], 404);
        }

        // 🔥 validar dueño
        if ($publicacion->publicacion_usuario_id != $request->publicacion_usuario_id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $publicacion->delete();

        return response()->json(['mensaje' => 'Eliminada'], 200);
    }
}