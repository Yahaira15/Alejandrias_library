<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if (!$usuario || $usuario->usuario_rol !== 'admin') {
            return response()->json(['error' => 'Acceso solo para administradores'], 403);
        }

        return $next($request);
    }
}
