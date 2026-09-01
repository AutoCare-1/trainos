<?php

namespace Tests\Feature;

use App\Models\MealLog;
use App\Models\Professional;
use App\Models\Student;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A poda existe porque o diário alimentar não tem prazo de validade e a foto
 * pesa até 8 MB — sem ela o disco cresce pra sempre.
 *
 * O ponto do comando é ser cirúrgico: some a imagem, fica o texto. O personal
 * usa o histórico pra enxergar padrão ao longo do tempo, e ele consegue fazer
 * isso lendo "31/08 · Almoço · arroz, feijão e frango" — não precisa da foto
 * de meses atrás.
 */
class PodarFotosDeRefeicaoTest extends TestCase
{
    use RefreshDatabase;

    private function alunoComRefeicaoComFoto(int $diasAtras): MealLog
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno Teste',
            'invite_token' => uniqid('token'),
        ]);

        $caminho = Uploads::storePrivate(
            UploadedFile::fake()->image('almoco.jpg'),
            'refeicoes',
            $student->invite_token
        );

        return MealLog::create([
            'student_id' => $student->id,
            'data' => now()->subDays($diasAtras)->toDateString(),
            'momento' => 'almoco',
            'descricao' => 'Arroz, feijão e frango',
            'file_path' => $caminho,
        ]);
    }

    public function test_poda_apaga_a_foto_antiga_e_mantem_o_texto(): void
    {
        $refeicao = $this->alunoComRefeicaoComFoto(diasAtras: 45);
        $caminhoAbsoluto = Uploads::privateAbsolutePath($refeicao->file_path);
        $this->assertFileExists($caminhoAbsoluto);

        $this->artisan('nutricao:podar-fotos')->assertSuccessful();

        $this->assertFileDoesNotExist($caminhoAbsoluto);
        // O registro continua: é ele que o personal lê pra ver o padrão.
        $refeicao->refresh();
        $this->assertNull($refeicao->file_path);
        $this->assertSame('Arroz, feijão e frango', $refeicao->descricao);
    }

    public function test_foto_recente_nao_e_tocada(): void
    {
        $refeicao = $this->alunoComRefeicaoComFoto(diasAtras: 3);
        $caminhoAbsoluto = Uploads::privateAbsolutePath($refeicao->file_path);

        $this->artisan('nutricao:podar-fotos')->assertSuccessful();

        $this->assertFileExists($caminhoAbsoluto);
        $this->assertNotNull($refeicao->fresh()->file_path);
    }

    public function test_dry_run_nao_apaga_nada(): void
    {
        $refeicao = $this->alunoComRefeicaoComFoto(diasAtras: 45);
        $caminhoAbsoluto = Uploads::privateAbsolutePath($refeicao->file_path);

        $this->artisan('nutricao:podar-fotos', ['--dry-run' => true])->assertSuccessful();

        $this->assertFileExists($caminhoAbsoluto);
        $this->assertNotNull($refeicao->fresh()->file_path);
    }

    public function test_janela_e_configuravel(): void
    {
        $refeicao = $this->alunoComRefeicaoComFoto(diasAtras: 10);

        $this->artisan('nutricao:podar-fotos', ['--dias' => 7])->assertSuccessful();

        $this->assertNull($refeicao->fresh()->file_path);
    }
}
