<?php

namespace App\Http\Controllers;

use App\Models\BodyMeasurement;
use App\Models\Food;
use App\Models\FoodMeasure;
use App\Models\HydrationLog;
use App\Models\MealLog;
use App\Models\MealLogItem;
use App\Models\NutritionSuggestion;
use App\Models\Student;
use App\Support\ErrorReporting;
use App\Support\KillSwitchIa;
use App\Support\Nutricao;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Diário alimentar, água e orientação pré/pós-treino — lado do aluno.
 *
 * O aluno REGISTRA e PERGUNTA; ninguém prescreve nada. Ver a migration de
 * meal_logs e App\Support\Nutricao pra o porquê dessa fronteira.
 */
class PortalNutricaoController extends Controller
{
    // GET /:token/nutricao — o dia de hoje do aluno
    public function index(Request $request): JsonResponse
    {
        $student = $this->alunoDoPortal($request);
        // Só hoje. O histórico continua existindo e o personal continua vendo
        // — a tela do aluno é um ritual diário, não um arquivo.
        $data = now()->toDateString();

        $refeicoes = MealLog::where('student_id', $student->id)
            ->whereDate('data', $data)
            ->with('itens.food:id,nome,categoria')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MealLog $r) => $this->formatarRefeicao($r));

        $agua = HydrationLog::where('student_id', $student->id)->whereDate('data', $data)->value('ml') ?? 0;

        return response()->json([
            'data' => $data,
            'refeicoes' => $refeicoes,
            'agua_ml' => (int) $agua,
            // A meta vem do servidor pra regra morar num lugar só — o
            // frontend só desenha o que recebe.
            'agua_meta_ml' => HydrationLog::metaDiariaMl($this->pesoAtual($student)),
            // O frontend precisa saber se a meta é do aluno ou o padrão, pra
            // não dizer "referência pro seu peso" a quem nunca foi pesado.
            'agua_meta_do_peso' => $this->pesoAtual($student) !== null,
        ]);
    }

    /**
     * Gramas de um item, vindo da medida caseira quando o aluno escolheu uma.
     *
     * A conversão mora aqui e não no cliente porque quem sabe quanto pesa uma
     * concha é o servidor — mandar o cliente calcular abriria espaço pra
     * qualquer número entrar no registro.
     *
     * @param  array<string, mixed>  $item
     */
    private function gramasDoItem(array $item): ?int
    {
        if (! empty($item['medida_id'])) {
            $medida = FoodMeasure::find($item['medida_id']);
            if ($medida && $medida->food_id === $item['food_id']) {
                return (int) round($medida->gramas * (float) ($item['medida_qtd'] ?? 1));
            }
        }

        return $item['quantidade_g'] ?? null;
    }

    /** "2 conchas" pro aluno reler depois — a grama sozinha não diz nada a ele. */
    private function rotuloDaMedida(array $item): ?string
    {
        if (empty($item['medida_id'])) {
            return null;
        }
        $medida = FoodMeasure::find($item['medida_id']);
        if (! $medida || $medida->food_id !== $item['food_id']) {
            return null;
        }
        $qtd = (float) ($item['medida_qtd'] ?? 1);
        $quantia = $qtd == (int) $qtd ? (string) (int) $qtd : str_replace('.', ',', (string) $qtd);

        // Plural na primeira palavra: "2 conchas", "2 colheres de sopa" — o
        // "de sopa" não vai pro plural junto. Meia porção fica no singular
        // ("0,5 concha"), como se fala.
        $nome = mb_strtolower($medida->nome);
        if ($qtd > 1) {
            $partes = explode(' ', $nome, 2);
            $partes[0] = match (true) {
                str_ends_with($partes[0], 'r') => $partes[0].'es',      // colher -> colheres
                str_ends_with($partes[0], 'l') => mb_substr($partes[0], 0, -1).'is', // pastel -> pasteis
                str_ends_with($partes[0], 's') => $partes[0],           // já plural
                default => $partes[0].'s',
            };
            $nome = implode(' ', $partes);
        }

        return trim($quantia.' '.$nome);
    }

    /**
     * Formato de uma refeição pro cliente.
     *
     * file_path é caminho de disco e não sai daqui: o cliente só precisa saber
     * SE existe foto, e busca a imagem pelo endpoint autenticado.
     *
     * @return array<string, mixed>
     */
    private function formatarRefeicao(MealLog $refeicao): array
    {
        return [
            ...$refeicao->only(['id', 'momento', 'descricao', 'created_at']),
            'tem_foto' => $refeicao->file_path !== null,
            'alimentos' => $refeicao->itens->map(fn (MealLogItem $i) => [
                'id' => $i->id,
                'nome' => $i->food->nome,
                'categoria' => $i->food->categoria,
                'quantidade_g' => $i->quantidade_g,
                'medida' => $i->medida_nome,
            ])->values(),
        ];
    }

    /**
     * Peso mais recente conhecido do aluno.
     *
     * A pesagem que o personal registra vai pra body_measurements e NÃO mexe
     * em students.weight_kg (que é só o que foi digitado no cadastro, e
     * costuma estar vazio). Usar a medição mais nova é o que faz a meta
     * acompanhar o aluno em vez de congelar no dia do cadastro.
     */
    private function pesoAtual(Student $student): ?float
    {
        $daMedicao = BodyMeasurement::where('student_id', $student->id)
            ->orderByDesc('recorded_at')
            ->value('weight_kg');

        $peso = $daMedicao ?? $student->weight_kg;

        return $peso ? (float) $peso : null;
    }

    /**
     * GET /:token/nutricao/alimentos?busca=fran — busca na tabela TACO.
     *
     * Sem o termo devolve os mais comuns por categoria, pra a tela ter o que
     * mostrar antes de o aluno digitar qualquer coisa.
     */
    public function buscarAlimentos(Request $request): JsonResponse
    {
        $this->alunoDoPortal($request);

        $busca = trim((string) $request->query('busca', ''));

        $query = Food::query()->orderBy('nome');

        if ($busca !== '') {
            // Uma palavra por vez: quem digita "frango grelhado" espera achar
            // "Frango, peito, grelhado", que não contém a frase inteira.
            foreach (preg_split('/\s+/', $busca) as $termo) {
                if ($termo === '') {
                    continue;
                }
                $query->where('nome', 'like', '%'.$termo.'%');
            }
        }

        return response()->json([
            'alimentos' => $query->with('medidas:id,food_id,nome,gramas')
                ->limit(40)
                ->get(['id', 'nome', 'categoria', 'kcal', 'proteina_g', 'carboidrato_g', 'lipideos_g'])
                ->map(fn (Food $f) => [
                    ...$f->only(['id', 'nome', 'categoria', 'kcal', 'proteina_g', 'carboidrato_g', 'lipideos_g']),
                    // Pode vir vazio: nem todo alimento tem medida caseira, e
                    // a tela cai no campo de gramas nesses casos.
                    'medidas' => $f->medidas->map(fn ($m) => $m->only(['id', 'nome', 'gramas']))->values(),
                ]),
        ]);
    }

    // POST /:token/nutricao/refeicoes — registra uma refeição (foto e/ou texto)
    public function registrarRefeicao(Request $request): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            'momento' => ['required', 'string', Rule::in(MealLog::MOMENTOS)],
            'descricao' => ['nullable', 'string', 'max:500'],
            'foto' => ['nullable', 'image', 'max:8192'],
            'alimentos' => ['nullable', 'array', 'max:20'],
            'alimentos.*.food_id' => ['required', 'string', 'exists:foods,id'],
            'alimentos.*.quantidade_g' => ['nullable', 'integer', 'min:1', 'max:'.MealLogItem::MAX_QUANTIDADE_G],
            // Medida caseira escolhida ("2 conchas"): o servidor converte pra
            // grama, porque quem sabe quanto pesa cada medida é ele.
            'alimentos.*.medida_id' => ['nullable', 'string', 'exists:food_measures,id'],
            'alimentos.*.medida_qtd' => ['nullable', 'numeric', 'min:0.25', 'max:20'],
        ]);

        $descricao = trim($validated['descricao'] ?? '') ?: null;
        $alimentos = $validated['alimentos'] ?? [];
        // Registro sem alimento, sem foto E sem texto não diz nada a ninguém —
        // nem pro aluno que vai reler depois, nem pro personal que acompanha.
        if ($alimentos === [] && ! $request->hasFile('foto') && $descricao === null) {
            return response()->json(['error' => 'Escolha um alimento, manda uma foto ou escreve o que você comeu.'], 422);
        }

        $filePath = $request->hasFile('foto')
            ? Uploads::storePrivate($request->file('foto'), 'refeicoes', (string) $request->route('token'))
            : null;

        // Sempre hoje, e o cliente não escolhe: deixar registrar dias atrás
        // permitia preencher a semana inteira no domingo, de memória. Isso é
        // histórico inventado — e o personal olha esse histórico pra decidir
        // coisa, então dado inventado é pior que dado nenhum.
        // Transação: refeição sem os alimentos que o aluno escolheu seria um
        // registro pela metade, e ele não teria como perceber que faltou.
        $refeicao = DB::transaction(function () use ($student, $validated, $filePath, $descricao, $alimentos) {
            $refeicao = MealLog::create([
                'student_id' => $student->id,
                'data' => now()->toDateString(),
                'momento' => $validated['momento'],
                'file_path' => $filePath,
                'descricao' => $descricao,
            ]);

            foreach ($alimentos as $item) {
                $refeicao->itens()->create([
                    'food_id' => $item['food_id'],
                    'quantidade_g' => $this->gramasDoItem($item),
                    'medida_nome' => $this->rotuloDaMedida($item),
                ]);
            }

            return $refeicao->refresh();
        });

        return response()->json(['refeicao' => $this->formatarRefeicao($refeicao->load('itens.food'))], 201);
    }

    // GET /:token/nutricao/refeicoes/{id}/imagem — foto da refeição
    public function imagem(Request $request, string $token, string $id): JsonResponse|BinaryFileResponse
    {
        $student = $this->alunoDoPortal($request);

        $refeicao = MealLog::where('id', $id)->where('student_id', $student->id)->first();
        if (! $refeicao || ! $refeicao->file_path) {
            return response()->json(['error' => 'Foto não encontrada'], 404);
        }

        return response()->file(Uploads::privateAbsolutePath($refeicao->file_path));
    }

    // DELETE /:token/nutricao/refeicoes/{id} — o aluno apaga o próprio registro
    public function removerRefeicao(Request $request, string $token, string $id): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $refeicao = MealLog::where('id', $id)->where('student_id', $student->id)->first();
        if (! $refeicao) {
            return response()->json(['error' => 'Registro não encontrado'], 404);
        }

        if ($refeicao->file_path) {
            Uploads::deletePrivateQuietly($refeicao->file_path);
        }
        $refeicao->delete();

        return response()->json(['ok' => true]);
    }

    // POST /:token/nutricao/agua — soma (ou tira) um copo/garrafa do dia
    public function registrarAgua(Request $request): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            // O cliente diz QUE recipiente foi, não quantos ml: quem define o
            // volume de cada um é o servidor (HydrationLog::VOLUMES), senão
            // qualquer número entraria no registro do aluno.
            'recipiente' => ['required', 'string', Rule::in(array_keys(HydrationLog::VOLUMES))],
            'sinal' => ['required', 'integer', 'in:-1,1'],
        ]);

        // Mesma razão do registro de refeição: só o dia de hoje.
        $data = now()->toDateString();

        // whereDate e não firstOrCreate: o cast 'date' grava "Y-m-d 00:00:00"
        // no SQLite, então a igualdade exata de firstOrCreate nunca casava com
        // a string "Y-m-d" — ele tentava inserir de novo e batia no unique.
        // Mesma pegadinha que já tinha mordido a Agenda; whereDate normaliza
        // nos dois bancos.
        $log = HydrationLog::where('student_id', $student->id)->whereDate('data', $data)->first()
            ?? new HydrationLog(['student_id' => $student->id, 'data' => $data, 'ml' => 0]);

        $delta = HydrationLog::VOLUMES[$validated['recipiente']] * $validated['sinal'];

        // Preso entre 0 e o teto: acima disso é toque repetido sem querer, e
        // abaixo de zero não existe.
        $novo = max(0, min(HydrationLog::MAX_ML, (int) $log->ml + $delta));
        $log->ml = $novo;
        $log->save();

        return response()->json(['agua_ml' => $novo]);
    }

    // GET /:token/nutricao/sugestoes — histórico do que a IA já orientou
    public function sugestoes(Request $request): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        return response()->json([
            'sugestoes' => NutritionSuggestion::where('student_id', $student->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'momento', 'resposta', 'encaminhou_nutricionista', 'created_at']),
        ]);
    }

    // POST /:token/nutricao/sugestoes — aluno pede orientação de pré/pós-treino
    public function pedirSugestao(Request $request): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            'momento' => ['required', 'string', Rule::in(NutritionSuggestion::MOMENTOS)],
        ]);

        if ($resposta = KillSwitchIa::verificar('nutricao_sugestao', $student->professional_id)) {
            return $resposta;
        }

        try {
            $resultado = Nutricao::sugerir($validated['momento'], $student);
        } catch (\Throwable $e) {
            ErrorReporting::capturarFalhaIa('nutricao_sugestao', $e, ['student_id' => $student->id]);

            return response()->json([
                'error' => 'Não consegui responder agora. Tenta de novo daqui a pouco.',
            ], 502);
        }

        $sugestao = NutritionSuggestion::create([
            'student_id' => $student->id,
            'momento' => $validated['momento'],
            'resposta' => $resultado['resposta'],
            'encaminhou_nutricionista' => $resultado['encaminhou'],
        ])->refresh();

        return response()->json(['sugestao' => $sugestao], 201);
    }
}
