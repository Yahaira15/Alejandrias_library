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
        return Categoria::all();
    }

    public function show($id)
    {
        return Categoria::find($id);
    }

    public function foros($id)
    {
        $categoria = Categoria::with('foros')->find($id);
        return $categoria->foros;
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
        ], 201);
    }

}
