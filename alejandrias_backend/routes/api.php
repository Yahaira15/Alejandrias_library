<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiUsuario\UsuarioController;
use App\Http\Controllers\ApiForo\ForoController;
use App\Http\Controllers\ApiCategoria\CategoriaController;


Route::post('/register', [UsuarioController::class, 'register']);
Route::post('/login', [UsuarioController::class, 'login']);
Route::get('/verificar-apodo/{apodo}', [UsuarioController::class, 'verificarApodo']);

Route::get('/mis-foros/{id}', [ForoController::class, 'misForos']);
Route::post('/foros', [ForoController::class, 'store']);
Route::get('/foros', [ForoController::class, 'index']);
Route::get('/foros/{foro_id}', [ForoController::class, 'show']);
Route::put('/foros/{foro_id}', [ForoController::class, 'update']);
Route::delete('/foros/{foro_id}', [ForoController::class, 'destroy']);

Route::get('/categorias', [CategoriaController::class, 'index']);
Route::get('/categorias/{categoria_id}', [CategoriaController::class, 'show']);
Route::post('/categorias', [CategoriaController::class, 'store']);
Route::get('/categorias/{categoria_id}/foros', [CategoriaController::class, 'foros']);