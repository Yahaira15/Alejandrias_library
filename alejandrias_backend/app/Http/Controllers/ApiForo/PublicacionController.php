<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesAdjuntos;
use Illuminate\Http\Request;
use App\Models\Foro;
use App\Models\Publicacion;
use App\Models\Notificacion;
use App\Services\IA\Moderation\ContentModerationService;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\XpService;
use App\Services\Notifications\LeaderNotificationService;
use App\Services\Sanctions\SanctionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicacionController extends Controller
{
    use HandlesAdjuntos;

    public function index($foroId)
    {
        $foro = Foro::find($foroId);

        if (!$foro) {
            return response()->json(['error' => 'Foro no encontrado'], 404);
        }

        if ($foro->foro_privado) {
            $usuario = auth('sanctum')->user();
            $registrado = $usuario && (
                $foro->foro_creador_id == $usuario->usuario_id
                || $usuario->usuario_rol === 'admin'
                || $foro->miembros()
                    ->where('usuario.usuario_id', $usuario->usuario_id)
                    ->exists()
            );

            if (!$registrado) {
                return response()->json(['error' => 'Debes registrarte en el foro antes de ver sus publicaciones'], 403);
            }
        }

        $publicaciones = Publicacion::where('publicacion_foro_id', $foroId)
            ->when(Schema::hasColumn('publicacion', 'estado_moderacion'), function ($query) {
                $usuario = auth('sanctum')->user();
                if (!$usuario || $usuario->usuario_rol !== 'admin') {
                    $query->where('estado_moderacion', 'visible');
                }
            })
            ->with('usuario')
            ->withCount('comentarios')
            ->orderBy('publicacion_destacada', 'desc')
            ->orderBy('publicacion_fecha_creacion', 'desc')
            ->get();

        $publicaciones = $this->adjuntarLikes($publicaciones, auth('sanctum')->user());

        return response()->json($publicaciones, 200);
    }

    public function store(Request $request, $foroId)
    {
        try {
            $usuario = auth('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if (app(SanctionService::class)->hasActiveBlock($usuario, 'publicar')) {
                return response()->json(['error' => 'Tu cuenta tiene una restriccion activa para publicar'], 403);
            }

            $foro = Foro::find($foroId);
            $registrado = $foro && (
                $foro->foro_creador_id == $usuario->usuario_id
                || $usuario->usuario_rol === 'admin'
                || $foro->miembros()
                    ->where('usuario.usuario_id', $usuario->usuario_id)
                    ->exists()
            );

            if (!$registrado) {
                return response()->json(['error' => 'Debes registrarte en el foro antes de publicar'], 403);
            }

            $request->validate([
                'publicacion_titulo' => 'required|string|max:255',
                'publicacion_contenido' => 'required|string',
                'publicacion_destacada' => 'nullable|boolean'
            ]);
            $this->validarAdjuntos($request);

            $moderationService = app(ContentModerationService::class);
            $moderationPayload = [
                'tipo_contenido' => 'publicacion',
                'contenido' => [
                    'titulo' => $request->publicacion_titulo,
                    'texto' => $request->publicacion_contenido,
                ],
                'contexto' => [
                    'foro_id' => $foro?->foro_id,
                    'foro' => $foro?->foro_titulo,
                    'categoria' => $foro?->categoria?->categoria_nombre,
                    'usuario_rol' => $usuario->usuario_rol,
                ],
            ];
            $moderation = $moderationService->analyze($moderationPayload);

            $publicacion = new Publicacion();
            $publicacion->publicacion_foro_id = $foroId;
            $publicacion->publicacion_usuario_id = $usuario->usuario_id;
            $publicacion->publicacion_titulo = $request->publicacion_titulo;
            $publicacion->publicacion_contenido = $request->publicacion_contenido;
            if (Schema::hasColumn('publicacion', 'publicacion_adjuntos')) {
                $publicacion->publicacion_adjuntos = $this->guardarAdjuntos($request, 'adjuntos/publicaciones');
            }
            $publicacion->publicacion_destacada = $request->boolean('publicacion_destacada');
            $publicacion->publicacion_fecha_creacion = now();
            $publicacion->publicacion_fecha_actualizacion = now();
            $moderationService->applyToModel($publicacion, $moderation, 'publicacion');

            $publicacion->save();
            $moderationPersisted = $moderationService->record(
                'publicacion',
                $publicacion->publicacion_id,
                $usuario->usuario_id,
                $moderation,
                $moderationPayload
            );

            // 🔔 Obtener foro
            $foro = Foro::with('miembros')->find($foroId);

            // 🔔 Notificar miembros registrados
            if (($moderation['estado'] ?? 'revision') === 'permitido') {
            app(XpService::class)->track($usuario, 'crear_publicacion');
            app(XpService::class)->award($usuario, 'crear_publicacion', $publicacion, ['skip_mission_progress' => true]);
            if (mb_strlen(strip_tags($publicacion->publicacion_contenido)) >= 700) {
                app(XpService::class)->award($usuario, 'publicacion_larga', $publicacion);
            }
            if ($publicacion->publicacion_destacada) {
                app(XpService::class)->award($usuario, 'publicacion_destacada', $publicacion);
            }
            foreach ($foro->miembros as $miembro) {

                // ❌ No notificarse a sí mismo
                if ($miembro->usuario_id == $usuario->usuario_id) {
                    continue;
                }

                Notificacion::create([

                    'notificacion_usuario_id' => $miembro->usuario_id,

                    'notificacion_tipo' => 'nueva_publicacion',

                    'notificacion_contenido' =>
                        $usuario->usuario_apodo .
                        ' publicó en el foro "' .
                        $foro->foro_titulo . '"',

                    'notificacion_leida' => false,

                    'notificacion_fecha' => now(),

                    'notificacion_url' =>
                        '/publicaciones/' . $publicacion->publicacion_id,

                    'notificacion_referencia_id' =>
                        $publicacion->publicacion_id
                ]);
            }
            app(LeaderNotificationService::class)->notifyRelevantLeaders(
                $foro,
                'lider_publicacion_relevante',
                'Nueva publicacion relevante en "' . $foro->foro_titulo . '"',
                '/publicaciones/' . $publicacion->publicacion_id,
                $publicacion->publicacion_id,
                $usuario->usuario_id
            );
            }

            $response = $publicacion->load('usuario')->toArray();
            $response['_moderacion'] = $moderation;
            $response['_moderacion_registrada'] = $moderationPersisted;

            return response()->json($response, 201);
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

        if (
            Schema::hasColumn('publicacion', 'estado_moderacion')
            && $publicacion->estado_moderacion !== 'visible'
        ) {
            $usuario = auth('sanctum')->user();
            if (!$usuario || ($usuario->usuario_rol !== 'admin' && $publicacion->publicacion_usuario_id !== $usuario->usuario_id)) {
                return response()->json(['error' => 'Publicacion no disponible'], 404);
            }
        }

        if ($publicacion->foro?->foro_privado) {
            $usuario = auth('sanctum')->user();
            $registrado = $usuario && (
                $publicacion->foro->foro_creador_id == $usuario->usuario_id
                || $usuario->usuario_rol === 'admin'
                || $publicacion->foro->miembros()
                    ->where('usuario.usuario_id', $usuario->usuario_id)
                    ->exists()
            );

            if (!$registrado) {
                return response()->json(['error' => 'Debes registrarte en el foro antes de ver esta publicación'], 403);
            }
        }

        return response()->json($this->adjuntarLike($publicacion, auth('sanctum')->user()), 200);
    }

    public function toggleLike($id)
    {
        $usuario = auth('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $publicacion = Publicacion::with('foro')->find($id);

        if (!$publicacion) {
            return response()->json(['error' => 'Publicacion no encontrada'], 404);
        }

        if ($publicacion->foro?->foro_privado && !$this->usuarioRegistradoEnForo($publicacion->foro, $usuario)) {
            return response()->json(['error' => 'Debes registrarte en el foro antes de dar me gusta'], 403);
        }

        if (!Schema::hasTable('publicacion_like')) {
            return response()->json(['error' => 'La base de datos requiere migracion para likes'], 500);
        }

        $like = DB::table('publicacion_like')
            ->where('publicacion_id', $publicacion->publicacion_id)
            ->where('usuario_id', $usuario->usuario_id)
            ->first();

        if ($like) {
            DB::table('publicacion_like')
                ->where('publicacion_id', $publicacion->publicacion_id)
                ->where('usuario_id', $usuario->usuario_id)
                ->delete();
            $liked = false;
        } else {
            DB::table('publicacion_like')->insert([
                'publicacion_id' => $publicacion->publicacion_id,
                'usuario_id' => $usuario->usuario_id,
                'fecha_creacion' => now(),
            ]);
            $liked = true;
        }

        $likes = DB::table('publicacion_like')
            ->where('publicacion_id', $publicacion->publicacion_id)
            ->count();

        if (Schema::hasColumn('publicacion', 'publicacion_likes')) {
            $publicacion->publicacion_likes = $likes;
            $publicacion->save();
        }

        return response()->json([
            'liked' => $liked,
            'publicacion_likes' => $likes,
        ], 200);
    }

    public function verificarRegistro($id)
    {
        $usuario = auth('sanctum')->user();

        if (!$usuario) {
            return response()->json(['registrado' => false], 401);
        }

        $publicacion = Publicacion::with('foro')->find($id);

        if (!$publicacion || !$publicacion->foro) {
            return response()->json(['registrado' => false], 404);
        }

        $registrado = $publicacion->foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $publicacion->foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists();

        return response()->json([
            'registrado' => $registrado,
            'foro_id' => $publicacion->publicacion_foro_id,
        ], 200);
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

            if ($publicacion->publicacion_usuario_id != $usuario->usuario_id && $usuario->usuario_rol !== 'admin') {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $request->validate([
                'publicacion_titulo' => 'required|string|max:255',
                'publicacion_contenido' => 'required|string',
                'publicacion_destacada' => 'nullable|boolean'
            ]);

            $moderationService = app(ContentModerationService::class);
            $moderationPayload = [
                'tipo_contenido' => 'publicacion',
                'contenido' => [
                    'titulo' => $request->publicacion_titulo,
                    'texto' => $request->publicacion_contenido,
                ],
                'contexto' => [
                    'foro_id' => $publicacion->publicacion_foro_id,
                    'foro' => $publicacion->foro?->foro_titulo,
                    'categoria' => $publicacion->foro?->categoria?->categoria_nombre,
                    'usuario_rol' => $usuario->usuario_rol,
                    'operacion' => 'actualizacion',
                ],
            ];
            $moderation = $moderationService->analyze($moderationPayload);

            $publicacion->publicacion_titulo = $request->publicacion_titulo;
            $publicacion->publicacion_contenido = $request->publicacion_contenido;
            $publicacion->publicacion_destacada = $request->boolean('publicacion_destacada');
            $publicacion->publicacion_fecha_actualizacion = now();
            $moderationService->applyToModel($publicacion, $moderation, 'publicacion');
            $publicacion->save();
            if ($publicacion->publicacion_destacada) {
                app(XpService::class)->award($usuario, 'publicacion_destacada', $publicacion);
            }
            $moderationPersisted = $moderationService->record(
                'publicacion',
                $publicacion->publicacion_id,
                $usuario->usuario_id,
                $moderation,
                $moderationPayload
            );

            $response = $publicacion->toArray();
            $response['_moderacion'] = $moderation;
            $response['_moderacion_registrada'] = $moderationPersisted;

            return response()->json($response, 200);
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

            if ($publicacion->publicacion_usuario_id != $usuario->usuario_id && $usuario->usuario_rol !== 'admin') {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $publicacion->delete();
            app(GamificationService::class)->award($usuario, 'publicacion_eliminada', $publicacion);

            return response()->json(['mensaje' => 'Eliminada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    private function adjuntarLikes($publicaciones, $usuario)
    {
        if (!Schema::hasTable('publicacion_like')) {
            return $publicaciones->map(function ($publicacion) {
                $publicacion->publicacion_likes = (int) ($publicacion->publicacion_likes ?? 0);
                $publicacion->liked_by_me = false;
                return $publicacion;
            });
        }

        $ids = $publicaciones->pluck('publicacion_id')->all();
        $likesPorPublicacion = DB::table('publicacion_like')
            ->select('publicacion_id', DB::raw('COUNT(*) as total'))
            ->whereIn('publicacion_id', $ids)
            ->groupBy('publicacion_id')
            ->pluck('total', 'publicacion_id');

        $likesUsuario = collect();
        if ($usuario) {
            $likesUsuario = DB::table('publicacion_like')
                ->where('usuario_id', $usuario->usuario_id)
                ->whereIn('publicacion_id', $ids)
                ->pluck('publicacion_id')
                ->flip();
        }

        return $publicaciones->map(function ($publicacion) use ($likesPorPublicacion, $likesUsuario) {
            $publicacion->publicacion_likes = (int) ($likesPorPublicacion[$publicacion->publicacion_id] ?? $publicacion->publicacion_likes ?? 0);
            $publicacion->liked_by_me = $likesUsuario->has($publicacion->publicacion_id);
            return $publicacion;
        });
    }

    private function adjuntarLike(Publicacion $publicacion, $usuario): Publicacion
    {
        if (!Schema::hasTable('publicacion_like')) {
            $publicacion->publicacion_likes = (int) ($publicacion->publicacion_likes ?? 0);
            $publicacion->liked_by_me = false;
            return $publicacion;
        }

        $publicacion->publicacion_likes = DB::table('publicacion_like')
            ->where('publicacion_id', $publicacion->publicacion_id)
            ->count();
        $publicacion->liked_by_me = $usuario
            ? DB::table('publicacion_like')
                ->where('publicacion_id', $publicacion->publicacion_id)
                ->where('usuario_id', $usuario->usuario_id)
                ->exists()
            : false;

        return $publicacion;
    }

    private function usuarioRegistradoEnForo(Foro $foro, $usuario): bool
    {
        return $foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists();
    }
}
