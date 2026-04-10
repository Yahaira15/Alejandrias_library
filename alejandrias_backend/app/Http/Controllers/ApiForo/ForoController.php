<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Foro;

class ForoController extends Controller
{
    public function index()
    {
        $foros = Foro::with(['usuario', 'categoria'])->get();
        return response()->json($foros, 200);
    }

    public function misForos()
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $foros = Foro::where('foro_creador_id', $usuario->usuario_id)
            ->with(['usuario', 'categoria'])
            ->get();

        return response()->json($foros, 200);
    }

    public function forosPublicos()
    {
        $foros = Foro::where('foro_privado', false)
            ->orWhereNull('foro_privado')
            ->with(['usuario', 'categoria'])
            ->get();

        return response()->json($foros, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'foro_titulo' => 'required|string|max:255',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
            'foro_privado' => 'nullable|boolean',
        ]);

        try {
            $usuario = Auth::guard('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if ($usuario->usuario_rol !== 'lider') {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $foro = new Foro();
            $foro->foro_titulo = $validated['foro_titulo'];
            $foro->foro_descripcion = $validated['foro_descripcion'];
            $foro->foro_categoria_id = $validated['foro_categoria_id'];
            $foro->foro_creador_id = $usuario->usuario_id;
            $foro->foro_privado = $request->boolean('foro_privado');

            $foro->save();

            return response()->json($foro->load(['usuario', 'categoria']), 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $foro = Foro::with(['usuario', 'categoria'])->find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        return response()->json($foro, 200);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'foro_titulo' => 'sometimes|required|string|max:255',
            'foro_descripcion' => 'sometimes|required|string',
            'foro_categoria_id' => 'sometimes|required|exists:categoria,categoria_id',
            'foro_privado' => 'sometimes|boolean',
        ]);

        try {
            $usuario = Auth::guard('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if ($usuario->usuario_rol !== 'lider') {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $foro = Foro::find($id);

            if (!$foro) {
                return response()->json(['error' => 'Foro no encontrado'], 404);
            }

            if ($foro->foro_creador_id != $usuario->usuario_id) {
                return response()->json(['error' => 'No puedes editar este foro'], 403);
            }

            $foro->fill([
                'foro_titulo' => $validated['foro_titulo'] ?? $foro->foro_titulo,
                'foro_descripcion' => $validated['foro_descripcion'] ?? $foro->foro_descripcion,
                'foro_categoria_id' => $validated['foro_categoria_id'] ?? $foro->foro_categoria_id,
            ]);

            if (array_key_exists('foro_privado', $validated)) {
                $foro->foro_privado = $request->boolean('foro_privado');
            }

            $foro->save();

            return response()->json([
                'mensaje' => 'Foro actualizado',
                'foro' => $foro->load(['usuario', 'categoria'])
            ]);
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
            $usuario = Auth::guard('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if ($usuario->usuario_rol !== 'lider') {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $foro = Foro::find($id);

            if (!$foro) {
                return response()->json(['error' => 'Foro no encontrado'], 404);
            }

            if ($foro->foro_creador_id != $usuario->usuario_id) {
                return response()->json(['error' => 'No puedes eliminar este foro'], 403);
            }

            $foro->delete();

            return response()->json([
                'mensaje' => 'Foro eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
