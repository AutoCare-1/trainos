<?php

namespace App\Http\Controllers;

use App\Models\BodyMeasurement;
use App\Models\HydrationLog;
use App\Models\MealLog;
use App\Models\NutritionSuggestion;
use App\Models\Student;
use App\Support\ErrorReporting;
use App\Support\KillSwitchIa;
use App\Support\Nutricao;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    // GET /:token/nutricao?data=YYYY-MM-DD — o dia do aluno
    public function index(Request $request): JsonResponse
    {
        $student = $this->alunoDoPortal($request);
        $data = $request->query('data') ?: now()->toDateString();

        $refeicoes = MealLog::where('student_id', $student->id)
            ->whereDate('data', $data)
            ->orderBy('created_at')
            ->get(['id', 'momento', 'descricao', 'created_at', 'file_path'])
            // file_path é caminho de disco e não sai daqui: o cliente só
            // precisa saber SE existe foto, e busca a imagem pelo endpoint
            // autenticado abaixo.
            ->map(fn (MealLog $r) => [
                ...$r->only(['id', 'momento', 'descricao', 'created_at']),
                'tem_foto' => $r->file_path !== null,
            ]);

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

    // POST /:token/nutricao/refeicoes — registra uma refeição (foto e/ou texto)
    public function registrarRefeicao(Request $request): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            'momento' => ['required', 'string', Rule::in(MealLog::MOMENTOS)],
            'descricao' => ['nullable', 'string', 'max:500'],
            'foto' => ['nullable', 'image', 'max:8192'],
            // Janela curta: registrar refeição no futuro não existe, e muito
            // pra trás é engano de digitação (ou tentativa de forjar histórico
            // pro personal). Uma semana cobre quem sincroniza atrasado.
            'data' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now()->subWeek()->toDateString(), 'before_or_equal:'.now()->toDateString()],
        ]);

        $descricao = trim($validated['descricao'] ?? '') ?: null;
        // Registro sem foto E sem texto não diz nada a ninguém — nem pro aluno
        // que vai reler depois, nem pro personal que acompanha.
        if (! $request->hasFile('foto') && $descricao === null) {
            return response()->json(['error' => 'Manda uma foto ou escreve o que você comeu.'], 422);
        }

        $filePath = $request->hasFile('foto')
            ? Uploads::storePrivate($request->file('foto'), 'refeicoes', (string) $request->route('token'))
            : null;

        $refeicao = MealLog::create([
            'student_id' => $student->id,
            'data' => $validated['data'] ?? now()->toDateString(),
            'momento' => $validated['momento'],
            'file_path' => $filePath,
            'descricao' => $descricao,
        ])->refresh();

        return response()->json([
            'refeicao' => [
                ...$refeicao->only(['id', 'momento', 'descricao', 'created_at']),
                'tem_foto' => $filePath !== null,
            ],
        ], 201);
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
            'data' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now()->subWeek()->toDateString(), 'before_or_equal:'.now()->toDateString()],
        ]);

        $data = $validated['data'] ?? now()->toDateString();

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
