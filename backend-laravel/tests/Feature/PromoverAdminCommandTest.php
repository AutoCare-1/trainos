<?php

namespace Tests\Feature;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * usuarios:promover-admin — a única porta de entrada pro CRM quando o banco
 * não tem nenhum admin ainda (POST /admin/admins exige já ser admin).
 */
class PromoverAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private function criarPersonal(string $email): Professional
    {
        return Professional::create([
            'name' => 'Dono Teste',
            'email' => $email,
            'password_hash' => bcrypt('senha12345'),
        ]);
    }

    public function test_promove_personal_pelo_email(): void
    {
        $personal = $this->criarPersonal('dono@example.com');
        $this->assertFalse((bool) $personal->is_admin);

        $this->artisan('usuarios:promover-admin', ['email' => 'dono@example.com'])
            ->assertExitCode(0);

        $this->assertTrue((bool) $personal->fresh()->is_admin);
    }

    public function test_rodar_duas_vezes_nao_quebra(): void
    {
        $this->criarPersonal('dono@example.com');

        $this->artisan('usuarios:promover-admin', ['email' => 'dono@example.com'])->assertExitCode(0);
        $this->artisan('usuarios:promover-admin', ['email' => 'dono@example.com'])->assertExitCode(0);

        $this->assertSame(1, Professional::where('is_admin', true)->count());
    }

    public function test_revogar_tira_o_acesso(): void
    {
        $personal = $this->criarPersonal('dono@example.com');
        $this->artisan('usuarios:promover-admin', ['email' => 'dono@example.com'])->assertExitCode(0);

        $this->artisan('usuarios:promover-admin', ['email' => 'dono@example.com', '--revogar' => true])
            ->assertExitCode(0);

        $this->assertFalse((bool) $personal->fresh()->is_admin);
    }

    /**
     * E-mail com typo é o modo de falha mais provável de quem roda isso no
     * console de produção, com pressa. Precisa falhar visível, não em silêncio.
     */
    public function test_email_inexistente_falha(): void
    {
        $this->artisan('usuarios:promover-admin', ['email' => 'ninguem@example.com'])
            ->assertExitCode(1);
    }
}
