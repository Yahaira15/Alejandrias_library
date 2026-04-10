<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Publicacion;

class PublicacionController extends Controller
{
    public function index($foroId)
    {
        $publicaciones = Publicacion::where('publicacion_foro_id', $foroId)
            ->with('usuario')
            ->withCount('comentarios')
            ->orderBy('publicacion_destacada', 'desc')
            ->orderBy('publicacion_fecha_creacion', 'desc')
            ->get();

        return response()->json($publicaciones, 200);
    }

    public function store(Request $request, $foroId)
    {
        try {
            $usuario = auth('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $request->validate([
                'publicacion_titulo' => 'required|string|max:255',
                'publicacion_contenido' => 'required|string',
                'publicacion_destacada' => 'nullable|boolean'
            ]);

            $publicacion = new Publicacion();
            $publicacion->publicacion_foro_id = $foroId;
            $publicacion->publicacion_usuario_id = $usuario->usuario_id;
            $publicacion->publicacion_titulo = $request->publicacion_titulo;
            $publicacion->publicacion_contenido = $request->publicacion_contenido;
            $publicacion->publicacion_destacada = $request->boolean('publicacion_destacada');
            $publicacion->publicacion_fecha_creacion = now();
            $publicacion->publicacion_fecha_actualizacion = now();

            $publicacion->save();

            return response()->json($publicacion->load('usuario'), 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $publicacion = Publicacion::with(['usuario', 'foro'])
            ->withCount('comentarios')
            ->find($id);

        if (!$publicacion) {
            return response()->json(['error' => 'No encontrada'], 404);
        }

        return response()->json($publicacion, 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $usuario = auth('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $publicacion = Publicacion::find($id);

            if (!$publicacion) {
                return response()->json(['error' => 'No encontrada'], 404);
            }

            if ($publicacion->publicacion_usuario_id != $usuario->usuario_id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $request->validate([
                'publicacion_titulo' => 'required|string|max:255',
                'publicacion_contenido' => 'required|string',
                'publicacion_destacada' => 'nullable|boolean'
            ]);

            $publicacion->update([
                'publicacion_titulo' => $request->publicacion_titulo,
                'publicacion_contenido' => $request->publicacion_contenido,
                'publicacion_destacada' => $request->boolean('publicacion_destacada'),
                'publicacion_fecha_actualizacion' => now()
            ]);

            return response()->json($publicacion, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $usuario = auth('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $publicacion = Publicacion::find($id);

            if (!$publicacion) {
                return response()->json(['error' => 'No encontrada'], 404);
            }

            if ($publicacion->publicacion_usuario_id != $usuario->usuario_id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $publicacion->delete();

            return response()->json(['mensaje' => 'Eliminada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
