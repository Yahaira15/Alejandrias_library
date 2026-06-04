<?php

namespace App\Http\Controllers\ApiAdmin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Comentario;
use App\Models\Foro;
use App\Models\Publicacion;
use App\Models\Usuario;
use App\Services\IA\Moderation\ContentModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    public function usuarios()
    {
        return response()->json(Usuario::orderBy('usuario_id', 'desc')->get(), 200);
    }

    public function crearUsuario(Request $request)
    {
        $data = $request->validate([
            'usuario_nombre' => 'required|string|max:255',
            'usuario_apellido' => 'nullable|string|max:255',
            'usuario_apodo' => 'required|string|max:100|unique:usuario,usuario_apodo',
            'usuario_email' => 'required|email|unique:usuario,usuario_email',
            'usuario_password' => 'required|string|min:8',
            'usuario_rol' => 'required|in:explorador,lider,admin',
            'usuario_bio' => 'nullable|string',
            'usuario_bloqueado' => 'nullable|boolean',
        ]);

        $data['usuario_password'] = Hash::make($data['usuario_password']);
        $data['usuario_bloqueado'] = (bool) ($data['usuario_bloqueado'] ?? false);

        return response()->json(Usuario::create($data), 201);
    }

    public function actualizarUsuario(Request $request, $id)
    {
        $usuario = Usuario::findOrFail($id);
        $data = $request->validate([
            'usuario_nombre' => 'required|string|max:255',
            'usuario_apellido' => 'nullable|string|max:255',
            'usuario_apodo' => 'required|string|max:100|unique:usuario,usuario_apodo,' . $usuario->usuario_id . ',usuario_id',
            'usuario_email' => 'required|email|unique:usuario,usuario_email,' . $usuario->usuario_id . ',usuario_id',
            'usuario_password' => 'nullable|string|min:8',
            'usuario_rol' => 'required|in:explorador,lider,admin',
            'usuario_bio' => 'nullable|string',
            'usuario_bloqueado' => 'nullable|boolean',
        ]);

        if (!empty($data['usuario_password'])) {
            $data['usuario_password'] = Hash::make($data['usuario_password']);
        } else {
            unset($data['usuario_password']);
        }

        $data['usuario_bloqueado'] = (bool) ($data['usuario_bloqueado'] ?? false);
        $usuario->update($data);

        return response()->json($usuario, 200);
    }

    public function eliminarUsuario($id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json(['mensaje' => 'Usuario eliminado'], 200);
    }

    public function foros()
    {
        return response()->json(Foro::with(['usuario', 'categoria', 'subcategoria'])->orderBy('foro_id', 'desc')->get(), 200);
    }

    public function crearForo(Request $request)
    {
        $subcategoriaTable = $this->tablaSubcategorias();
        $data = $request->validate([
            'foro_titulo' => 'required|string|max:255',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
            'subcategoria_id' => $subcategoriaTable ? "nullable|exists:{$subcategoriaTable},subcategoria_id" : 'nullable|integer',
            'foro_creador_id' => 'required|exists:usuario,usuario_id',
            'foro_privado' => 'nullable|boolean',
            'foro_password' => 'nullable|required_if:foro_privado,true|regex:/^[A-Za-z0-9]{8}$/',
            'foro_imagen' => 'nullable',
        ]);

        $data['foro_privado'] = (bool) ($data['foro_privado'] ?? false);
        $data['foro_password'] = $data['foro_privado'] && !empty($data['foro_password'])
            ? Crypt::encryptString($data['foro_password'])
            : null;
        $data['subcategoria_id'] = $data['subcategoria_id'] ?? null;

        if (Schema::hasColumn('foro', 'foro_imagen')) {
            $data['foro_imagen'] = $this->resolverImagenForo($request);
        } else {
            unset($data['foro_imagen']);
        }

        if (!Schema::hasColumn('foro', 'subcategoria_id')) {
            unset($data['subcategoria_id']);
        }

        $moderationService = app(ContentModerationService::class);
        $usuario = Usuario::find($data['foro_creador_id']);
        $moderationPayload = [
            'tipo_contenido' => 'foro',
            'contenido' => [
                'nombre' => $data['foro_titulo'],
                'titulo' => $data['foro_titulo'],
                'texto' => $data['foro_descripcion'],
            ],
            'contexto' => [
                'categoria_id' => $data['foro_categoria_id'],
                'usuario_rol' => $usuario?->usuario_rol ?? 'admin',
                'origen' => 'admin',
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        if (($moderation['estado'] ?? 'revision') === 'bloqueado') {
            $moderationService->record('foro', null, $data['foro_creador_id'], $moderation, $moderationPayload);

            return response()->json([
                'error' => $moderation['mensaje_usuario'] ?? 'El foro fue bloqueado por moderacion IA y no fue creado.',
                '_moderacion' => $moderation,
            ], 422);
        }

        $foro = new Foro($data);
        $moderationService->applyToModel($foro, $moderation, 'foro');
        $foro->save();
        $moderationService->record('foro', $foro->foro_id, $data['foro_creador_id'], $moderation, $moderationPayload);
        $foro->miembros()->syncWithoutDetaching([$foro->foro_creador_id]);

        $response = $foro->load(['usuario', 'categoria', 'subcategoria'])->toArray();
        $response['_moderacion'] = $moderation;

        return response()->json($response, 201);
    }

    public function actualizarForo(Request $request, $id)
    {
        $foro = Foro::findOrFail($id);
        $subcategoriaTable = $this->tablaSubcategorias();
        $data = $request->validate([
            'foro_titulo' => 'required|string|max:255',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
            'subcategoria_id' => $subcategoriaTable ? "nullable|exists:{$subcategoriaTable},subcategoria_id" : 'nullable|integer',
            'foro_creador_id' => 'required|exists:usuario,usuario_id',
            'foro_privado' => 'nullable|boolean',
            'foro_password' => 'nullable|regex:/^[A-Za-z0-9]{8}$/',
            'foro_imagen' => 'nullable',
        ]);

        $data['foro_privado'] = (bool) ($data['foro_privado'] ?? false);
        $data['subcategoria_id'] = $data['subcategoria_id'] ?? null;

        $moderationService = app(ContentModerationService::class);
        $usuario = Usuario::find($data['foro_creador_id']);
        $moderationPayload = [
            'tipo_contenido' => 'foro',
            'contenido' => [
                'nombre' => $data['foro_titulo'],
                'titulo' => $data['foro_titulo'],
                'texto' => $data['foro_descripcion'],
            ],
            'contexto' => [
                'categoria_id' => $data['foro_categoria_id'],
                'usuario_rol' => $usuario?->usuario_rol ?? 'admin',
                'origen' => 'admin',
                'operacion' => 'actualizacion',
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        if (($moderation['estado'] ?? 'revision') === 'bloqueado') {
            $moderationService->applyToModel($foro, $moderation, 'foro');
            $foro->save();
            $moderationService->record('foro', $foro->foro_id, $data['foro_creador_id'], $moderation, $moderationPayload);

            return response()->json([
                'error' => $moderation['mensaje_usuario'] ?? 'La edicion fue bloqueada por moderacion IA. El foro fue ocultado.',
                '_moderacion' => $moderation,
            ], 422);
        }

        if (!$data['foro_privado']) {
            $data['foro_password'] = null;
        } elseif (!empty($data['foro_password'])) {
            $data['foro_password'] = Crypt::encryptString($data['foro_password']);
        } else {
            unset($data['foro_password']);
        }

        if (Schema::hasColumn('foro', 'foro_imagen')) {
            $data['foro_imagen'] = $this->resolverImagenForo($request, $foro->foro_imagen ?? null);
        } else {
            unset($data['foro_imagen']);
        }

        if (!Schema::hasColumn('foro', 'subcategoria_id')) {
            unset($data['subcategoria_id']);
        }

        $foro->update($data);
        $moderationService->applyToModel($foro, $moderation, 'foro');
        $foro->save();
        $moderationService->record('foro', $foro->foro_id, $data['foro_creador_id'], $moderation, $moderationPayload);
        $foro->miembros()->syncWithoutDetaching([$foro->foro_creador_id]);

        $response = $foro->load(['usuario', 'categoria', 'subcategoria'])->toArray();
        $response['_moderacion'] = $moderation;

        return response()->json($response, 200);
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

    private function resolverImagenForo(Request $request, ?string $imagenActual = null): ?string
    {
        if ($request->hasFile('foro_imagen')) {
            $request->validate([
                'foro_imagen' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $archivo = $request->file('foro_imagen');
            $archivo->store('foros', 'public');

            return $this->dataUrlImagenSubida($archivo);
        }

        $imagen = $request->input('foro_imagen');

        if (is_string($imagen) && trim($imagen) !== '') {
            return $this->normalizarRutaImagenForo($imagen);
        }

        return $imagenActual;
    }

    private function normalizarRutaImagenForo(string $imagen): string
    {
        $imagen = trim($imagen);

        if (preg_match('/^(data:|blob:)/i', $imagen)) {
            return $imagen;
        }

        if (preg_match('/^https?:\/\//i', $imagen)) {
            $path = parse_url($imagen, PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, '/storage/')) {
                return $path;
            }

            return $imagen;
        }

        if (str_starts_with($imagen, '/storage/')) {
            return $imagen;
        }

        $imagen = ltrim($imagen, '/');

        return str_starts_with($imagen, 'storage/')
            ? '/' . $imagen
            : '/storage/' . $imagen;
    }

    private function dataUrlImagenSubida($archivo): string
    {
        $mime = $archivo->getMimeType() ?: 'image/jpeg';
        $bytes = file_get_contents($archivo->getRealPath());

        return 'data:' . $mime . ';base64,' . base64_encode($bytes ?: '');
    }

    public function categorias()
    {
        return response()->json(Categoria::orderBy('categoria_id', 'desc')->get(), 200);
    }

    public function publicaciones()
    {
        return response()->json(
            Publicacion::with(['usuario', 'foro'])->withCount('comentarios')->orderBy('publicacion_id', 'desc')->get(),
            200
        );
    }

    public function crearPublicacion(Request $request)
    {
        $data = $request->validate([
            'publicacion_foro_id' => 'required|exists:foro,foro_id',
            'publicacion_usuario_id' => 'required|exists:usuario,usuario_id',
            'publicacion_titulo' => 'required|string|max:255',
            'publicacion_contenido' => 'required|string',
            'publicacion_destacada' => 'nullable|boolean',
        ]);

        $data['publicacion_destacada'] = (bool) ($data['publicacion_destacada'] ?? false);
        $data['publicacion_fecha_creacion'] = now();
        $data['publicacion_fecha_actualizacion'] = now();

        $moderationService = app(ContentModerationService::class);
        $foro = Foro::with('categoria')->find($data['publicacion_foro_id']);
        $usuario = Usuario::find($data['publicacion_usuario_id']);
        $moderationPayload = [
            'tipo_contenido' => 'publicacion',
            'contenido' => [
                'titulo' => $data['publicacion_titulo'],
                'texto' => $data['publicacion_contenido'],
            ],
            'contexto' => [
                'foro_id' => $foro?->foro_id,
                'foro' => $foro?->foro_titulo,
                'categoria' => $foro?->categoria?->categoria_nombre,
                'usuario_rol' => $usuario?->usuario_rol ?? 'admin',
                'origen' => 'admin',
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        $publicacion = new Publicacion($data);
        $moderationService->applyToModel($publicacion, $moderation, 'publicacion');
        $publicacion->save();
        $moderationPersisted = $moderationService->record(
            'publicacion',
            $publicacion->publicacion_id,
            $data['publicacion_usuario_id'],
            $moderation,
            $moderationPayload
        );

        $response = $publicacion->load(['usuario', 'foro'])->toArray();
        $response['_moderacion'] = $moderation;
        $response['_moderacion_registrada'] = $moderationPersisted;

        return response()->json($response, 201);
    }

    public function actualizarPublicacion(Request $request, $id)
    {
        $publicacion = Publicacion::findOrFail($id);
        $data = $request->validate([
            'publicacion_foro_id' => 'required|exists:foro,foro_id',
            'publicacion_usuario_id' => 'required|exists:usuario,usuario_id',
            'publicacion_titulo' => 'required|string|max:255',
            'publicacion_contenido' => 'required|string',
            'publicacion_destacada' => 'nullable|boolean',
        ]);

        $data['publicacion_destacada'] = (bool) ($data['publicacion_destacada'] ?? false);
        $data['publicacion_fecha_actualizacion'] = now();
        $moderationService = app(ContentModerationService::class);
        $foro = Foro::with('categoria')->find($data['publicacion_foro_id']);
        $usuario = Usuario::find($data['publicacion_usuario_id']);
        $moderationPayload = [
            'tipo_contenido' => 'publicacion',
            'contenido' => [
                'titulo' => $data['publicacion_titulo'],
                'texto' => $data['publicacion_contenido'],
            ],
            'contexto' => [
                'foro_id' => $foro?->foro_id,
                'foro' => $foro?->foro_titulo,
                'categoria' => $foro?->categoria?->categoria_nombre,
                'usuario_rol' => $usuario?->usuario_rol ?? 'admin',
                'origen' => 'admin',
                'operacion' => 'actualizacion',
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        $publicacion->fill($data);
        $moderationService->applyToModel($publicacion, $moderation, 'publicacion');
        $publicacion->save();
        $moderationPersisted = $moderationService->record(
            'publicacion',
            $publicacion->publicacion_id,
            $data['publicacion_usuario_id'],
            $moderation,
            $moderationPayload
        );

        $response = $publicacion->load(['usuario', 'foro'])->toArray();
        $response['_moderacion'] = $moderation;
        $response['_moderacion_registrada'] = $moderationPersisted;

        return response()->json($response, 200);
    }

    public function comentarios()
    {
        return response()->json(
            Comentario::with(['usuario', 'publicacion'])->orderBy('comentario_id', 'desc')->get(),
            200
        );
    }

    public function crearComentario(Request $request)
    {
        $data = $request->validate([
            'comentario_usuario_id' => 'required|exists:usuario,usuario_id',
            'comentario_publicacion_id' => 'required|exists:publicacion,publicacion_id',
            'comentario_contenido' => 'required|string|max:2000',
        ]);
        $data['comentario_fecha_creacion'] = now();

        $moderationService = app(ContentModerationService::class);
        $publicacion = Publicacion::with(['foro.categoria'])->find($data['comentario_publicacion_id']);
        $usuario = Usuario::find($data['comentario_usuario_id']);
        $moderationPayload = [
            'tipo_contenido' => 'comentario',
            'contenido' => [
                'texto' => $data['comentario_contenido'],
            ],
            'contexto' => [
                'publicacion_id' => $publicacion?->publicacion_id,
                'foro_id' => $publicacion?->publicacion_foro_id,
                'publicacion' => $publicacion?->publicacion_titulo,
                'foro' => $publicacion?->foro?->foro_titulo,
                'categoria' => $publicacion?->foro?->categoria?->categoria_nombre,
                'usuario_rol' => $usuario?->usuario_rol ?? 'admin',
                'origen' => 'admin',
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        $comentario = new Comentario($data);
        $moderationService->applyToModel($comentario, $moderation, 'comentario');
        $comentario->save();
        $moderationPersisted = $moderationService->record(
            'comentario',
            $comentario->comentario_id,
            $data['comentario_usuario_id'],
            $moderation,
            $moderationPayload
        );

        $response = $comentario->load(['usuario', 'publicacion'])->toArray();
        $response['_moderacion'] = $moderation;
        $response['_moderacion_registrada'] = $moderationPersisted;

        return response()->json($response, 201);
    }

    public function actualizarComentario(Request $request, $id)
    {
        $comentario = Comentario::findOrFail($id);
        $data = $request->validate([
            'comentario_usuario_id' => 'required|exists:usuario,usuario_id',
            'comentario_publicacion_id' => 'required|exists:publicacion,publicacion_id',
            'comentario_contenido' => 'required|string|max:2000',
        ]);

        $moderationService = app(ContentModerationService::class);
        $publicacion = Publicacion::with(['foro.categoria'])->find($data['comentario_publicacion_id']);
        $usuario = Usuario::find($data['comentario_usuario_id']);
        $moderationPayload = [
            'tipo_contenido' => 'comentario',
            'contenido' => [
                'texto' => $data['comentario_contenido'],
            ],
            'contexto' => [
                'publicacion_id' => $publicacion?->publicacion_id,
                'foro_id' => $publicacion?->publicacion_foro_id,
                'publicacion' => $publicacion?->publicacion_titulo,
                'foro' => $publicacion?->foro?->foro_titulo,
                'categoria' => $publicacion?->foro?->categoria?->categoria_nombre,
                'usuario_rol' => $usuario?->usuario_rol ?? 'admin',
                'origen' => 'admin',
                'operacion' => 'actualizacion',
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        $comentario->fill($data);
        $moderationService->applyToModel($comentario, $moderation, 'comentario');
        $comentario->save();
        $moderationPersisted = $moderationService->record(
            'comentario',
            $comentario->comentario_id,
            $data['comentario_usuario_id'],
            $moderation,
            $moderationPayload
        );

        $response = $comentario->load(['usuario', 'publicacion'])->toArray();
        $response['_moderacion'] = $moderation;
        $response['_moderacion_registrada'] = $moderationPersisted;

        return response()->json($response, 200);
    }
}
