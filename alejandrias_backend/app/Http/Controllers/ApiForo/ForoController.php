<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Foro;

class ForoController extends Controller
{
    public function index() {
        $foros = Foro::with(['usuario', 'categoria'])->get();

        return response()->json($foros, 200);
    }

    public function store(Request $request)
{
    $request->validate([
        'foro_titulo' => 'required|string|max:255',
        'foro_descripcion' => 'required|string',
        'foro_categoria_id' => 'required|exists:categoria,categoria_id',
        'foro_creador_id' => 'required|exists:usuario,usuario_id',
        'foro_privado' => 'required|boolean'
    ]);

    $foro = Foro::create([
        'foro_titulo' => $request->foro_titulo,
        'foro_descripcion' => $request->foro_descripcion,
        'foro_categoria_id' => $request->foro_categoria_id,
        'foro_creador_id' => $request->foro_creador_id,
        'foro_privado' => $request->foro_privado
    ]);

    return response()->json($foro, 201);
}

    public function show($id) {
        $foro = Foro::with(['usuario', 'categoria'])->find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        return response()->json($foro, 200);
    }

    public function update(Request $request, $id) {
        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        $request->validate([
            'foro_titulo' => 'sometimes|required|string|max:255',
            'foro_descripcion' => 'sometimes|required|string',
            'foro_categoria_id' => 'sometimes|required|exists:categoria,categoria_id',
            'foro_creador_id' => 'sometimes|required|exists:usuario,usuario_id',
            'foro_privado' => 'sometimes|required|boolean'
        ]);

        $foro->update($request->only(['foro_titulo', 'foro_descripcion', 'foro_categoria_id', 'foro_privado']));

        return response()->json($foro, 200);
    }

    public function destroy($id) {
        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        $foro->delete();

        return response()->json(['message' => 'Foro eliminado'], 200);
    }
}
