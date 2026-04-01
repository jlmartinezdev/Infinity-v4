<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Rutas públicas de autenticación (sin sesiones necesarias)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Las rutas protegidas (/logout y /user) están en web.php bajo el prefijo /api
// para que puedan usar sesiones correctamente
