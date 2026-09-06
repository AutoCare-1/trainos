<?php

namespace Database\Seeders;

use App\Models\Food;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carrega a TACO (NEPA/UNICAMP, 4a ed.) em foods — ver database/alimentos_taco.php.
 *
 * Reexecutável: usa o código da TACO como chave, então rodar de novo atualiza
 * em vez de duplicar (e preserva o vínculo com o que os alunos já registraram).
 */
class AlimentoTacoSeeder extends Seeder
{
    public function run(): void
    {
        $alimentos = require database_path('alimentos_taco.php');

        $existentes = Food::pluck('id', 'codigo_taco');
        $agora = now();

        // upsert em lote: 597 inserts um a um deixam a suíte de testes lenta
        // sem necessidade, já que cada teste que precisa da tabela semeia tudo.
        $linhas = array_map(fn (array $a) => [
            'id' => $existentes[$a['codigo']] ?? (string) Str::uuid(),
            'codigo_taco' => $a['codigo'],
            'categoria' => $a['categoria'],
            'nome' => $a['nome'],
            'kcal' => $a['kcal'],
            'proteina_g' => $a['proteina_g'],
            'carboidrato_g' => $a['carboidrato_g'],
            'lipideos_g' => $a['lipideos_g'],
            'fibra_g' => $a['fibra_g'],
            'created_at' => $agora,
        ], $alimentos);

        foreach (array_chunk($linhas, 200) as $lote) {
            DB::table('foods')->upsert(
                $lote,
                ['codigo_taco'],
                ['categoria', 'nome', 'kcal', 'proteina_g', 'carboidrato_g', 'lipideos_g', 'fibra_g']
            );
        }
    }
}
