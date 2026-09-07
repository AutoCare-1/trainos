<?php

namespace Database\Seeders;

use App\Models\Food;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carrega os alimentos da POF/IBGE — ver database/alimentos_pof.php.
 *
 * Reexecutável: a chave é (codigo_pof, codigo_preparo), então rodar de novo
 * atualiza em vez de duplicar, e preserva o vínculo com o que os alunos já
 * registraram.
 */
class AlimentoPofSeeder extends Seeder
{
    public function run(): void
    {
        // DB::table e não Eloquent: são 1.971 alimentos e cada teste que
        // precisa da tabela semeia tudo de novo. Hidratar model pra isso
        // estourava o limite de memória do PHP no meio da suíte.
        $existentes = DB::table('foods')->get(['id', 'codigo_pof', 'codigo_preparo'])
            ->mapWithKeys(fn ($f) => [$f->codigo_pof.'|'.$f->codigo_preparo => $f->id]);
        $agora = now();
        // Sinônimos entram só no campo de busca — ver database/sinonimos_busca.php.
        $sinonimos = require database_path('sinonimos_busca.php');

        // upsert em lote: 1.971 inserts um a um deixam a suíte lenta sem
        // necessidade, já que cada teste que precisa da tabela semeia tudo.
        $linhas = [];
        foreach (require database_path('alimentos_pof.php') as $a) {
            [$codigo, $preparo, $nome] = $a;
            $linhas[] = [
                'id' => $existentes[$codigo.'|'.$preparo] ?? (string) Str::uuid(),
                'codigo_pof' => $codigo,
                'codigo_preparo' => $preparo,
                'nome' => $nome,
                'nome_busca' => Food::normalizarParaBusca(
                    isset($sinonimos[$codigo]) ? $nome.' '.$sinonimos[$codigo] : $nome
                ),
                'kcal' => $a['kcal'],
                'proteina_g' => $a['proteina_g'],
                'carboidrato_g' => $a['carboidrato_g'],
                'lipideos_g' => $a['lipideos_g'],
                'fibra_g' => $a['fibra_g'],
                'created_at' => $agora,
            ];
        }

        foreach (array_chunk($linhas, 500) as $lote) {
            DB::table('foods')->upsert(
                $lote,
                ['codigo_pof', 'codigo_preparo'],
                ['nome', 'nome_busca', 'kcal', 'proteina_g', 'carboidrato_g', 'lipideos_g', 'fibra_g']
            );
        }

        $this->semearMedidasCaseiras();
    }

    /**
     * Medidas caseiras — ver database/medidas_pof.php. Roda depois dos
     * alimentos porque depende do id deles.
     */
    private function semearMedidasCaseiras(): void
    {
        $porChave = DB::table('foods')->get(['id', 'codigo_pof', 'codigo_preparo'])
            ->mapWithKeys(fn ($f) => [$f->codigo_pof.'|'.$f->codigo_preparo => $f->id]);
        $agora = now();

        // Apaga tudo e regrava, em vez de upsert + poda. O id de uma medida
        // não é guardado em lugar nenhum (a refeição do aluno guarda o RÓTULO
        // e a grama já convertida, em colunas próprias), então recriar não
        // quebra histórico — e resolve de graça o caso que o upsert sozinho
        // não resolve: medida que saiu do arquivo precisa sumir do banco,
        // senão uma medida errada semeada uma vez fica pra sempre. Isso
        // importa agora que o deploy roda os seeders a cada subida.
        DB::table('food_measures')->delete();

        $linhas = [];
        foreach (require database_path('medidas_pof.php') as $chave => $medidas) {
            $foodId = $porChave[$chave] ?? null;
            if (! $foodId) {
                continue;
            }
            foreach ($medidas as [$nome, $gramas]) {
                $linhas[] = [
                    'id' => (string) Str::uuid(),
                    'food_id' => $foodId,
                    'nome' => $nome,
                    'gramas' => $gramas,
                    'created_at' => $agora,
                ];
            }

            if (count($linhas) >= 1000) {
                DB::table('food_measures')->insert($linhas);
                $linhas = [];
            }
        }

        if ($linhas !== []) {
            DB::table('food_measures')->insert($linhas);
        }
    }
}
