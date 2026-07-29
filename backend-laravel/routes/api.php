<?php

use App\Http\Controllers\AlunoBodyPhotoController;
use App\Http\Controllers\AlunoPosturalController;
use App\Http\Controllers\AlunoRevisaoController;
use App\Http\Controllers\AlunoChatController;
use App\Http\Controllers\AlunoCheckinController;
use App\Http\Controllers\AlunoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AcademiaController;
use App\Http\Controllers\ConsultorIaController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DesafioController;
use App\Http\Controllers\ExercicioController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\ModeloController;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\PortalAcademiaController;
use App\Http\Controllers\PortalBodyPhotoController;
use App\Http\Controllers\PortalPosturalController;
use App\Http\Controllers\PortalChatController;
use App\Http\Controllers\PortalCheckinController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalFormaController;
use App\Http\Controllers\PortalPushController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\StravaController;
use App\Http\Controllers\TreinoController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey']);

Route::prefix('auth')->group(function () {
    // throttle:10,1 — mesmo padrão já usado em push/subscribe: sem isso, login
    // e signup ficam abertos a brute-force de senha e criação em massa de contas.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/signup', [AuthController::class, 'signup']);
        Route::post('/login', [AuthController::class, 'login']);
    });
    Route::middleware('auth.jwt')->get('/me', [AuthController::class, 'me']);
});

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('exercicios')->group(function () {
        Route::get('/', [ExercicioController::class, 'index']);
        Route::post('/{id}/video', [ExercicioController::class, 'uploadVideo']);
        Route::delete('/{id}/video', [ExercicioController::class, 'deleteVideo']);
    });

    Route::prefix('alunos')->group(function () {
        Route::get('/', [AlunoController::class, 'index']);
        Route::post('/', [AlunoController::class, 'store']);
        Route::get('/{id}', [AlunoController::class, 'show']);
        Route::patch('/{id}', [AlunoController::class, 'update']);
        Route::patch('/{id}/cobranca/encerrar', [AlunoController::class, 'encerrarCobranca']);
        Route::patch('/{id}/lembrete-pagamento', [AlunoController::class, 'lembretePagamento']);

        Route::post('/{id}/medicoes', [AlunoController::class, 'postMedicao']);
        Route::patch('/{id}/avaliacao', [AlunoController::class, 'updateAvaliacao']);
        Route::post('/{id}/foto', [AlunoController::class, 'uploadFoto']);

        Route::get('/{id}/body-photos', [AlunoBodyPhotoController::class, 'index']);
        Route::get('/{id}/body-photos/{photoId}/imagem', [AlunoBodyPhotoController::class, 'imagem']);
        Route::get('/{id}/postural', [AlunoPosturalController::class, 'index']);
        Route::get('/{id}/postural/{assessmentId}/imagem/{angulo}', [AlunoPosturalController::class, 'imagem']);
        Route::get('/{id}/revisoes', [AlunoRevisaoController::class, 'index']);

        Route::get('/{id}/checkins/summary', [AlunoCheckinController::class, 'summary']);
        Route::get('/{id}/checkins', [AlunoCheckinController::class, 'index']);
        Route::get('/{id}/checkins/{checkinId}/imagem', [AlunoCheckinController::class, 'imagem']);

        Route::get('/{id}/mensagens', [AlunoChatController::class, 'index']);
        Route::post('/{id}/mensagens', [AlunoChatController::class, 'store']);
        Route::patch('/{id}/autopilot', [AlunoChatController::class, 'autopilot']);
    });

    Route::prefix('treinos')->group(function () {
        Route::post('/', [TreinoController::class, 'store']);
        Route::get('/{id}', [TreinoController::class, 'show']);
        Route::post('/{id}/enviar', [TreinoController::class, 'enviar']);
        Route::patch('/{id}/arquivar', [TreinoController::class, 'arquivar']);
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

    Route::prefix('consultor-ia')->group(function () {
        Route::get('/', [ConsultorIaController::class, 'index']);
        Route::post('/chat', [ConsultorIaController::class, 'chat']);
    });

    Route::get('/negocio', [NegocioController::class, 'index']);

    // throttle:10,1 — endpoint de escrita novo (registra subscription de push),
    // sem padrão de proteção anterior no projeto pra reaproveitar; 10/min é
    // generoso pro uso real (ativar/reativar manualmente) e barra abuso de
    // registro em massa de subscriptions falsas.
    Route::prefix('push')->middleware('throttle:10,1')->group(function () {
        Route::post('/subscribe', [PushSubscriptionController::class, 'subscribe']);
        Route::delete('/subscribe', [PushSubscriptionController::class, 'unsubscribe']);
    });

    Route::prefix('notificacoes')->group(function () {
        Route::get('/tipos', [NotificacaoController::class, 'index']);
        Route::patch('/tipos/{chave}', [NotificacaoController::class, 'toggle']);
    });

    Route::prefix('gastos')->group(function () {
        Route::get('/', [GastoController::class, 'index']);
        Route::post('/', [GastoController::class, 'store']);
        Route::patch('/{id}', [GastoController::class, 'update']);
        Route::patch('/{id}/encerrar', [GastoController::class, 'encerrar']);
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

Route::prefix('portal')->group(function () {
    Route::get('/{token}', [PortalController::class, 'show']);
    Route::post('/{token}/avaliacao', [PortalController::class, 'avaliacao']);
    Route::post('/{token}/foto', [PortalController::class, 'foto']);

    Route::get('/{token}/body-photos', [PortalBodyPhotoController::class, 'index']);
    Route::post('/{token}/body-photos', [PortalBodyPhotoController::class, 'store']);
    Route::get('/{token}/body-photos/{photoId}/imagem', [PortalBodyPhotoController::class, 'imagem']);
    Route::get('/{token}/postural', [PortalPosturalController::class, 'index']);
    Route::post('/{token}/postural', [PortalPosturalController::class, 'store']);
    Route::get('/{token}/postural/{id}/imagem/{angulo}', [PortalPosturalController::class, 'imagem']);
    Route::post('/{token}/revisao', [PortalController::class, 'revisao']);

    Route::get('/{token}/checkins/summary', [PortalCheckinController::class, 'summary']);
    Route::get('/{token}/checkins', [PortalCheckinController::class, 'index']);
    Route::post('/{token}/checkins', [PortalCheckinController::class, 'store']);
    Route::get('/{token}/checkins/{checkinId}/imagem', [PortalCheckinController::class, 'imagem']);

    Route::get('/{token}/academia', [PortalAcademiaController::class, 'index']);
    Route::get('/{token}/academia/{submissionId}', [PortalAcademiaController::class, 'show']);
    Route::post('/{token}/academia', [PortalAcademiaController::class, 'store']);

    Route::post('/{token}/forma', [PortalFormaController::class, 'store']);

    Route::post('/{token}/sessoes', [PortalController::class, 'iniciarSessao']);
    Route::post('/{token}/sessoes/{sessionId}/registros', [PortalController::class, 'registrarSerie']);
    Route::post('/{token}/sessoes/{sessionId}/concluir', [PortalController::class, 'concluirSessao']);

    Route::get('/{token}/mensagens', [PortalChatController::class, 'index']);
    Route::post('/{token}/mensagens', [PortalChatController::class, 'store']);

    // throttle:push-portal — chaveado pelo token do portal (App\Providers\AppServiceProvider),
    // não por IP: não penaliza vários usuários legítimos atrás do mesmo IP
    // (rede de academia/escola) e ainda barra abuso concentrado num token vazado.
    Route::middleware('throttle:push-portal')->group(function () {
        Route::post('/{token}/push/subscribe', [PortalPushController::class, 'subscribe']);
        Route::delete('/{token}/push/subscribe', [PortalPushController::class, 'unsubscribe']);
    });
});
