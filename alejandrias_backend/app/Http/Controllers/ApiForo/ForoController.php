<?php

namespace App\Http\Controllers\ApiForo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Models\Foro;
use App\Models\Notificacion;
use App\Models\PuntuacionForo;
use App\Services\IA\Moderation\ContentModerationService;
use App\Services\Gamification\GamificationService;
use App\Services\Notifications\LeaderNotificationService;
use App\Services\Sanctions\SanctionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForoController extends Controller
{
    public function index()
    {
        $foros = Foro::when(Schema::hasColumn('foro', 'foro_visibilidad'), function ($query) {
                $usuario = Auth::guard('sanctum')->user();
                if (!$usuario || $usuario->usuario_rol !== 'admin') {
                    $query->where('foro_visibilidad', 'visible');
                }
            })
            ->with(['usuario', 'categoria'])
            ->withCount(['miembros', 'publicaciones', 'comentarios as comentarios_count_total'])
            ->get();
        $foros = $this->adjuntarPuntuaciones($foros, Auth::guard('sanctum')->user());
        return response()->json($foros, 200);
    }

    public function misForos()
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        if ($usuario->usuario_rol === 'admin') {
            $foros = Foro::with(['usuario', 'categoria'])
                ->withCount(['miembros', 'publicaciones', 'comentarios as comentarios_count_total'])
                ->get();
        } elseif ($usuario->usuario_rol === 'lider') {
            $foros = Foro::where('foro_creador_id', $usuario->usuario_id)
                ->with(['usuario', 'categoria'])
                ->withCount(['miembros', 'publicaciones', 'comentarios as comentarios_count_total'])
                ->get();
        } else {
            $foros = Foro::whereHas('miembros', function ($query) use ($usuario) {
                $query->where('usuario.usuario_id', $usuario->usuario_id);
            })
                ->with(['usuario', 'categoria'])
                ->withCount(['miembros', 'publicaciones', 'comentarios as comentarios_count_total'])
                ->get();
        }

        $foros = $this->adjuntarPuntuaciones($foros, $usuario);

        return response()->json($foros, 200);
    }

    public function forosPublicos()
    {
        $usuario = Auth::guard('sanctum')->user();

        $foros = Foro::where(function ($query) {
                $query->where('foro_privado', false)
                    ->orWhereNull('foro_privado');
            })
            ->when(Schema::hasColumn('foro', 'foro_visibilidad'), function ($query) {
                $query->where('foro_visibilidad', 'visible');
            })
            ->when($usuario, function ($query) use ($usuario) {
                $query->where('foro_creador_id', '!=', $usuario->usuario_id)
                    ->whereDoesntHave('miembros', function ($miembros) use ($usuario) {
                        $miembros->where('usuario.usuario_id', $usuario->usuario_id);
                    });
            })
            ->with(['usuario', 'categoria'])
            ->withCount(['miembros', 'publicaciones', 'comentarios as comentarios_count_total'])
            ->get();

        $foros = $this->adjuntarPuntuaciones($foros, $usuario);

        return response()->json($foros, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'foro_titulo' => 'required|string|max:255',
            'foro_descripcion' => 'required|string',
            'foro_categoria_id' => 'required|exists:categoria,categoria_id',
            'foro_privado' => 'nullable|boolean',
            'foro_password' => 'nullable|required_if:foro_privado,true|regex:/^[A-Za-z0-9]{8}$/',
            'foro_imagen' => 'nullable',
        ]);

        try {
            $usuario = Auth::guard('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if (!in_array($usuario->usuario_rol, ['lider', 'admin'], true)) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            if (app(SanctionService::class)->hasActiveBlock($usuario, 'publicar')) {
                return response()->json(['error' => 'Tu cuenta tiene una restriccion activa para publicar'], 403);
            }

            $moderationService = app(ContentModerationService::class);
            $moderationPayload = [
                'tipo_contenido' => 'foro',
                'contenido' => [
                    'nombre' => $validated['foro_titulo'],
                    'titulo' => $validated['foro_titulo'],
                    'texto' => $validated['foro_descripcion'],
                ],
                'contexto' => [
                    'categoria_id' => $validated['foro_categoria_id'],
                    'usuario_rol' => $usuario->usuario_rol,
                ],
            ];
            $moderation = $moderationService->analyze($moderationPayload);

            if (($moderation['estado'] ?? 'revision') === 'bloqueado') {
                $moderationService->record('foro', null, $usuario->usuario_id, $moderation, $moderationPayload);

                return response()->json([
                    'error' => $moderation['mensaje_usuario'] ?? 'El foro fue bloqueado por moderacion IA y no fue creado.',
                    '_moderacion' => $moderation,
                ], 422);
            }

            $foro = new Foro();
            $foro->foro_titulo = $validated['foro_titulo'];
            $foro->foro_descripcion = $validated['foro_descripcion'];
            $foro->foro_categoria_id = $validated['foro_categoria_id'];
            $foro->foro_creador_id = $usuario->usuario_id;
            $foro->foro_privado = $request->boolean('foro_privado');
            $foro->foro_password = $foro->foro_privado
                ? Crypt::encryptString($validated['foro_password'])
                : null;
            if (Schema::hasColumn('foro', 'foro_imagen')) {
                $foro->foro_imagen = $this->resolverImagenForo($request);
            }
            $moderationService->applyToModel($foro, $moderation, 'foro');

            $foro->save();
            $moderationService->record('foro', $foro->foro_id, $usuario->usuario_id, $moderation, $moderationPayload);
            $foro->miembros()->syncWithoutDetaching([$usuario->usuario_id]);

            if (($moderation['estado'] ?? 'revision') === 'permitido') {
                app(GamificationService::class)->award($usuario, 'crear_foro', $foro);
                app(LeaderNotificationService::class)->notifyRelevantLeaders(
                    $foro,
                    'lider_foro_relevante',
                    'Nuevo foro relevante en tu categoria: "' . $foro->foro_titulo . '"',
                    '/foro/' . $foro->foro_id,
                    $foro->foro_id,
                    $usuario->usuario_id
                );
            }

            $response = $foro->load(['usuario', 'categoria'])->toArray();
            $response['_moderacion'] = $moderation;

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
        $foro = Foro::with(['usuario', 'categoria'])
            ->withCount(['miembros', 'publicaciones', 'comentarios as comentarios_count_total'])
            ->find($id);

        if (!$foro) {
            return response()->json(['message' => 'Foro no encontrado'], 404);
        }

        if (
            Schema::hasColumn('foro', 'foro_visibilidad')
            && $foro->foro_visibilidad !== 'visible'
        ) {
            $usuario = Auth::guard('sanctum')->user();
            if (!$usuario || ($usuario->usuario_rol !== 'admin' && $foro->foro_creador_id !== $usuario->usuario_id)) {
                return response()->json(['message' => 'Foro no encontrado'], 404);
            }
        }

        if ($foro->foro_privado) {
            $usuario = Auth::guard('sanctum')->user();
            $registrado = $usuario && (
                $foro->foro_creador_id == $usuario->usuario_id
                || $usuario->usuario_rol === 'admin'
                || $foro->miembros()
                    ->where('usuario.usuario_id', $usuario->usuario_id)
                    ->exists()
            );

            if (!$registrado) {
                return response()->json(['error' => 'Debes registrarte en el foro antes de verlo'], 403);
            }
        }

        return response()->json($this->adjuntarPuntuacion($foro, Auth::guard('sanctum')->user()), 200);
    }

    public function puntuar(Request $request, $id)
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        if ($usuario->usuario_rol !== 'explorador') {
            return response()->json(['error' => 'Solo los exploradores pueden puntuar foros'], 403);
        }

        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['error' => 'Foro no encontrado'], 404);
        }

        if (!$this->usuarioRegistradoEnForo($foro, $usuario)) {
            return response()->json(['error' => 'Debes registrarte en el foro antes de puntuarlo'], 403);
        }

        $data = $request->validate([
            'puntuacion' => 'required|numeric|between:0,5',
        ]);

        $puntuacion = (float) $data['puntuacion'];
        if (abs(($puntuacion * 2) - round($puntuacion * 2)) > 0.0001) {
            return response()->json(['error' => 'La puntuacion debe ir de 0 a 5 en pasos de 0.5'], 422);
        }

        $registro = PuntuacionForo::updateOrCreate(
            [
                'usuario_id' => $usuario->usuario_id,
                'foro_id' => $foro->foro_id,
            ],
            [
                'puntuacion' => $puntuacion,
                'fecha_creacion' => now(),
                'fecha_actualizacion' => now(),
            ]
        );

        $foro = $this->adjuntarPuntuacion($foro->fresh(), $usuario);

        return response()->json([
            'puntuacion' => $registro,
            'foro_puntuacion_promedio' => $foro->foro_puntuacion_promedio,
            'foro_puntuacion_promedio_redondeada' => $foro->foro_puntuacion_promedio_redondeada,
            'foro_puntuaciones_count' => $foro->foro_puntuaciones_count,
            'mi_puntuacion' => $foro->mi_puntuacion,
        ], 200);
    }

    public function eliminarPuntuacion($id)
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['error' => 'Foro no encontrado'], 404);
        }

        PuntuacionForo::where('usuario_id', $usuario->usuario_id)
            ->where('foro_id', $foro->foro_id)
            ->delete();

        $foro = $this->adjuntarPuntuacion($foro->fresh(), $usuario);

        return response()->json([
            'mensaje' => 'Puntuacion eliminada',
            'foro_puntuacion_promedio' => $foro->foro_puntuacion_promedio,
            'foro_puntuacion_promedio_redondeada' => $foro->foro_puntuacion_promedio_redondeada,
            'foro_puntuaciones_count' => $foro->foro_puntuaciones_count,
            'mi_puntuacion' => $foro->mi_puntuacion,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'foro_titulo' => 'sometimes|required|string|max:255',
            'foro_descripcion' => 'sometimes|required|string',
            'foro_categoria_id' => 'sometimes|required|exists:categoria,categoria_id',
            'foro_privado' => 'sometimes|boolean',
            'foro_password' => 'nullable|regex:/^[A-Za-z0-9]{8}$/',
            'foro_imagen' => 'nullable',
        ]);

        try {
            $usuario = Auth::guard('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if (!in_array($usuario->usuario_rol, ['lider', 'admin'], true)) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $foro = Foro::find($id);

            if (!$foro) {
                return response()->json(['error' => 'Foro no encontrado'], 404);
            }

            if ($foro->foro_creador_id != $usuario->usuario_id && $usuario->usuario_rol !== 'admin') {
                return response()->json(['error' => 'No puedes editar este foro'], 403);
            }

            $seraPrivado = array_key_exists('foro_privado', $validated)
                ? $request->boolean('foro_privado')
                : (bool) $foro->foro_privado;

            if ($seraPrivado && !$foro->foro_password && !$request->filled('foro_password')) {
                return response()->json([
                    'error' => 'La contraseña es obligatoria para un foro privado'
                ], 422);
            }

            $moderationService = app(ContentModerationService::class);
            $tituloModerado = $validated['foro_titulo'] ?? $foro->foro_titulo;
            $descripcionModerada = $validated['foro_descripcion'] ?? $foro->foro_descripcion;
            $categoriaModerada = $validated['foro_categoria_id'] ?? $foro->foro_categoria_id;
            $moderationPayload = [
                'tipo_contenido' => 'foro',
                'contenido' => [
                    'nombre' => $tituloModerado,
                    'titulo' => $tituloModerado,
                    'texto' => $descripcionModerada,
                ],
                'contexto' => [
                    'categoria_id' => $categoriaModerada,
                    'usuario_rol' => $usuario->usuario_rol,
                    'operacion' => 'actualizacion',
                ],
            ];
            $moderation = $moderationService->analyze($moderationPayload);

            if (($moderation['estado'] ?? 'revision') === 'bloqueado') {
                $moderationService->applyToModel($foro, $moderation, 'foro');
                $foro->save();
                $moderationService->record('foro', $foro->foro_id, $usuario->usuario_id, $moderation, $moderationPayload);

                return response()->json([
                    'error' => $moderation['mensaje_usuario'] ?? 'La edicion fue bloqueada por moderacion IA. El foro fue ocultado.',
                    '_moderacion' => $moderation,
                ], 422);
            }

            $foro->fill([
                'foro_titulo' => $tituloModerado,
                'foro_descripcion' => $descripcionModerada,
                'foro_categoria_id' => $categoriaModerada,
            ]);

            if (array_key_exists('foro_privado', $validated)) {
                $foro->foro_privado = $request->boolean('foro_privado');
            }

            if (!$foro->foro_privado) {
                $foro->foro_password = null;
            } elseif ($request->filled('foro_password')) {
                $foro->foro_password = Crypt::encryptString($validated['foro_password']);
            }

            if (Schema::hasColumn('foro', 'foro_imagen')) {
                $foro->foro_imagen = $this->resolverImagenForo($request, $foro->foro_imagen);
            }

            $moderationService->applyToModel($foro, $moderation, 'foro');

            $foro->save();
            $moderationService->record('foro', $foro->foro_id, $usuario->usuario_id, $moderation, $moderationPayload);

            $response = $foro->load(['usuario', 'categoria'])->toArray();
            $response['mensaje'] = 'Foro actualizado';
            $response['_moderacion'] = $moderation;

            return response()->json($response);
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

            if (!in_array($usuario->usuario_rol, ['lider', 'admin'], true)) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $foro = Foro::find($id);

            if (!$foro) {
                return response()->json(['error' => 'Foro no encontrado'], 404);
            }

            if ($foro->foro_creador_id != $usuario->usuario_id && $usuario->usuario_rol !== 'admin') {
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

    public function registrar(Request $request, $id)
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['error' => 'Foro no encontrado'], 404);
        }

        if (
            $foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists()
        ) {
            return response()->json([
                'mensaje' => 'Ya te encuentras registrado en este foro',
                'foro' => $foro->load(['usuario', 'categoria']),
            ], 200);
        }

        if ($foro->foro_privado) {
            $data = $request->validate([
                'foro_password' => 'required|regex:/^[A-Za-z0-9]{8}$/',
            ]);

            if (!$this->passwordForoCoincide($foro, $data['foro_password'])) {
                return response()->json(['error' => 'Contraseña incorrecta'], 403);
            }
        }

        $foro->miembros()->syncWithoutDetaching([$usuario->usuario_id]);

        try {
            app(GamificationService::class)->award($usuario, 'registro_foro', $foro);

        // 🔔 Notificación para el usuario registrado
        Notificacion::create([
            'notificacion_usuario_id' => $usuario->usuario_id,
            'notificacion_tipo' => 'registro_foro',
            'notificacion_contenido' => 'Te registraste en el foro "' . $foro->foro_titulo . '"',
            'notificacion_leida' => false,
            'notificacion_fecha' => now(),
            'notificacion_url' => '/foros/' . $foro->foro_id,
            'notificacion_referencia_id' => $foro->foro_id
        ]);

        // 🔔 Notificación para el líder del foro
        if ($foro->foro_creador_id != $usuario->usuario_id) {
            if ($foro->usuario) {
                app(GamificationService::class)->award($foro->usuario, 'foro_activo', $foro, [
                    'nuevo_miembro_id' => $usuario->usuario_id,
                ]);
            }

            Notificacion::create([
                'notificacion_usuario_id' => $foro->foro_creador_id,
                'notificacion_tipo' => 'nuevo_miembro',
                'notificacion_contenido' => $usuario->usuario_apodo . ' se unió a tu foro "' . $foro->foro_titulo . '"',
                'notificacion_leida' => false,
                'notificacion_fecha' => now(),
                'notificacion_url' => '/foros/' . $foro->foro_id,
                'notificacion_referencia_id' => $foro->foro_id
            ]);
        }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'mensaje' => 'Registro al foro completado',
            'foro' => $foro->load(['usuario', 'categoria']),
        ], 200);
    }

    public function verificarRegistro($id)
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['registrado' => false], 401);
        }

        $foro = Foro::find($id);

        $registrado = $foro && (
            $foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists()
        );

        return response()->json(['registrado' => $registrado], 200);
    }

    public function eliminarRegistro($id)
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['error' => 'Foro no encontrado'], 404);
        }

        if ($foro->foro_creador_id == $usuario->usuario_id || $usuario->usuario_rol === 'admin') {
            return response()->json(['error' => 'No puedes eliminar el seguimiento de un foro que administras'], 403);
        }

        $foro->miembros()->detach($usuario->usuario_id);

        return response()->json([
            'mensaje' => 'Foro eliminado de tu lista de seguimiento'
        ], 200);
    }

    public function revelarPassword(Request $request, $id)
    {
        $data = $request->validate([
            'usuario_password' => 'required|string',
        ]);

        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $foro = Foro::find($id);

        if (!$foro) {
            return response()->json(['error' => 'Foro no encontrado'], 404);
        }

        if (($foro->foro_creador_id != $usuario->usuario_id || $usuario->usuario_rol !== 'lider') && $usuario->usuario_rol !== 'admin') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if (!Hash::check($data['usuario_password'], $usuario->usuario_password)) {
            return response()->json(['error' => 'Contraseña de usuario incorrecta'], 403);
        }

        $passwordForo = $this->desencriptarPasswordForo($foro);

        if (!$passwordForo) {
            return response()->json([
                'error' => 'No se puede mostrar la contraseña de este foro porque fue guardada con el formato anterior'
            ], 422);
        }

        return response()->json([
            'foro_password' => $passwordForo,
        ], 200);
    }

    public function buscarPrivadoPorPassword(Request $request)
    {
        $data = $request->validate([
            'foro_password' => 'required|regex:/^[A-Za-z0-9]{8}$/',
        ]);

        $foros = Foro::where('foro_privado', true)
            ->with(['usuario', 'categoria'])
            ->get();

        $foroEncontrado = $foros->first(function ($foro) use ($data) {
            return $this->passwordForoCoincide($foro, $data['foro_password']);
        });

        if (!$foroEncontrado) {
            return response()->json(['error' => 'No se encontró un foro privado con esa contraseña'], 404);
        }

        return response()->json($foroEncontrado, 200);
    }

    private function adjuntarPuntuaciones($foros, $usuario)
    {
        if (!Schema::hasTable('puntuacion_foro')) {
            return $foros->map(fn ($foro) => $this->puntuacionVacia($foro));
        }

        $ids = $foros->pluck('foro_id')->all();
        $estadisticas = DB::table('puntuacion_foro')
            ->select('foro_id', DB::raw('AVG(puntuacion) as promedio'), DB::raw('COUNT(*) as total'))
            ->whereIn('foro_id', $ids)
            ->groupBy('foro_id')
            ->get()
            ->keyBy('foro_id');

        $misPuntuaciones = collect();
        if ($usuario) {
            $misPuntuaciones = DB::table('puntuacion_foro')
                ->where('usuario_id', $usuario->usuario_id)
                ->whereIn('foro_id', $ids)
                ->pluck('puntuacion', 'foro_id');
        }

        return $foros->map(function ($foro) use ($estadisticas, $misPuntuaciones) {
            $stat = $estadisticas->get($foro->foro_id);
            $promedio = $stat ? (float) $stat->promedio : 0.0;
            $foro->foro_puntuacion_promedio = $promedio;
            $foro->foro_puntuacion_promedio_redondeada = $this->redondearMediaEstrella($promedio);
            $foro->foro_puntuaciones_count = $stat ? (int) $stat->total : 0;
            $foro->mi_puntuacion = $misPuntuaciones->has($foro->foro_id)
                ? (float) $misPuntuaciones[$foro->foro_id]
                : null;
            return $foro;
        });
    }

    private function adjuntarPuntuacion(Foro $foro, $usuario): Foro
    {
        if (!Schema::hasTable('puntuacion_foro')) {
            return $this->puntuacionVacia($foro);
        }

        $promedio = (float) DB::table('puntuacion_foro')
            ->where('foro_id', $foro->foro_id)
            ->avg('puntuacion');
        $foro->foro_puntuacion_promedio = $promedio;
        $foro->foro_puntuacion_promedio_redondeada = $this->redondearMediaEstrella($promedio);
        $foro->foro_puntuaciones_count = DB::table('puntuacion_foro')
            ->where('foro_id', $foro->foro_id)
            ->count();
        $foro->mi_puntuacion = $usuario
            ? DB::table('puntuacion_foro')
                ->where('foro_id', $foro->foro_id)
                ->where('usuario_id', $usuario->usuario_id)
                ->value('puntuacion')
            : null;

        if ($foro->mi_puntuacion !== null) {
            $foro->mi_puntuacion = (float) $foro->mi_puntuacion;
        }

        return $foro;
    }

    private function puntuacionVacia(Foro $foro): Foro
    {
        $foro->foro_puntuacion_promedio = 0;
        $foro->foro_puntuacion_promedio_redondeada = 0;
        $foro->foro_puntuaciones_count = 0;
        $foro->mi_puntuacion = null;

        return $foro;
    }

    private function redondearMediaEstrella(float $valor): float
    {
        return max(0, min(5, round($valor * 2) / 2));
    }

    private function usuarioRegistradoEnForo(Foro $foro, $usuario): bool
    {
        return $foro->foro_creador_id == $usuario->usuario_id
            || $usuario->usuario_rol === 'admin'
            || $foro->miembros()
                ->where('usuario.usuario_id', $usuario->usuario_id)
                ->exists();
    }

    private function passwordForoCoincide(Foro $foro, string $password): bool
    {
        if (!$foro->foro_password) {
            return false;
        }

        $passwordDesencriptada = $this->desencriptarPasswordForo($foro);

        if ($passwordDesencriptada !== null) {
            return hash_equals($passwordDesencriptada, $password);
        }

        return Hash::check($password, $foro->foro_password);
    }

    private function desencriptarPasswordForo(Foro $foro): ?string
    {
        if (!$foro->foro_password) {
            return null;
        }

        try {
            return Crypt::decryptString($foro->foro_password);
        } catch (DecryptException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
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
}
