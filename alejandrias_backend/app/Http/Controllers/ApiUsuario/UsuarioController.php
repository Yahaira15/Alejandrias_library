<?php

namespace App\Http\Controllers\ApiUsuario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
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
            'usuario_rol' => 'required|in:explorador,lider'
        ], [
            'usuario_email.unique' => 'Este correo ya está registrado',
            'usuario_apodo.unique' => 'Este apodo ya está en uso',
            'usuario_password.regex' => 'La contraseña debe tener al menos una mayúscula, un número y un carácter especial',
            'usuario_password.min' => 'La contraseña debe tener mínimo 8 caracteres'
        ]);

        $usuario = Usuario::create([
            'usuario_nombre' => $request->usuario_nombre,
            'usuario_apellido' => $request->usuario_apellido,
            'usuario_apodo' => $request->usuario_apodo,
            'usuario_email' => $request->usuario_email,
            'usuario_password' => Hash::make($request->usuario_password),
            'usuario_rol' => $request->usuario_rol
        ]);

        return response()->json([
            'mensaje' => 'Usuario registrado correctamente',
            'usuario' => [
                'id' => $usuario->usuario_id,
                'nombre' => $usuario->usuario_nombre,
                'apellido' => $usuario->usuario_apellido,
                'apodo' => $usuario->usuario_apodo,
                'email' => $usuario->usuario_email,
                'rol' => $usuario->usuario_rol
            ]
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

        // 🔍 Buscar por email o apodo
        if (filter_var($request->login, FILTER_VALIDATE_EMAIL)) {
            $usuario = Usuario::where('usuario_email', $request->login)->first();
        } else {
            $usuario = Usuario::where('usuario_apodo', $request->login)->first();
        }

        // ❌ Credenciales incorrectas
        if (!$usuario || !Hash::check($request->usuario_password, $usuario->usuario_password)) {
            return response()->json([
                'mensaje' => 'Credenciales incorrectas'
            ], 401);
        }

        // 🚫 Usuario bloqueado
        if ($usuario->usuario_bloqueado) {
            return response()->json([
                'mensaje' => 'Usuario bloqueado'
            ], 403);
        }

        // 🔐 CREAR TOKEN
        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'mensaje' => 'Login exitoso',
            'usuario' => $usuario,
            'token' => $token // 🔥 AQUÍ ESTÁ LA CLAVE
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

// 🔹 VER PERFIL
    public function perfil()
    {
        return response()->json(auth()->user(), 200);
    }

    // 🔹 ACTUALIZAR PERFIL
    public function update(Request $request)
    {
        try {

            $usuario = auth()->user();

            $request->validate([
                'usuario_nombre' => 'required|string',
                'usuario_apellido' => 'nullable|string',
                'usuario_apodo' => 'required|string',
                'usuario_email' => 'required|email',
                'usuario_bio' => 'nullable|string',
                'usuario_foto_perfil' => 'nullable|string',
                'usuario_password' => 'nullable|min:6'
            ]);

            $usuario->usuario_nombre = $request->usuario_nombre;
            $usuario->usuario_apellido = $request->usuario_apellido;
            $usuario->usuario_apodo = $request->usuario_apodo;
            $usuario->usuario_email = $request->usuario_email;
            $usuario->usuario_bio = $request->usuario_bio;
            $usuario->usuario_foto_perfil = $request->usuario_foto_perfil;

            // 🔐 Si quiere cambiar contraseña
            if ($request->filled('usuario_password')) {
                $usuario->usuario_password = Hash::make($request->usuario_password);
            }

            $usuario->save();

            return response()->json([
                'mensaje' => 'Perfil actualizado',
                'usuario' => $usuario
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    // 🔹 ELIMINAR CUENTA
    public function destroy()
    {
        try {

            $usuario = auth()->user();

            // 🔥 eliminar tokens
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
}
