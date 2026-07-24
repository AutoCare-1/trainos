<?php

use App\Http\Controllers\AlunoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AcademiaController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DesafioController;
use App\Http\Controllers\ExercicioController;
use App\Http\Controllers\ModeloController;
use App\Http\Controllers\StravaController;
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

    Route::prefix('modelos')->group(function () {
        Route::get('/', [ModeloController::class, 'index']);
        Route::post('/', [ModeloController::class, 'store']);
        Route::get('/{id}', [ModeloController::class, 'show']);
        Route::delete('/{id}', [ModeloController::class, 'destroy']);
    });

    Route::prefix('desafios')->group(function () {
        Route::get('/', [DesafioController::class, 'index']);
        Route::post('/', [DesafioController::class, 'store']);
        Route::get('/{id}', [DesafioController::class, 'show']);
        Route::delete('/{id}', [DesafioController::class, 'destroy']);
    });

    Route::prefix('academia')->group(function () {
        Route::get('/', [AcademiaController::class, 'index']);
        Route::get('/{submissionId}', [AcademiaController::class, 'show']);
        Route::patch('/{submissionId}/aprovar', [AcademiaController::class, 'aprovar']);
        Route::patch('/{submissionId}/rejeitar', [AcademiaController::class, 'rejeitar']);
    });

    Route::prefix('conteudo')->group(function () {
        Route::get('/', [ContentController::class, 'index']);
        Route::post('/', [ContentController::class, 'store']);
        Route::patch('/{id}', [ContentController::class, 'update']);
    });
});

// Rotas públicas: autenticadas pelo invite_token do aluno, não por JWT (mesmo
// padrão do backend Node — usadas pelo fluxo de OAuth do Strava e pelo portal).
Route::prefix('strava')->group(function () {
    Route::get('/conectar/{token}', [StravaController::class, 'conectar']);
    Route::get('/callback', [StravaController::class, 'callback']);
    Route::get('/{token}/status', [StravaController::class, 'status']);
    Route::post('/{token}/sincronizar', [StravaController::class, 'sincronizar']);
});
