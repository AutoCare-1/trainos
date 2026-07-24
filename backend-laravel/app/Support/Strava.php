<?php

namespace App\Support;

use App\Models\DeviceConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Espelha backend/src/services/strava.ts do Node. */
class Strava
{
    private static function credenciais(): array
    {
        $clientId = config('services.strava.client_id');
        $clientSecret = config('services.strava.client_secret');
        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException('STRAVA_CLIENT_ID / STRAVA_CLIENT_SECRET não configurados no .env');
        }

        return [$clientId, $clientSecret];
    }

    public static function montarUrlAutorizacao(string $redirectUri, string $state): string
    {
        [$clientId] = self::credenciais();

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'scope' => 'activity:read_all',
            'state' => $state,
        ]);

        return "https://www.strava.com/oauth/authorize?{$params}";
    }

    /** @return array{access_token: string, refresh_token: string, expires_at: int, athlete?: array} */
    public static function trocarCodigoPorToken(string $code): array
    {
        [$clientId, $clientSecret] = self::credenciais();

        $response = Http::asJson()->post('https://www.strava.com/oauth/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Strava rejeitou a troca de código: {$response->status()} {$response->body()}");
        }

        return $response->json();
    }

    private static function renovarToken(DeviceConnection $conexao): DeviceConnection
    {
        [$clientId, $clientSecret] = self::credenciais();

        $response = Http::asJson()->post('https://www.strava.com/oauth/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $conexao->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Falha ao renovar token do Strava: {$response->status()} {$response->body()}");
        }

        $dados = $response->json();

        $conexao->update([
            'access_token' => $dados['access_token'],
            'refresh_token' => $dados['refresh_token'],
            'expires_at' => Carbon::createFromTimestamp($dados['expires_at']),
        ]);

        return $conexao;
    }

    // Garante um access_token válido, renovando automaticamente se estiver perto de expirar.
    private static function tokenValido(DeviceConnection $conexao): DeviceConnection
    {
        $margemSegundos = 5 * 60; // renova com 5min de folga
        if ($conexao->expires_at->timestamp - now()->timestamp > $margemSegundos) {
            return $conexao;
        }

        return self::renovarToken($conexao);
    }

    public static function sincronizarAtividades(string $studentId): int
    {
        $conexao = DeviceConnection::where('student_id', $studentId)
            ->where('provider', 'strava')
            ->first();
        if (! $conexao) {
            throw new RuntimeException('Aluno não conectou o Strava ainda');
        }

        $atual = self::tokenValido($conexao);

        $response = Http::withToken($atual->access_token)
            ->get('https://www.strava.com/api/v3/athlete/activities', ['per_page' => 30]);
        if ($response->failed()) {
            throw new RuntimeException("Erro ao buscar atividades no Strava: {$response->status()} {$response->body()}");
        }

        $inseridas = 0;
        foreach ($response->json() as $a) {
            $criada = DB::table('external_activities')->insertOrIgnore([
                'id' => (string) Str::orderedUuid(),
                'student_id' => $studentId,
                'provider' => 'strava',
                'external_id' => (string) $a['id'],
                'activity_type' => $a['type'],
                'name' => $a['name'],
                'started_at' => $a['start_date'],
                'duration_seconds' => $a['moving_time'],
                'distance_meters' => $a['distance'],
                'calories' => $a['calories'] ?? null,
                'avg_heart_rate' => $a['average_heartrate'] ?? null,
                'raw_payload' => json_encode($a),
            ]);
            if ($criada) {
                $inseridas++;
            }
        }

        return $inseridas;
    }
}
