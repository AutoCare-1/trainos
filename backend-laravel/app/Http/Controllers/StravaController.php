<?php

namespace App\Http\Controllers;

use App\Models\DeviceConnection;
use App\Models\ExternalActivity;
use App\Models\Student;
use App\Support\Strava;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StravaController extends Controller
{
    // Rotas públicas: autenticadas pelo invite_token do aluno (link do portal), não por JWT.
    private function buscarAlunoPorToken(string $token): ?Student
    {
        return Student::where('invite_token', $token)->where('status', 'active')->first();
    }

    // GET /strava/conectar/:token — redireciona o aluno para a tela de autorização do Strava
    public function conectar(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $redirectUri = config('app.backend_url').'/strava/callback';
        $url = Strava::montarUrlAutorizacao($redirectUri, $token);

        return redirect()->away($url);
    }

    // GET /strava/callback — o Strava chama esta rota depois que o aluno autoriza
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        $voltarPara = fn (string $status) => redirect()->away(
            config('app.frontend_url').'/aluno/'.rawurlencode($state ?? '')."?strava={$status}"
        );

        if ($error || ! $code || ! $state) {
            return $voltarPara('erro');
        }

        $student = $this->buscarAlunoPorToken($state);
        if (! $student) {
            return $voltarPara('erro');
        }

        // O Strava pode chamar este callback mais de uma vez pro mesmo "code"
        // (usuário atualiza a página, navegador reenvia) — a segunda tentativa
        // sempre falha porque o code já foi consumido na primeira. Sem captura
        // aqui, essa exceção derrubava o redirect OAuth com um 500 cru em vez
        // de voltar pro app com "?strava=erro" (mesmo tratamento já dado aos
        // outros casos de falha desta rota).
        try {
            $dados = Strava::trocarCodigoPorToken($code);

            DeviceConnection::updateOrCreate(
                ['student_id' => $student->id, 'provider' => 'strava'],
                [
                    'provider_athlete_id' => (string) ($dados['athlete']['id'] ?? ''),
                    'access_token' => $dados['access_token'],
                    'refresh_token' => $dados['refresh_token'],
                    'expires_at' => Carbon::createFromTimestamp($dados['expires_at']),
                    'scope' => 'activity:read_all',
                ]
            );
        } catch (\Throwable) {
            return $voltarPara('erro');
        }

        return $voltarPara('conectado');
    }

    // GET /strava/:token/status — status da conexão + atividades sincronizadas
    public function status(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $conexoes = DB::table('device_connections')
            ->where('student_id', $student->id)
            ->select('provider', 'connected_at')
            ->get();

        // raw_payload é coluna json — usa o model (com cast pra array) em vez de DB::table()
        // puro, senão volta como string crua e diverge do pg do Node (que já entrega parseado).
        $atividades = ExternalActivity::where('student_id', $student->id)
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        return response()->json([
            'conectado' => $conexoes->isNotEmpty(),
            'conexoes' => $conexoes,
            'atividades' => $atividades,
        ]);
    }

    // POST /strava/:token/sincronizar — busca atividades recentes do Strava
    public function sincronizar(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        try {
            $novas = Strava::sincronizarAtividades($student->id);

            return response()->json(['novas' => $novas]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
