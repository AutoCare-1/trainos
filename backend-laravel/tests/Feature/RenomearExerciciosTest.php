<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renomeação da biblioteca a partir do mapa versionado.
 *
 * O que o teste protege, em ordem de risco: (1) o vídeo e o id do exercício
 * têm de sobreviver ao rename — é a linha que muda de nome, nunca uma linha
 * nova; (2) `name` é unique, então colisão com exercício que fica tem de ser
 * pulada com relatório, não estourar no meio do lote; (3) o comando roda em
 * todo deploy, logo precisa ser idempotente.
 *
 * Usa mapa e nomes próprios, nunca `renames_revisao_hugo.php`, pra não quebrar
 * quando aquele arquivo mudar.
 */
class RenomearExerciciosTest extends TestCase
{
    use RefreshDatabase;

    private string $mapa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapa = sys_get_temp_dir().'/renames-'.uniqid().'.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->mapa);
        parent::tearDown();
    }

    private function escreverMapa(array $m): void
    {
        file_put_contents($this->mapa, '<?php return '.var_export($m, true).';');
    }

    private function exercicio(string $nome, array $extra = []): Exercise
    {
        return Exercise::create(array_merge([
            'name' => $nome,
            'muscle_group' => 'Core',
            'equipment' => 'Peso corporal',
            'instructions' => 'Instrução de teste.',
        ], $extra));
    }

    public function test_renomeia_preservando_id_e_video(): void
    {
        $ex = $this->exercicio('__teste Nome Antigo__', ['video_url' => '/uploads/teste.mp4']);
        $this->escreverMapa(['__teste Nome Antigo__' => '__teste Nome Novo__']);

        $this->artisan('exercicios:renomear', ['--force' => true, '--arquivo' => $this->mapa])
            ->assertSuccessful();

        $ex->refresh();
        $this->assertSame('__teste Nome Novo__', $ex->name);
        $this->assertSame('/uploads/teste.mp4', $ex->video_url, 'o vídeo tem de seguir com a linha');
        $this->assertNull(Exercise::where('name', '__teste Nome Antigo__')->first());
    }

    public function test_dry_run_nao_altera(): void
    {
        $this->exercicio('__teste Nome Antigo__');
        $this->escreverMapa(['__teste Nome Antigo__' => '__teste Nome Novo__']);

        $this->artisan('exercicios:renomear', ['--dry-run' => true, '--arquivo' => $this->mapa])
            ->assertSuccessful();

        $this->assertNotNull(Exercise::where('name', '__teste Nome Antigo__')->first());
    }

    public function test_sem_force_nem_dry_run_recusa(): void
    {
        $this->escreverMapa([]);

        $this->artisan('exercicios:renomear', ['--arquivo' => $this->mapa])->assertFailed();
    }

    public function test_pula_quando_o_nome_novo_e_de_exercicio_com_video(): void
    {
        $antigo = $this->exercicio('__teste Nome Antigo__');
        $ocupante = $this->exercicio('__teste Nome Novo__', ['video_url' => '/uploads/ocupante.mp4']);
        $this->escreverMapa(['__teste Nome Antigo__' => '__teste Nome Novo__']);

        $this->artisan('exercicios:renomear', ['--force' => true, '--arquivo' => $this->mapa])
            ->assertFailed();

        $this->assertSame('__teste Nome Antigo__', $antigo->refresh()->name);
        $this->assertSame('/uploads/ocupante.mp4', $ocupante->refresh()->video_url);
    }

    public function test_apaga_duplicata_de_seeder_e_renomeia(): void
    {
        // Cenário da ordem invertida no entrypoint: o seeder já criou a linha
        // com o nome novo, sem vídeo e sem dependência nenhuma.
        $antigo = $this->exercicio('__teste Nome Antigo__', ['video_url' => '/uploads/teste.mp4']);
        $fantasma = $this->exercicio('__teste Nome Novo__');
        $this->escreverMapa(['__teste Nome Antigo__' => '__teste Nome Novo__']);

        $this->artisan('exercicios:renomear', ['--force' => true, '--arquivo' => $this->mapa])
            ->assertSuccessful();

        $this->assertNull(Exercise::find($fantasma->id));
        $this->assertSame('__teste Nome Novo__', $antigo->refresh()->name);
        $this->assertSame('/uploads/teste.mp4', $antigo->video_url);
    }

    public function test_e_idempotente(): void
    {
        $this->exercicio('__teste Nome Antigo__');
        $this->escreverMapa(['__teste Nome Antigo__' => '__teste Nome Novo__']);

        $this->artisan('exercicios:renomear', ['--force' => true, '--arquivo' => $this->mapa]);
        $this->artisan('exercicios:renomear', ['--force' => true, '--arquivo' => $this->mapa])
            ->assertSuccessful();

        $this->assertSame(1, Exercise::whereIn('name', ['__teste Nome Antigo__', '__teste Nome Novo__'])->count());
    }

    public function test_substituicao_apaga_o_retirado_e_passa_o_nome_adiante(): void
    {
        // O Hugo mandou retirar 'Rosca direta com halteres' e deu esse nome ao
        // 'Rosca 21 com halteres', que é o que tem o vídeo certo.
        $retirado = Exercise::create(['name' => 'Rosca direta com halteres', 'muscle_group' => 'Bíceps', 'equipment' => 'Halteres', 'video_url' => 'https://cdn/velho.mp4']);
        $certo = Exercise::create(['name' => 'Rosca 21 com halteres', 'muscle_group' => 'Bíceps', 'equipment' => 'Halteres', 'video_url' => 'https://cdn/certo.mp4']);

        $this->artisan('exercicios:renomear', ['--force' => true]);

        $this->assertNull($retirado->fresh());
        $this->assertSame('Rosca direta com halteres', $certo->fresh()->name);
        $this->assertSame('https://cdn/certo.mp4', $certo->fresh()->video_url);
    }

    public function test_renomear_acerta_o_grupo_muscular_quando_o_nome_novo_pede(): void
    {
        $ex = Exercise::create(['name' => 'Tríceps coice bilateral', 'muscle_group' => 'Tríceps', 'equipment' => 'Halteres']);

        $this->artisan('exercicios:renomear', ['--force' => true]);

        $this->assertSame('Crucifixo inverso com halteres', $ex->fresh()->name);
        $this->assertSame('Ombros', $ex->fresh()->muscle_group);
    }
}
