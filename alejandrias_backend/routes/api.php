<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiUsuario\UsuarioController;
use App\Http\Controllers\ApiForo\ForoController;
use App\Http\Controllers\ApiCategoria\CategoriaController;

Route::post('/register', [UsuarioController::class, 'register']);
Route::post('/login', [UsuarioController::class, 'login']);

Route::post('/foros', [ForoController::class, 'store']);
Route::get('/foros', [ForoController::class, 'index']);
Route::get('/foros/{id}', [ForoController::class, 'show']);
Route::put('/foros/{id}', [ForoController::class, 'update']);
Route::delete('/foros/{id}', [ForoController::class, 'destroy']);

Route::get('/categorias', [CategoriaController::class, 'index']);
Route::get('/categorias/{id}', [CategoriaController::class, 'show']);
Route::post('/categorias', [CategoriaController::class, 'store']);
Route::get('/categorias/{id}/foros', [CategoriaController::class, 'foros']);