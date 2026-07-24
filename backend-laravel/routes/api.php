<?php

use App\Http\Controllers\AlunoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExercicioController;
use App\Http\Controllers\TreinoController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:api')->get('/me', [AuthController::class, 'me']);
});

Route::middleware('auth:api')->group(function () {
    Route::prefix('exercicios')->group(function () {
        Route::get('/', [ExercicioController::class, 'index']);
        Route::post('/{id}/video', [ExercicioController::class, 'uploadVideo']);
        Route::delete('/{id}/video', [ExercicioController::class, 'deleteVideo']);
    });

    Route::prefix('alunos')->group(function () {
        Route::get('/', [AlunoController::class, 'index']);
        Route::post('/', [AlunoController::class, 'store']);
        Route::get('/{id}', [AlunoController::class, 'show']);
    });

    Route::prefix('treinos')->group(function () {
        Route::post('/', [TreinoController::class, 'store']);
        Route::get('/{id}', [TreinoController::class, 'show']);
        Route::post('/{id}/enviar', [TreinoController::class, 'enviar']);
    });
});
