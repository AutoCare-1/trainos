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

        $this->semearMedidasCaseiras();
    }

    /**
     * Medidas caseiras (IBGE/POF) dos alimentos que têm — ver
     * database/medidas_caseiras.php. Roda depois dos alimentos porque depende
     * do id deles.
     */
    private function semearMedidasCaseiras(): void
    {
        $porCodigo = Food::pluck('id', 'codigo_taco');
        $existentes = DB::table('food_measures')->pluck('id', DB::raw("concat(food_id, '|', nome)"));
        $agora = now();

        $linhas = [];
        foreach (require database_path('medidas_caseiras.php') as $codigo => $dados) {
            $foodId = $porCodigo[$codigo] ?? null;
            if (! $foodId) {
                continue;
            }
            foreach ($dados['medidas'] as $medida) {
                $linhas[] = [
                    'id' => $existentes[$foodId.'|'.$medida['nome']] ?? (string) Str::uuid(),
                    'food_id' => $foodId,
                    'nome' => $medida['nome'],
                    'gramas' => $medida['gramas'],
                    'created_at' => $agora,
                ];
            }
        }

        foreach (array_chunk($linhas, 200) as $lote) {
            DB::table('food_measures')->upsert($lote, ['food_id', 'nome'], ['gramas']);
        }
    }
}
