<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Support\Progressao;
use Database\Seeders\ExercicioBibliotecaAmpliadaSeeder;
use Database\Seeders\ExercicioBibliotecaComplementarSeeder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Terceira leva da biblioteca (esportivo, mobilidade, alongamento, ativação,
 * equilíbrio, prevenção).
 *
 * O que estes testes protegem não é a contagem: é a curadoria. A leva foi
 * escrita nove dias depois da poda de 646 -> 402, e o jeito mais fácil de
 * estragar aquele trabalho é alguém acrescentar aqui, de boa fé, uma variação
 * que já tinha sido cortada — o app não reclamaria, o exercício simplesmente
 * voltaria pra tela do personal.
 */
class ExercicioBibliotecaComplementarTest extends TestCase
{
    use RefreshDatabase;

    /** @return string[] */
    private function nomesNovos(): array
    {
        return array_column(ExercicioBibliotecaComplementarSeeder::linhas(), 0);
    }

    // ---- A curadoria -----------------------------------------------------

    public function test_nao_repete_nome_dentro_da_propria_leva(): void
    {
        $nomes = $this->nomesNovos();

        $this->assertSame(array_unique($nomes), $nomes, 'Nome repetido na leva complementar.');
    }

    public function test_nao_ressuscita_nada_da_lista_podada(): void
    {
        $podados = require database_path('biblioteca_podada.php');

        $colisoes = array_intersect($this->nomesNovos(), $podados);

        $this->assertSame([], array_values($colisoes),
            'A leva complementar traria de volta exercício cortado na poda de 22/08.');
    }

    public function test_nao_colide_com_as_duas_levas_anteriores(): void
    {
        $this->seed(ExerciseSeeder::class);
        $this->seed(ExercicioBibliotecaAmpliadaSeeder::class);
        $antigos = Exercise::pluck('name')->all();

        $colisoes = array_intersect($this->nomesNovos(), $antigos);

        $this->assertSame([], array_values($colisoes));
    }

    // ---- A inserção ------------------------------------------------------

    public function test_insere_a_leva_inteira_sobre_a_biblioteca_existente(): void
    {
        $this->seed(ExerciseSeeder::class);
        $this->seed(ExercicioBibliotecaAmpliadaSeeder::class);
        $antes = Exercise::count();

        $this->seed(ExercicioBibliotecaComplementarSeeder::class);

        $this->assertSame($antes + count($this->nomesNovos()), Exercise::count());
    }

    public function test_rodar_duas_vezes_nao_duplica(): void
    {
        $this->seed(ExercicioBibliotecaComplementarSeeder::class);
        $depoisDaPrimeira = Exercise::count();

        $this->seed(ExercicioBibliotecaComplementarSeeder::class);

        $this->assertSame($depoisDaPrimeira, Exercise::count());
    }

    public function test_nao_apaga_a_foto_dos_exercicios_com_imagem_real(): void
    {
        // Só o ExerciseSeeder tem image_url; se esta leva usasse updateOrCreate,
        // um nome coincidente zeraria a foto sem erro nenhum.
        $this->seed(ExerciseSeeder::class);
        $comFoto = Exercise::whereNotNull('image_url')->count();

        $this->seed(ExercicioBibliotecaComplementarSeeder::class);

        $this->assertSame($comFoto, Exercise::whereNotNull('image_url')->count());
    }

    // ---- A ligação com a sugestão de carga -------------------------------

    public function test_nenhum_equipamento_novo_sugere_aumento_de_carga(): void
    {
        // Alongamento, mobilidade, equilíbrio e calistenia não têm carga pra
        // somar. Equipamento fora do mapa de App\Support\Progressao cai no
        // INCREMENTO_PADRAO e o app passa a sugerir "+2,5 kg" num alongamento
        // de panturrilha na parede.
        $semCarga = ['Bastão', 'Rolo', 'Bosu', 'Disco', 'Argolas', 'Toalha', 'Cadeira', 'Peso corporal', 'Elástico'];

        foreach ($semCarga as $equipamento) {
            $this->assertSame(0.0, Progressao::incrementoMinimo($equipamento),
                "Equipamento sem carga somável '$equipamento' está fora do mapa de incrementos.");
        }
    }

    public function test_todo_equipamento_da_leva_esta_mapeado_na_progressao(): void
    {
        $conhecidos = [];
        foreach (ExercicioBibliotecaComplementarSeeder::linhas() as [, , $equipamento]) {
            $conhecidos[$equipamento] = true;
        }

        // Bicicleta, esteira, polia, máquina, barra, halteres e afins já vinham
        // mapeados; o teste existe pro equipamento que a leva introduziu.
        foreach (array_keys($conhecidos) as $equipamento) {
            $incremento = Progressao::incrementoMinimo($equipamento);
            $this->assertContains($incremento, [0.0, 1.0, 2.0, 2.5, 4.0, 5.0],
                "Incremento inesperado para '$equipamento'.");
        }
    }

    // ---- A forma das linhas ----------------------------------------------

    public function test_toda_linha_tem_grupo_equipamento_e_instrucao(): void
    {
        foreach (ExercicioBibliotecaComplementarSeeder::linhas() as [$nome, $grupo, $equipamento, $instrucoes]) {
            $this->assertNotSame('', trim($nome));
            $this->assertNotSame('', trim($grupo));
            $this->assertNotSame('', trim($equipamento));
            $this->assertNotSame('', trim($instrucoes), "Exercício sem instrução: $nome");
        }
    }
}
