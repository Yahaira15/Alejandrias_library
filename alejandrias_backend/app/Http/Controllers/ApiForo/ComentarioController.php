<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesAdjuntos;
use App\Models\Comentario;
use App\Models\Publicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notificacion;
use App\Services\IA\Moderation\ContentModerationService;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\XpService;
use App\Services\Notifications\LeaderNotificationService;
use App\Services\Sanctions\SanctionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComentarioController extends Controller
{
    use HandlesAdjuntos;

    public function index($publicacionId)
    {
        $publicacion = Publicacion::find($publicacionId);

        if (!$publicacion) {
            return response()->json(['message' => 'Publicacion no encontrada'], 404);
        }

        if ($publicacion->foro?->foro_privado) {
            $usuario = Auth::guard('sanctum')->user();
            $registrado = $usuario && (
                $publicacion->foro->foro_creador_id == $usuario->usuario_id
                || $usuario->usuario_rol === 'admin'
                || $publicacion->foro->miembros()
                    ->where('usuario.usuario_id', $usuario->usuario_id)
                    ->exists()
            );

            if (!$registrado) {
                return response()->json(['error' => 'Debes registrarte en el foro antes de ver sus comentarios'], 403);
            }
        }

        $comentarios = Comentario::where('comentario_publicacion_id', $publicacionId)
            ->when(Schema::hasColumn('comentario', 'comentario_padre_id'), function ($query) {
                $query->whereNull('comentario_padre_id');
            })
            ->when(Schema::hasColumn('comentario', 'estado_moderacion'), function ($query) {
                $usuario = Auth::guard('sanctum')->user();
                if (!$usuario || $usuario->usuario_rol !== 'admin') {
                    $query->where('estado_moderacion', 'visible');
                }
            })
            ->with('usuario')
            ->orderBy('comentario_id', 'asc')
            ->get();

        $comentarios = $this->adjuntarLikes($comentarios, Auth::guard('sanctum')->user());

        return response()->json($comentarios, 200);
    }

    public function store(Request $request, $publicacionId)
    {
        $publicacion = Publicacion::find($publicacionId);

        if (!$publicacion) {
            return response()->json(['message' => 'Publicacion no encontrada'], 404);
        }

        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        if (app(SanctionService::class)->hasActiveBlock($usuario, 'comentar')) {
            return response()->json(['error' => 'Tu cuenta tiene una restriccion activa para comentar'], 403);
        }

        $foro = $publicacion->foro;
        $registrado = $foro && (
            $foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists()
        );

        if (!$registrado) {
            return response()->json(['error' => 'Debes registrarte en el foro antes de comentar'], 403);
        }

        $data = $request->validate([
            'comentario_contenido' => 'required|string|max:2000',
        ]);
        $this->validarAdjuntos($request);

        $data['comentario_publicacion_id'] = $publicacionId;
        $data['comentario_usuario_id'] = $usuario->usuario_id;
        $data['comentario_fecha_creacion'] = now();
        if (Schema::hasColumn('comentario', 'comentario_adjuntos')) {
            $data['comentario_adjuntos'] = $this->guardarAdjuntos($request, 'adjuntos/comentarios');
        }

        $moderationService = app(ContentModerationService::class);
        $moderationPayload = [
            'tipo_contenido' => 'comentario',
            'contenido' => [
                'texto' => $data['comentario_contenido'],
            ],
            'contexto' => [
                'publicacion_id' => $publicacion->publicacion_id,
                'foro_id' => $foro?->foro_id,
                'publicacion' => $publicacion->publicacion_titulo,
                'foro' => $foro?->foro_titulo,
                'categoria' => $foro?->categoria?->categoria_nombre,
                'usuario_rol' => $usuario->usuario_rol,
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        $comentario = Comentario::create($data);
        $moderationService->applyToModel($comentario, $moderation, 'comentario');
        $comentario->save();
        $moderationPersisted = $moderationService->record(
            'comentario',
            $comentario->comentario_id,
            $usuario->usuario_id,
            $moderation,
            $moderationPayload
        );

        // 🔔 Notificar dueño de publicación

            $duenoPublicacion = $publicacion->usuario;
            if (($moderation['estado'] ?? 'revision') === 'permitido') {
                app(XpService::class)->track($usuario, 'comentario_creado');

                if (mb_strlen(trim($comentario->comentario_contenido)) >= 20) {
                    app(XpService::class)->award($usuario, 'comentario_creado', $comentario, ['skip_mission_progress' => true]);
                }

                if ($duenoPublicacion && $duenoPublicacion->usuario_id != $usuario->usuario_id) {
                    app(XpService::class)->award($duenoPublicacion, 'comentario_recibido', $comentario, [
                        'publicacion_id' => $publicacion->publicacion_id,
                    ]);
                }
            }

            // ❌ Evitar auto notificación
            if (($moderation['estado'] ?? 'revision') === 'permitido' &&
                $duenoPublicacion &&
                $duenoPublicacion->usuario_id != $usuario->usuario_id) {

                Notificacion::create([

                    'notificacion_usuario_id' =>
                        $duenoPublicacion->usuario_id,

                    'notificacion_tipo' =>
                        'nuevo_comentario',

                    'notificacion_contenido' =>
                        $usuario->usuario_apodo .
                        ' comentó tu publicación "' .
                        $publicacion->publicacion_titulo . '"',

                    'notificacion_leida' => false,

                    'notificacion_fecha' => now(),

                    'notificacion_url' =>
                        '/foro/' . $foro->foro_id .
                        '/publicacion/' . $publicacion->publicacion_id,

                    'notificacion_referencia_id' =>
                        $publicacion->publicacion_id
                ]);
            }

            if (($moderation['estado'] ?? 'revision') === 'permitido' && $foro) {
                app(LeaderNotificationService::class)->notifyRelevantLeaders(
                    $foro,
                    'lider_comentario_relevante',
                    'Nuevo comentario relevante en "' . $foro->foro_titulo . '"',
                    '/foro/' . $foro->foro_id . '/publicacion/' . $publicacion->publicacion_id,
                    $publicacion->publicacion_id,
                    $usuario->usuario_id
                );
            }

        $response = $comentario->load('usuario')->toArray();
        $response['_moderacion'] = $moderation;
        $response['_moderacion_registrada'] = $moderationPersisted;

        return response()->json($response, 201);
    }

    public function respuestas($comentarioId)
    {
        $comentarioPadre = Comentario::with('publicacion.foro')->find($comentarioId);

        if (!$comentarioPadre) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        if (!Schema::hasColumn('comentario', 'comentario_padre_id')) {
            return response()->json([], 200);
        }

        $respuestas = Comentario::where('comentario_padre_id', $comentarioPadre->comentario_id)
            ->when(Schema::hasColumn('comentario', 'estado_moderacion'), function ($query) {
                $usuario = Auth::guard('sanctum')->user();
                if (!$usuario || $usuario->usuario_rol !== 'admin') {
                    $query->where('estado_moderacion', 'visible');
                }
            })
            ->with('usuario')
            ->orderBy('comentario_id', 'asc')
            ->get();

        $respuestas = $this->adjuntarLikes($respuestas, Auth::guard('sanctum')->user());

        return response()->json($respuestas, 200);
    }

    public function responder(Request $request, $comentarioId)
    {
        if (!Schema::hasColumn('comentario', 'comentario_padre_id')) {
            return response()->json(['error' => 'La base de datos requiere migracion para respuestas de comentarios'], 500);
        }

        $comentarioPadre = Comentario::with(['publicacion.foro.categoria'])->find($comentarioId);

        if (!$comentarioPadre || !$comentarioPadre->publicacion) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        $publicacion = $comentarioPadre->publicacion;
        $foro = $publicacion->foro;
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        if (app(SanctionService::class)->hasActiveBlock($usuario, 'comentar')) {
            return response()->json(['error' => 'Tu cuenta tiene una restriccion activa para comentar'], 403);
        }

        $registrado = $foro && (
            $foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists()
        );

        if (!$registrado) {
            return response()->json(['error' => 'Debes registrarte en el foro antes de responder'], 403);
        }

        $data = $request->validate([
            'comentario_contenido' => 'required|string|max:2000',
        ]);

        $moderationService = app(ContentModerationService::class);
        $moderationPayload = [
            'tipo_contenido' => 'comentario_respuesta',
            'contenido' => [
                'texto' => $data['comentario_contenido'],
            ],
            'contexto' => [
                'comentario_padre_id' => $comentarioPadre->comentario_id,
                'publicacion_id' => $publicacion->publicacion_id,
                'foro_id' => $foro?->foro_id,
                'publicacion' => $publicacion->publicacion_titulo,
                'foro' => $foro?->foro_titulo,
                'categoria' => $foro?->categoria?->categoria_nombre,
                'usuario_rol' => $usuario->usuario_rol,
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);

        $respuesta = Comentario::create([
            'comentario_publicacion_id' => $publicacion->publicacion_id,
            'comentario_usuario_id' => $usuario->usuario_id,
            'comentario_padre_id' => $comentarioPadre->comentario_id,
            'comentario_contenido' => $data['comentario_contenido'],
        ]);
        $moderationService->applyToModel($respuesta, $moderation, 'comentario');
        $respuesta->save();

        $moderationPersisted = $moderationService->record(
            'comentario',
            $respuesta->comentario_id,
            $usuario->usuario_id,
            $moderation,
            $moderationPayload
        );

        $response = $respuesta->load('usuario')->toArray();
        $response['_moderacion'] = $moderation;
        $response['_moderacion_registrada'] = $moderationPersisted;

        return response()->json($response, 201);
    }

    public function show($id)
    {
        $comentario = Comentario::with(['usuario', 'publicacion'])->find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        if (
            Schema::hasColumn('comentario', 'estado_moderacion')
            && $comentario->estado_moderacion !== 'visible'
        ) {
            $usuario = Auth::guard('sanctum')->user();
            if (!$usuario || ($usuario->usuario_rol !== 'admin' && $comentario->comentario_usuario_id !== $usuario->usuario_id)) {
                return response()->json(['message' => 'Comentario no encontrado'], 404);
            }
        }

        return response()->json($this->adjuntarLike($comentario, Auth::guard('sanctum')->user()), 200);
    }

    public function toggleLike($id)
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $comentario = Comentario::with('publicacion.foro')->find($id);

        if (!$comentario) {
            return response()->json(['error' => 'Comentario no encontrado'], 404);
        }

        $foro = $comentario->publicacion?->foro;
        if ($foro?->foro_privado && !$this->usuarioRegistradoEnForo($foro, $usuario)) {
            return response()->json(['error' => 'Debes registrarte en el foro antes de dar me gusta'], 403);
        }

        if (!Schema::hasTable('comentario_like')) {
            return response()->json(['error' => 'La base de datos requiere migracion para likes'], 500);
        }

        $like = DB::table('comentario_like')
            ->where('comentario_id', $comentario->comentario_id)
            ->where('usuario_id', $usuario->usuario_id)
            ->first();

        if ($like) {
            DB::table('comentario_like')
                ->where('comentario_id', $comentario->comentario_id)
                ->where('usuario_id', $usuario->usuario_id)
                ->delete();
            $liked = false;
        } else {
            DB::table('comentario_like')->insert([
                'comentario_id' => $comentario->comentario_id,
                'usuario_id' => $usuario->usuario_id,
                'fecha_creacion' => now(),
            ]);
            $liked = true;
        }

        $likes = DB::table('comentario_like')
            ->where('comentario_id', $comentario->comentario_id)
            ->count();

        if (Schema::hasColumn('comentario', 'comentario_likes')) {
            $comentario->comentario_likes = $likes;
            $comentario->save();
        }

        return response()->json([
            'liked' => $liked,
            'comentario_likes' => $likes,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario || ($comentario->comentario_usuario_id != $usuario->usuario_id && $usuario->usuario_rol !== 'admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'comentario_contenido' => 'required|string|max:2000'
        ]);

        $moderationService = app(ContentModerationService::class);
        $moderationPayload = [
            'tipo_contenido' => 'comentario',
            'contenido' => [
                'texto' => $data['comentario_contenido'],
            ],
            'contexto' => [
                'publicacion_id' => $comentario->comentario_publicacion_id,
                'foro_id' => $comentario->publicacion?->publicacion_foro_id,
                'publicacion' => $comentario->publicacion?->publicacion_titulo,
                'foro' => $comentario->publicacion?->foro?->foro_titulo,
                'categoria' => $comentario->publicacion?->foro?->categoria?->categoria_nombre,
                'usuario_rol' => $usuario->usuario_rol,
                'operacion' => 'actualizacion',
            ],
        ];
        $moderation = $moderationService->analyze($moderationPayload);
        $moderationService->applyToModel($comentario, $moderation, 'comentario');

        $comentario->update($data);
        $comentario->save();
        $moderationPersisted = $moderationService->record(
            'comentario',
            $comentario->comentario_id,
            $usuario->usuario_id,
            $moderation,
            $moderationPayload
        );

        $response = $comentario->load('usuario')->toArray();
        $response['_moderacion'] = $moderation;
        $response['_moderacion_registrada'] = $moderationPersisted;

        return response()->json($response, 200);
    }

    public function destroy($id)
    {
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json(['message' => 'Comentario no encontrado'], 404);
        }

        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario || ($comentario->comentario_usuario_id != $usuario->usuario_id && $usuario->usuario_rol !== 'admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $comentario->delete();

        return response()->json(['message' => 'Comentario eliminado'], 200);
    }

    private function adjuntarLikes($comentarios, $usuario)
    {
        if (!Schema::hasTable('comentario_like')) {
            return $comentarios->map(function ($comentario) {
                $comentario->comentario_likes = (int) ($comentario->comentario_likes ?? 0);
                $comentario->liked_by_me = false;
                return $comentario;
            });
        }

        $ids = $comentarios->pluck('comentario_id')->all();
        $likesPorComentario = DB::table('comentario_like')
            ->select('comentario_id', DB::raw('COUNT(*) as total'))
            ->whereIn('comentario_id', $ids)
            ->groupBy('comentario_id')
            ->pluck('total', 'comentario_id');

        $likesUsuario = collect();
        if ($usuario) {
            $likesUsuario = DB::table('comentario_like')
                ->where('usuario_id', $usuario->usuario_id)
                ->whereIn('comentario_id', $ids)
                ->pluck('comentario_id')
                ->flip();
        }

        return $comentarios->map(function ($comentario) use ($likesPorComentario, $likesUsuario) {
            $comentario->comentario_likes = (int) ($likesPorComentario[$comentario->comentario_id] ?? $comentario->comentario_likes ?? 0);
            $comentario->liked_by_me = $likesUsuario->has($comentario->comentario_id);
            return $comentario;
        });
    }

    private function adjuntarLike(Comentario $comentario, $usuario): Comentario
    {
        if (!Schema::hasTable('comentario_like')) {
            $comentario->comentario_likes = (int) ($comentario->comentario_likes ?? 0);
            $comentario->liked_by_me = false;
            return $comentario;
        }

        $comentario->comentario_likes = DB::table('comentario_like')
            ->where('comentario_id', $comentario->comentario_id)
            ->count();
        $comentario->liked_by_me = $usuario
            ? DB::table('comentario_like')
                ->where('comentario_id', $comentario->comentario_id)
                ->where('usuario_id', $usuario->usuario_id)
                ->exists()
            : false;

        return $comentario;
    }

    private function usuarioRegistradoEnForo($foro, $usuario): bool
    {
        return $foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists();
    }
}
