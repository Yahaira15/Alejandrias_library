<?php

namespace App\Http\Controllers\ApiCategoria;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Categoria;
use App\Models\Foro;

class CategoriaController extends Controller
{
    public function index()
    {
        return response()->json(Categoria::all(), 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    public function show($id)
    {
        return response()->json(Categoria::find($id), 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    public function foros($id)
    {
        $categoria = Categoria::with('foros')->find($id);
        return response()->json($categoria->foros, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    public function store(Request $request)
    {
        $request->validate([
            'categoria_nombre' => 'required|max:200',
            'categoria_descripcion' => 'nullable'
        ]);

        $categoria = Categoria::create([
            'categoria_nombre' => $request->categoria_nombre,
            'categoria_descripcion' => $request->categoria_descripcion
        ]);

        return response()->json([
            'message' => 'Categoría creada correctamente',
            'data' => $categoria
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }

        $data = $request->validate([
            'categoria_nombre' => 'required|max:200',
            'categoria_descripcion' => 'nullable'
        ]);

        $categoria->update($data);

        return response()->json([
            'message' => 'Categoria actualizada correctamente',
            'data' => $categoria
        ], 200);
    }

    public function destroy($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }

        if (Foro::where('foro_categoria_id', $categoria->categoria_id)->exists()) {
            return response()->json([
                'message' => 'No puedes eliminar una categoria con foros asociados'
            ], 409);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoria eliminada'], 200);
    }

}
