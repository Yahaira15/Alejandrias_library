<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Foro;

class ForoController extends Controller
{
    // 🔹 TODOS LOS FOROS
    public function index() {
        $foros = Foro::with(['usuario', 'categoria'])->get();

        return response()->json($foros, 200);
    }

<<<<<<< HEAD
    public function store(Request $request)
    {
        $request->validate([
            'foro_titulo' => 'required|string',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
        ]);

        $foro = new Foro();
        $foro->foro_titulo = $request->foro_titulo;
        $foro->foro_descripcion = $request->foro_descripcion;
        $foro->foro_categoria_id = $request->foro_categoria_id;

        $foro->foro_creador_id = auth()->id() ?? 9;

        $foro->save();

        return response()->json($foro, 201);
    }
=======
    public function misForos($id) {
    $foros = Foro::where('foro_creador_id', $id)
        ->with(['usuario', 'categoria'])
        ->get();

    return response()->json($foros, 200);
}
>>>>>>> feature-foros

    // 🔹 CREAR FORO
    public function store(Request $request)
    {
        $request->validate([
            'foro_titulo' => 'required|string',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
        ]);

        $foro = new Foro();
        $foro->foro_titulo = $request->foro_titulo;
        $foro->foro_descripcion = $request->foro_descripcion;
        $foro->foro_categoria_id = $request->foro_categoria_id;

        // 🔥 IMPORTANTE: usar usuario autenticado
        $foro->foro_creador_id = auth()->id();

        $foro->save();

        return response()->json($foro, 201);
    }

    // 🔹 VER FORO
    public function show($id) {
        $foro = Foro::with(['usuario', 'categoria'])->find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        return response()->json($foro, 200);
    }

    // 🔹 ACTUALIZAR
    public function update(Request $request, $id) {
        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        $request->validate([
            'foro_titulo' => 'sometimes|required|string|max:255',
            'foro_descripcion' => 'sometimes|required|string',
            'foro_categoria_id' => 'sometimes|required|exists:categoria,categoria_id',
            'foro_privado' => 'sometimes|required|boolean'
        ]);

        $foro->update($request->only([
            'foro_titulo',
            'foro_descripcion',
            'foro_categoria_id',
            'foro_privado'
        ]));

        return response()->json($foro, 200);
    }

    // 🔹 ELIMINAR
    public function destroy($id) {
        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        $foro->delete();

        return response()->json(['message' => 'Foro eliminado'], 200);
    }
}