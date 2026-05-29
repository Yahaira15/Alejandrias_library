<?php

namespace App\Http\Controllers\ApiCategoria;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Categoria;
use App\Models\Foro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

    public function subcategorias($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoria no encontrada'], 404);
        }

        $tabla = $this->tablaSubcategorias();
        $columnaCategoria = $this->columnaCategoriaSubcategoria($tabla);

        if (!$tabla || !$columnaCategoria) {
            return response()->json([], 200);
        }

        return response()->json(
            DB::table($tabla)->where($columnaCategoria, $id)->orderBy('subcategoria_id', 'desc')->get(),
            200
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'categoria_nombre' => 'required|max:200',
            'categoria_descripcion' => 'nullable',
            'categoria_imagen' => 'nullable'
        ]);

        $data = [
            'categoria_nombre' => $request->categoria_nombre,
            'categoria_descripcion' => $request->categoria_descripcion
        ];

        if (Schema::hasColumn('categoria', 'categoria_imagen')) {
            $data['categoria_imagen'] = $this->resolverImagen($request, 'categoria_imagen', 'categorias');
        }

        $categoria = Categoria::create($data);

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
            'categoria_descripcion' => 'nullable',
            'categoria_imagen' => 'nullable'
        ]);

        if (Schema::hasColumn('categoria', 'categoria_imagen')) {
            $data['categoria_imagen'] = $this->resolverImagen($request, 'categoria_imagen', 'categorias', $categoria->categoria_imagen ?? null);
        } else {
            unset($data['categoria_imagen']);
        }

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

    public function storeSubcategoria(Request $request)
    {
        $tabla = $this->tablaSubcategorias();
        $columnaCategoria = $this->columnaCategoriaSubcategoria($tabla);

        if (!$tabla || !$columnaCategoria) {
            return response()->json(['message' => 'La tabla de subcategorias no esta disponible'], 422);
        }

        $data = $request->validate([
            'subcategoria_nombre' => 'required|max:200',
            'subcategoria_descripcion' => 'nullable',
            'categoria_id' => 'required|exists:categoria,categoria_id',
            'subcategoria_imagen' => 'nullable',
        ]);

        $payload = [
            'subcategoria_nombre' => $data['subcategoria_nombre'],
            'subcategoria_descripcion' => $data['subcategoria_descripcion'] ?? null,
            $columnaCategoria => $data['categoria_id'],
        ];

        if (Schema::hasColumn($tabla, 'subcategoria_imagen')) {
            $payload['subcategoria_imagen'] = $this->resolverImagen($request, 'subcategoria_imagen', 'subcategorias');
        }

        $id = DB::table($tabla)->insertGetId($payload, 'subcategoria_id');

        return response()->json(DB::table($tabla)->where('subcategoria_id', $id)->first(), 201);
    }

    public function updateSubcategoria(Request $request, $id)
    {
        $tabla = $this->tablaSubcategorias();
        $columnaCategoria = $this->columnaCategoriaSubcategoria($tabla);

        if (!$tabla || !$columnaCategoria) {
            return response()->json(['message' => 'La tabla de subcategorias no esta disponible'], 422);
        }

        $subcategoria = DB::table($tabla)->where('subcategoria_id', $id)->first();

        if (!$subcategoria) {
            return response()->json(['message' => 'Subcategoria no encontrada'], 404);
        }

        $data = $request->validate([
            'subcategoria_nombre' => 'required|max:200',
            'subcategoria_descripcion' => 'nullable',
            'categoria_id' => 'required|exists:categoria,categoria_id',
            'subcategoria_imagen' => 'nullable',
        ]);

        $payload = [
            'subcategoria_nombre' => $data['subcategoria_nombre'],
            'subcategoria_descripcion' => $data['subcategoria_descripcion'] ?? null,
            $columnaCategoria => $data['categoria_id'],
        ];

        if (Schema::hasColumn($tabla, 'subcategoria_imagen')) {
            $payload['subcategoria_imagen'] = $this->resolverImagen($request, 'subcategoria_imagen', 'subcategorias', $subcategoria->subcategoria_imagen ?? null);
        }

        DB::table($tabla)->where('subcategoria_id', $id)->update($payload);

        return response()->json(DB::table($tabla)->where('subcategoria_id', $id)->first(), 200);
    }

    public function destroySubcategoria($id)
    {
        $tabla = $this->tablaSubcategorias();

        if (!$tabla) {
            return response()->json(['message' => 'La tabla de subcategorias no esta disponible'], 422);
        }

        if (Schema::hasColumn('foro', 'subcategoria_id') && Foro::where('subcategoria_id', $id)->exists()) {
            return response()->json(['message' => 'No puedes eliminar una subcategoria con foros asociados'], 409);
        }

        DB::table($tabla)->where('subcategoria_id', $id)->delete();

        return response()->json(['message' => 'Subcategoria eliminada'], 200);
    }

    private function tablaSubcategorias(): ?string
    {
        foreach (['subcategoria', 'subcategorias'] as $tabla) {
            if (Schema::hasTable($tabla)) {
                return $tabla;
            }
        }

        return null;
    }

    private function columnaCategoriaSubcategoria(?string $tabla): ?string
    {
        if (!$tabla) {
            return null;
        }

        foreach (['subcategoria_categoria_id', 'categoria_id'] as $columna) {
            if (Schema::hasColumn($tabla, $columna)) {
                return $columna;
            }
        }

        return null;
    }

    private function resolverImagen(Request $request, string $campo, string $carpeta, ?string $imagenActual = null): ?string
    {
        if ($request->hasFile($campo)) {
            $request->validate([
                $campo => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $path = $request->file($campo)->store($carpeta, 'public');

            return Storage::disk('public')->url($path);
        }

        $imagen = $request->input($campo);

        if (is_string($imagen) && trim($imagen) !== '') {
            return trim($imagen);
        }

        return $imagenActual;
    }

}
