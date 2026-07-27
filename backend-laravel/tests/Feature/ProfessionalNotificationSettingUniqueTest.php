<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\ProfessionalNotificationSetting;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 11 da revisão: unicidade de preferência global (student_id null) agora
 * é garantida por uma constraint UNIQUE de verdade no banco (via coluna
 * gerada student_id_chave), não só por updateOrCreate() na aplicação.
 */
class ProfessionalNotificationSettingUniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTypesSeeder::class);
    }

    public function test_banco_rejeita_segunda_linha_global_duplicada_mesmo_sem_updateOrCreate(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);

        ProfessionalNotificationSetting::create([
            'professional_id' => $professional->id,
            'tipo_chave' => 'alerta_sexta',
            'student_id' => null,
            'enabled' => true,
        ]);

        $this->expectException(QueryException::class);

        // create() direto, sem updateOrCreate — se a unicidade dependesse só de
        // disciplina de código, isso criaria uma segunda linha global silenciosamente.
        ProfessionalNotificationSetting::create([
            'professional_id' => $professional->id,
            'tipo_chave' => 'alerta_sexta',
            'student_id' => null,
            'enabled' => false,
        ]);
    }

    public function test_permite_preferencia_global_e_por_aluno_diferente_coexistirem(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = \App\Models\Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno Teste',
            'invite_token' => uniqid('token'),
        ]);

        ProfessionalNotificationSetting::create([
            'professional_id' => $professional->id, 'tipo_chave' => 'alerta_sexta',
            'student_id' => null, 'enabled' => true,
        ]);
        ProfessionalNotificationSetting::create([
            'professional_id' => $professional->id, 'tipo_chave' => 'alerta_sexta',
            'student_id' => $student->id, 'enabled' => false,
        ]);

        $this->assertSame(2, ProfessionalNotificationSetting::count());
    }
}
