<?php

namespace Tests\Feature;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O Strava pode chamar /strava/callback mais de uma vez pro mesmo "code"
 * (usuário atualiza a página do navegador durante o redirect OAuth) — a
 * segunda tentativa sempre falha porque o code já foi consumido. Antes da
 * correção, isso derrubava a rota com 500 em vez de redirecionar de volta
 * pro app com "?strava=erro".
 */
class StravaCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_redireciona_com_erro_quando_strava_rejeita_a_troca_de_code(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], ['Authorization' => "Bearer {$token}"])
            ->assertCreated()
            ->json('student.id');
        $inviteToken = \App\Models\Student::find($studentId)->invite_token;

        Http::fake([
            'https://www.strava.com/oauth/token' => Http::response(['message' => 'Bad Request'], 400),
        ]);

        $response = $this->get("/strava/callback?code=ja-usado&state={$inviteToken}");

        $response->assertRedirect();
        $this->assertStringContainsString('strava=erro', $response->headers->get('Location'));
    }
}
