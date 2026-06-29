<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiUsuario\UsuarioController;
use App\Http\Controllers\ApiForo\ForoController;
use App\Http\Controllers\ApiCategoria\CategoriaController;
use App\Http\Controllers\ApiForo\PublicacionController;
use App\Http\Controllers\ApiForo\ComentarioController;
use App\Http\Controllers\ApiForo\NotificacionController;
use App\Http\Controllers\ApiAdmin\AdminController;
use App\Http\Controllers\ApiAdmin\ModeracionIaController;
use App\Http\Controllers\ApiReporte\ReporteController;
use App\Http\Controllers\ApiReporte\SancionController;
use App\Http\Controllers\ApiIa\ChatIaController;
use App\Http\Controllers\ApiIa\ChatRiskAlertController;
use App\Http\Controllers\ApiGamification\LogroController;
use App\Http\Controllers\ApiGamification\GamificationController;


Route::post('/register', [UsuarioController::class, 'register']);
Route::post('/login', [UsuarioController::class, 'login']);
Route::post('/aceptar-terminos', [UsuarioController::class, 'aceptarTerminos']);
Route::post('/recuperar-password', [UsuarioController::class, 'recuperarPassword']);
Route::get('/verificar-apodo/{apodo}', [UsuarioController::class, 'verificarApodo']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UsuarioController::class, 'logout']);
    Route::get('/perfil', [UsuarioController::class, 'perfil']);
    Route::put('/perfil', [UsuarioController::class, 'update']);
    Route::put('/perfil/intereses', [UsuarioController::class, 'updateIntereses']);
    Route::delete('/perfil', [UsuarioController::class, 'destroy']);
    Route::get('/logros', [LogroController::class, 'perfil']);
    Route::post('/logros/sincronizar', [LogroController::class, 'sincronizar']);
    Route::post('/logros/demo', [LogroController::class, 'demo']);
    Route::post('/logros/eventos', [LogroController::class, 'registrarEvento']);
    Route::get('/gamificacion/panel', [GamificationController::class, 'panel']);
    Route::get('/gamificacion/racha', [GamificationController::class, 'racha']);
    Route::post('/gamificacion/racha/reclamar', [GamificationController::class, 'reclamarRacha']);
    Route::get('/gamificacion/misiones', [GamificationController::class, 'misiones']);
    Route::post('/gamificacion/misiones/{usuarioMisionId}/reclamar', [GamificationController::class, 'reclamarMision']);
    Route::get('/gamificacion/ranking', [GamificationController::class, 'ranking']);

});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/moderacion', [ModeracionIaController::class, 'index']);
    Route::get('/moderacion/{id}', [ModeracionIaController::class, 'show']);
    Route::post('/moderacion/{id}/aprobar', [ModeracionIaController::class, 'aprobar']);
    Route::post('/moderacion/{id}/rechazar', [ModeracionIaController::class, 'rechazar']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/usuarios', [AdminController::class, 'usuarios']);
    Route::post('/usuarios', [AdminController::class, 'crearUsuario']);
    Route::put('/usuarios/{id}', [AdminController::class, 'actualizarUsuario']);
    Route::delete('/usuarios/{id}', [AdminController::class, 'eliminarUsuario']);

    Route::get('/foros', [AdminController::class, 'foros']);
    Route::post('/foros', [AdminController::class, 'crearForo']);
    Route::put('/foros/{id}', [AdminController::class, 'actualizarForo']);

    Route::get('/categorias', [AdminController::class, 'categorias']);
    Route::post('/categorias', [CategoriaController::class, 'store']);
    Route::put('/categorias/{id}', [CategoriaController::class, 'update']);
    Route::post('/categorias/{id}', [CategoriaController::class, 'update']);
    Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy']);
    Route::get('/categorias/{categoria_id}/subcategorias', [CategoriaController::class, 'subcategorias']);
    Route::post('/subcategorias', [CategoriaController::class, 'storeSubcategoria']);
    Route::put('/subcategorias/{id}', [CategoriaController::class, 'updateSubcategoria']);
    Route::post('/subcategorias/{id}', [CategoriaController::class, 'updateSubcategoria']);
    Route::delete('/subcategorias/{id}', [CategoriaController::class, 'destroySubcategoria']);

    Route::get('/publicaciones', [AdminController::class, 'publicaciones']);
    Route::post('/publicaciones', [AdminController::class, 'crearPublicacion']);
    Route::put('/publicaciones/{id}', [AdminController::class, 'actualizarPublicacion']);
    Route::delete('/publicaciones/{id}', [PublicacionController::class, 'destroy']);

    Route::get('/comentarios', [AdminController::class, 'comentarios']);
    Route::post('/comentarios', [AdminController::class, 'crearComentario']);
    Route::put('/comentarios/{id}', [AdminController::class, 'actualizarComentario']);
    Route::delete('/comentarios/{id}', [ComentarioController::class, 'destroy']);

    Route::get('/reportes', [ReporteController::class, 'index']);
    Route::put('/reportes/{id}', [ReporteController::class, 'update']);
    Route::delete('/reportes/{id}', [ReporteController::class, 'destroy']);
    Route::post('/reportes/{id}/sancionar', [ReporteController::class, 'sancionar']);

    Route::get('/sanciones', [SancionController::class, 'index']);
    Route::post('/sanciones', [SancionController::class, 'store']);
    Route::put('/sanciones/{id}', [SancionController::class, 'update']);
    Route::delete('/sanciones/{id}', [SancionController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/foros', [ForoController::class, 'store']);
    Route::put('/foros/{id}', [ForoController::class, 'update']);
    Route::post('/foros/{id}', [ForoController::class, 'update']);
    Route::delete('/foros/{id}', [ForoController::class, 'destroy']);
    Route::get('/mis-foros', [ForoController::class, 'misForos']);
    Route::get('/foros-favoritos', [ForoController::class, 'forosFavoritos']);
    Route::post('/foros/privado/buscar', [ForoController::class, 'buscarPrivadoPorPassword']);
    Route::post('/foros/{id}/registrarse', [ForoController::class, 'registrar']);
    Route::get('/foros/{id}/registro', [ForoController::class, 'verificarRegistro']);
    Route::delete('/foros/{id}/registro', [ForoController::class, 'eliminarRegistro']);
    Route::post('/foros/{id}/favorito', [ForoController::class, 'toggleFavorito']);
    Route::post('/foros/{id}/password', [ForoController::class, 'revelarPassword']);
    Route::post('/foros/{id}/puntuacion', [ForoController::class, 'puntuar']);
    Route::delete('/foros/{id}/puntuacion', [ForoController::class, 'eliminarPuntuacion']);
    Route::post('/publicaciones/{id}/like', [PublicacionController::class, 'toggleLike']);
    Route::post('/comentarios/{id}/like', [ComentarioController::class, 'toggleLike']);
});
Route::get('/foros-publicos', [ForoController::class, 'forosPublicos']);
Route::get('/foros', [ForoController::class, 'index']);
Route::get('/foros/{foro_id}', [ForoController::class, 'show']);

Route::get('/categorias', [CategoriaController::class, 'index']);
Route::get('/categorias/{categoria_id}', [CategoriaController::class, 'show']);
Route::get('/categorias/{categoria_id}/foros', [CategoriaController::class, 'foros']);
Route::get('/categorias/{categoria_id}/subcategorias', [CategoriaController::class, 'subcategorias']);

Route::get('/foros/{foroId}/publicaciones', [PublicacionController::class, 'index']);
Route::post('/foros/{foroId}/publicaciones', [PublicacionController::class, 'store']);
Route::middleware('auth:sanctum')->get('/publicaciones/{id}/registro', [PublicacionController::class, 'verificarRegistro']);
Route::get('/publicaciones/{id}', [PublicacionController::class, 'show']);
Route::put('/publicaciones/{id}', [PublicacionController::class, 'update']);
Route::delete('/publicaciones/{id}', [PublicacionController::class, 'destroy']);

Route::get('/publicaciones/{publicacionId}/comentarios', [ComentarioController::class, 'index']);
Route::post('/publicaciones/{publicacionId}/comentarios', [ComentarioController::class, 'store']);
Route::get('/comentarios/{id}', [ComentarioController::class, 'show']);
Route::get('/comentarios/{comentarioId}/respuestas', [ComentarioController::class, 'respuestas']);
Route::post('/comentarios/{comentarioId}/respuestas', [ComentarioController::class, 'responder']);
Route::put('/comentarios/{id}', [ComentarioController::class, 'update']);
Route::delete('/comentarios/{id}', [ComentarioController::class, 'destroy']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::put('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);
    Route::get('/notificaciones-contador', [NotificacionController::class, 'contador']);
    Route::post('/reportes', [ReporteController::class, 'store']);
    Route::post('/ia/chat', [ChatIaController::class, 'store']);
    Route::post('/ia/chat-alerta-riesgo', [ChatRiskAlertController::class, 'store']);
});
