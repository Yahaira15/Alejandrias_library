<?php

namespace App\Http\Controllers\ApiUsuario;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\XpService;
use App\Services\Sanctions\SanctionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    private array $interesesPermitidos = [
        'programacion',
        'matematicas',
        'historia',
        'literatura',
        'biologia',
        'politica',
        'idiomas',
        'bienestar',
    ];

    public function verificarApodo($apodo)
    {
        $existe = Usuario::where('usuario_apodo', $apodo)->exists();

        return response()->json([
            'disponible' => !$existe
        ])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'usuario_nombre' => 'required|string|max:255',
                'usuario_apellido' => 'required|string|max:255',
                'usuario_apodo' => 'required|string|max:100|unique:usuario,usuario_apodo',
                'usuario_email' => 'required|email|unique:usuario,usuario_email',
                'usuario_password' => [
                    'required',
                    'min:8',
                    'regex:/[A-Z]/',
                    'regex:/[0-9]/',
                    'regex:/[^A-Za-z0-9]/'
                ],
                'usuario_rol' => 'required|in:explorador,lider',
                'usuario_intereses' => 'required|array|min:1',
                'usuario_intereses.*' => 'string|in:' . implode(',', $this->interesesPermitidos),
                'usuario_acepto_terminos' => 'required|in:Acepto,No acepto',
            ], [
                'usuario_email.unique' => 'Este correo ya esta registrado',
                'usuario_apodo.unique' => 'Este apodo ya esta en uso',
                'usuario_password.regex' => 'La contrasena debe tener al menos una mayuscula, un numero y un caracter especial',
                'usuario_password.min' => 'La contrasena debe tener minimo 8 caracteres',
                'usuario_intereses.required' => 'Selecciona al menos un interes',
                'usuario_intereses.min' => 'Selecciona al menos un interes',
            ]);

            $usuario = Usuario::create([
                'usuario_nombre' => $request->usuario_nombre,
                'usuario_apellido' => $request->usuario_apellido,
                'usuario_apodo' => $request->usuario_apodo,
                'usuario_email' => $request->usuario_email,
                'usuario_password' => Hash::make($request->usuario_password),
                'usuario_rol' => $request->usuario_rol,
                'usuario_intereses' => $request->usuario_intereses,
                'usuario_acepto_terminos' => $request->usuario_acepto_terminos,
            ]);

            return response()->json([
                'mensaje' => 'Usuario registrado correctamente',
                'usuario' => $usuario,
            ], 201)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al registrar usuario',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'login' => 'required',
                'usuario_password' => 'required'
            ]);

            if (filter_var($request->login, FILTER_VALIDATE_EMAIL)) {
                $usuario = Usuario::where('usuario_email', $request->login)->first();
            } else {
                $usuario = Usuario::where('usuario_apodo', $request->login)->first();
            }

            if (!$usuario || !Hash::check($request->usuario_password, $usuario->usuario_password)) {
                return response()->json([
                    'mensaje' => 'Credenciales incorrectas'
                ], 401);
            }

            $sanctionService = app(SanctionService::class);
            $sanctionService->expireOldSanctions($usuario->usuario_id);
            $usuario->refresh();

            if ($usuario->usuario_bloqueado || $sanctionService->hasActiveBlock($usuario, 'login')) {
                return response()->json([
                    'mensaje' => 'Usuario suspendido o baneado'
                ], 403);
            }

            if ($usuario->usuario_acepto_terminos === null) {
                return response()->json([
                    'codigo' => 'terminos_pendientes',
                    'mensaje' => 'Debes aceptar los terminos y condiciones para continuar'
                ], 409);
            }

            if ($usuario->usuario_acepto_terminos === 'No acepto') {
                return response()->json([
                    'codigo' => 'terminos_rechazados',
                    'mensaje' => 'No puedes iniciar sesion sin aceptar los terminos y condiciones'
                ], 403);
            }

            $usuario->tokens()->delete();
            $token = $usuario->createToken('auth_token')->plainTextToken;
            app(XpService::class)->dailyAccess($usuario);

            return response()->json([
                'mensaje' => 'Login exitoso',
                'usuario' => $usuario,
                'token' => $token
            ], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error en el login',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function aceptarTerminos(Request $request)
    {
        try {
            $request->validate([
                'login' => 'required',
                'usuario_password' => 'required'
            ]);

            if (filter_var($request->login, FILTER_VALIDATE_EMAIL)) {
                $usuario = Usuario::where('usuario_email', $request->login)->first();
            } else {
                $usuario = Usuario::where('usuario_apodo', $request->login)->first();
            }

            if (!$usuario || !Hash::check($request->usuario_password, $usuario->usuario_password)) {
                return response()->json([
                    'mensaje' => 'Credenciales incorrectas'
                ], 401);
            }

            $sanctionService = app(SanctionService::class);
            $sanctionService->expireOldSanctions($usuario->usuario_id);
            $usuario->refresh();

            if ($usuario->usuario_bloqueado || $sanctionService->hasActiveBlock($usuario, 'login')) {
                return response()->json([
                    'mensaje' => 'Usuario suspendido o baneado'
                ], 403);
            }

            $usuario->usuario_acepto_terminos = 'Acepto';
            $usuario->save();

            $usuario->tokens()->delete();
            $token = $usuario->createToken('auth_token')->plainTextToken;
            app(XpService::class)->dailyAccess($usuario);

            return response()->json([
                'mensaje' => 'Terminos aceptados',
                'usuario' => $usuario,
                'token' => $token
            ], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al aceptar terminos',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function recuperarPassword(Request $request)
    {
        try {
            $request->validate([
                'usuario_email' => 'required|email'
            ], [
                'usuario_email.required' => 'Ingresa el correo de tu cuenta',
                'usuario_email.email' => 'Ingresa un correo valido',
            ]);

            $usuario = Usuario::where('usuario_email', $request->usuario_email)->first();

            if (!$usuario) {
                return response()->json([
                    'mensaje' => 'No encontramos una cuenta con ese correo'
                ], 404);
            }

            $passwordTemporal = $this->generarPasswordTemporal();
            $usuario->usuario_password = Hash::make($passwordTemporal);
            $usuario->tokens()->delete();
            $usuario->save();
            app(GamificationService::class)->award($usuario, 'perfil_completo', $usuario);

            return response()->json([
                'mensaje' => 'Contrasena temporal generada',
                'usuario_nombre' => $usuario->usuario_nombre,
                'usuario_apodo' => $usuario->usuario_apodo,
                'usuario_email' => $usuario->usuario_email,
                'password_temporal' => $passwordTemporal,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al recuperar la contrasena',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function perfil()
    {
        return response()->json(auth()->user(), 200);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'mensaje' => 'Sesion cerrada'
        ]);
    }

    public function update(Request $request)
    {
        try {
            $usuario = auth()->user();

            $request->validate([
                'usuario_nombre' => 'required|string|max:255',
                'usuario_apellido' => 'nullable|string|max:255',
                'usuario_apodo' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('usuario', 'usuario_apodo')->ignore($usuario->usuario_id, 'usuario_id'),
                ],
                'usuario_email' => [
                    'required',
                    'email',
                    Rule::unique('usuario', 'usuario_email')->ignore($usuario->usuario_id, 'usuario_id'),
                ],
                'usuario_bio' => 'nullable|string',
                'usuario_foto_perfil' => 'nullable|string',
                'usuario_intereses' => 'nullable|array',
                'usuario_intereses.*' => 'string|in:' . implode(',', $this->interesesPermitidos),
                'usuario_password' => 'nullable|min:8'
            ], [
                'usuario_apodo.unique' => 'Este apodo ya esta en uso',
                'usuario_email.unique' => 'Este correo ya esta registrado',
                'usuario_password.min' => 'La contrasena debe tener minimo 8 caracteres',
            ]);

            $usuario->usuario_nombre = trim($request->usuario_nombre);
            $usuario->usuario_apellido = $request->filled('usuario_apellido') ? trim($request->usuario_apellido) : null;
            $usuario->usuario_apodo = trim($request->usuario_apodo);
            $usuario->usuario_email = trim($request->usuario_email);
            $usuario->usuario_bio = $request->usuario_bio;
            $usuario->usuario_foto_perfil = $request->usuario_foto_perfil;

            if ($request->has('usuario_intereses')) {
                $usuario->usuario_intereses = $request->usuario_intereses;
            }

            if ($request->filled('usuario_password')) {
                $usuario->usuario_password = Hash::make($request->usuario_password);
            }

            $usuario->save();

            return response()->json([
                'mensaje' => 'Perfil actualizado',
                'usuario' => $usuario
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function updateIntereses(Request $request)
    {
        try {
            $request->validate([
                'usuario_intereses' => 'required|array|min:1',
                'usuario_intereses.*' => 'string|in:' . implode(',', $this->interesesPermitidos),
            ], [
                'usuario_intereses.required' => 'Selecciona al menos un interes',
                'usuario_intereses.min' => 'Selecciona al menos un interes',
            ]);

            $usuario = auth()->user();
            $usuario->usuario_intereses = $request->usuario_intereses;
            $usuario->save();
            app(GamificationService::class)->award($usuario, 'perfil_completo', $usuario);

            return response()->json([
                'mensaje' => 'Intereses actualizados',
                'usuario' => $usuario
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar intereses',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy()
    {
        try {
            $usuario = auth()->user();

            $usuario->tokens()->delete();
            $usuario->delete();

            return response()->json([
                'mensaje' => 'Cuenta eliminada'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    private function generarPasswordTemporal(): string
    {
        $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $minusculas = 'abcdefghijkmnopqrstuvwxyz';
        $numeros = '23456789';
        $especiales = '@#$%&*?';
        $base = $mayusculas . $minusculas . $numeros . $especiales;

        return str_shuffle(
            $mayusculas[random_int(0, strlen($mayusculas) - 1)] .
            $minusculas[random_int(0, strlen($minusculas) - 1)] .
            $numeros[random_int(0, strlen($numeros) - 1)] .
            $especiales[random_int(0, strlen($especiales) - 1)] .
            Str::random(6)
        ) . $base[random_int(0, strlen($base) - 1)];
    }
}
