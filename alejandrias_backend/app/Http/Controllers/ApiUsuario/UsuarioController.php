<?php

namespace App\Http\Controllers\ApiUsuario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function register(Request $request)
    //**
    // Body JSON de ejemplo para registro:
    /*{
        "usuario_nombre": "Kevin",
        "usuario_email": "kevin@gmail.com",
        "usuario_password": "123456",
        "usuario_rol": "explorador"
        }
    */
    {
        try {
            $request->validate([
                'usuario_nombre' => 'required|string|max:255',
                'usuario_email' => 'required|email|unique:usuario,usuario_email',
                'usuario_password' => 'required|min:6',
                'usuario_rol' => 'required|in:explorador,lider'
            ]);

            $usuario = Usuario::create([
                'usuario_nombre' => $request->usuario_nombre,
                'usuario_email' => $request->usuario_email,
                'usuario_password' => Hash::make($request->usuario_password),
                'usuario_rol' => $request->usuario_rol
            ]);

            return response()->json([
                'mensaje' => 'Usuario registrado correctamente',
                'usuario' => [
                    'id' => $usuario->usuario_id,
                    'nombre' => $usuario->usuario_nombre,
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
    //**
    // Body JSON de ejemplo para el login:
    /*{
        "usuario_email": "kevin@gmail.com",
        "usuario_password": "123456"
        }
    */
    {
        try {
            $request->validate([
                'usuario_email' => 'required|email',
                'usuario_password' => 'required'
            ]);

            $usuario = Usuario::where('usuario_email', $request->usuario_email)->first();

            if (!$usuario || !Hash::check($request->usuario_password, $usuario->usuario_password)) {
                return response()->json([
                    'mensaje' => 'Credenciales incorrectas'
                ], 401);
            }

            return response()->json([
                'mensaje' => 'Login exitoso',
                'usuario' => [
                    'id' => $usuario->usuario_id,
                    'nombre' => $usuario->usuario_nombre,
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