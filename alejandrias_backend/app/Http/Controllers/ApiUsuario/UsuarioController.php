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
        ]);
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
        ], 201);

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

        if ($usuario->usuario_bloqueado) {
            return response()->json([
                'mensaje' => 'Usuario bloqueado'
            ], 403);
        }

        return response()->json([
            'mensaje' => 'Login exitoso',
            'usuario' => [
                'id' => $usuario->usuario_id,
                'nombre' => $usuario->usuario_nombre,
                'apellido' => $usuario->usuario_apellido,
                'apodo' => $usuario->usuario_apodo,
                'email' => $usuario->usuario_email,
                'rol' => $usuario->usuario_rol
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error en el login',
            'detalle' => $e->getMessage()
        ], 500);
    }
}
}