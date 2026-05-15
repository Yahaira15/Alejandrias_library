<?php

namespace App\Http\Controllers\ApiAdmin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Comentario;
use App\Models\Foro;
use App\Models\Publicacion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

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
        return response()->json(Foro::with(['usuario', 'categoria'])->orderBy('foro_id', 'desc')->get(), 200);
    }

    public function crearForo(Request $request)
    {
        $data = $request->validate([
            'foro_titulo' => 'required|string|max:255',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
            'foro_creador_id' => 'required|exists:usuario,usuario_id',
            'foro_privado' => 'nullable|boolean',
            'foro_password' => 'nullable|required_if:foro_privado,true|regex:/^[A-Za-z0-9]{8}$/',
        ]);

        $data['foro_privado'] = (bool) ($data['foro_privado'] ?? false);
        $data['foro_password'] = $data['foro_privado'] && !empty($data['foro_password'])
            ? Crypt::encryptString($data['foro_password'])
            : null;

        $foro = Foro::create($data);
        $foro->miembros()->syncWithoutDetaching([$foro->foro_creador_id]);

        return response()->json($foro->load(['usuario', 'categoria']), 201);
    }

    public function actualizarForo(Request $request, $id)
    {
        $foro = Foro::findOrFail($id);
        $data = $request->validate([
            'foro_titulo' => 'required|string|max:255',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
            'foro_creador_id' => 'required|exists:usuario,usuario_id',
            'foro_privado' => 'nullable|boolean',
            'foro_password' => 'nullable|regex:/^[A-Za-z0-9]{8}$/',
        ]);

        $data['foro_privado'] = (bool) ($data['foro_privado'] ?? false);

        if (!$data['foro_privado']) {
            $data['foro_password'] = null;
        } elseif (!empty($data['foro_password'])) {
            $data['foro_password'] = Crypt::encryptString($data['foro_password']);
        } else {
            unset($data['foro_password']);
        }

        $foro->update($data);
        $foro->miembros()->syncWithoutDetaching([$foro->foro_creador_id]);

        return response()->json($foro->load(['usuario', 'categoria']), 200);
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

        return response()->json(Publicacion::create($data)->load(['usuario', 'foro']), 201);
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
        $publicacion->update($data);

        return response()->json($publicacion->load(['usuario', 'foro']), 200);
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

        return response()->json(Comentario::create($data)->load(['usuario', 'publicacion']), 201);
    }

    public function actualizarComentario(Request $request, $id)
    {
        $comentario = Comentario::findOrFail($id);
        $data = $request->validate([
            'comentario_usuario_id' => 'required|exists:usuario,usuario_id',
            'comentario_publicacion_id' => 'required|exists:publicacion,publicacion_id',
            'comentario_contenido' => 'required|string|max:2000',
        ]);

        $comentario->update($data);

        return response()->json($comentario->load(['usuario', 'publicacion']), 200);
    }
}
