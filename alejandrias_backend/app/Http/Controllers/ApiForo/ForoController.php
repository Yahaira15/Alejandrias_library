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
use App\Services\IA\Moderation\ContentModerationService;
use App\Services\Notifications\LeaderNotificationService;
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
            ->get();
        return response()->json($foros, 200);
    }

    public function misForos()
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        if ($usuario->usuario_rol === 'admin') {
            $foros = Foro::with(['usuario', 'categoria'])->get();
        } elseif ($usuario->usuario_rol === 'lider') {
            $foros = Foro::where('foro_creador_id', $usuario->usuario_id)
                ->with(['usuario', 'categoria'])
                ->get();
        } else {
            $foros = Foro::whereHas('miembros', function ($query) use ($usuario) {
                $query->where('usuario.usuario_id', $usuario->usuario_id);
            })
                ->with(['usuario', 'categoria'])
                ->get();
        }

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
            ->get();

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
        ]);

        try {
            $usuario = Auth::guard('sanctum')->user();

            if (!$usuario) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            if (!in_array($usuario->usuario_rol, ['lider', 'admin'], true)) {
                return response()->json(['error' => 'No autorizado'], 403);
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

            $foro = new Foro();
            $foro->foro_titulo = $validated['foro_titulo'];
            $foro->foro_descripcion = $validated['foro_descripcion'];
            $foro->foro_categoria_id = $validated['foro_categoria_id'];
            $foro->foro_creador_id = $usuario->usuario_id;
            $foro->foro_privado = $request->boolean('foro_privado');
            $foro->foro_password = $foro->foro_privado
                ? Crypt::encryptString($validated['foro_password'])
                : null;
            $moderationService->applyToModel($foro, $moderation, 'foro');

            $foro->save();
            $moderationService->record('foro', $foro->foro_id, $usuario->usuario_id, $moderation, $moderationPayload);
            $foro->miembros()->syncWithoutDetaching([$usuario->usuario_id]);

            if (($moderation['estado'] ?? 'revision') === 'permitido') {
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
        $foro = Foro::with(['usuario', 'categoria'])->find($id);

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

        return response()->json($foro, 200);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'foro_titulo' => 'sometimes|required|string|max:255',
            'foro_descripcion' => 'sometimes|required|string',
            'foro_categoria_id' => 'sometimes|required|exists:categoria,categoria_id',
            'foro_privado' => 'sometimes|boolean',
            'foro_password' => 'nullable|regex:/^[A-Za-z0-9]{8}$/',
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

            $foro->fill([
                'foro_titulo' => $validated['foro_titulo'] ?? $foro->foro_titulo,
                'foro_descripcion' => $validated['foro_descripcion'] ?? $foro->foro_descripcion,
                'foro_categoria_id' => $validated['foro_categoria_id'] ?? $foro->foro_categoria_id,
            ]);

            if (array_key_exists('foro_privado', $validated)) {
                $foro->foro_privado = $request->boolean('foro_privado');
            }

            if (!$foro->foro_privado) {
                $foro->foro_password = null;
            } elseif ($request->filled('foro_password')) {
                $foro->foro_password = Crypt::encryptString($validated['foro_password']);
            }

            $moderationService = app(ContentModerationService::class);
            $moderationPayload = [
                'tipo_contenido' => 'foro',
                'contenido' => [
                    'nombre' => $foro->foro_titulo,
                    'titulo' => $foro->foro_titulo,
                    'texto' => $foro->foro_descripcion,
                ],
                'contexto' => [
                    'categoria_id' => $foro->foro_categoria_id,
                    'usuario_rol' => $usuario->usuario_rol,
                    'operacion' => 'actualizacion',
                ],
            ];
            $moderation = $moderationService->analyze($moderationPayload);
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
            return response()->json(['error' => 'Ya e encuentras registrado en este foro'], 409);
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

        // 🔔 Notificación para el usuario registrado
        Notificacion::create([
            'notificacion_usuario_id' => $usuario->usuario_id,
            'notificacion_tipo' => 'registro_foro',
            'notificacion_contenido' => 'Te registraste en el foro "' . $foro->foro_titulo . '"',
            'notificacion_leida' => false,
            'notificacion_fecha' => now(),
            'notificacion_url' => '/foro/' . $foro->foro_id,
            'notificacion_referencia_id' => $foro->foro_id
        ]);

        // 🔔 Notificación para el líder del foro
        if ($foro->foro_creador_id != $usuario->usuario_id) {

            Notificacion::create([
                'notificacion_usuario_id' => $foro->foro_creador_id,
                'notificacion_tipo' => 'nuevo_miembro',
                'notificacion_contenido' => $usuario->usuario_apodo . ' se unió a tu foro "' . $foro->foro_titulo . '"',
                'notificacion_leida' => false,
                'notificacion_fecha' => now(),
                'notificacion_url' => '/foro/' . $foro->foro_id,
                'notificacion_referencia_id' => $foro->foro_id
            ]);
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
}
