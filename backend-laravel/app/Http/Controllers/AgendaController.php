<?php

namespace App\Http\Controllers;

use App\Models\AgendaOcorrencia;
use App\Models\AgendaSlot;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Agenda semanal do PERSONAL — organização da própria rotina dele (horário
 * fixo + troca pontual numa data específica), não um registro de frequência
 * do aluno na academia (isso já vive em training_sessions/checkins).
 */
class AgendaController extends Controller
{
    // GET /agenda?semana=YYYY-MM-DD — devolve os 7 dias da semana que contém
    // a data informada (hoje, se omitida), com os horários já resolvidos
    // (padrão do slot, ou a exceção daquele dia quando existir).
    public function semana(Request $request): JsonResponse
    {
        $professionalId = $request->user()->id;
        $ancora = $request->query('semana') ? Carbon::parse($request->query('semana')) : now();
        $inicio = $ancora->copy()->startOfWeek(Carbon::SUNDAY);

        $slots = AgendaSlot::where('professional_id', $professionalId)
            ->where('ativo', true)
            ->with('student:id,name')
            ->get()
            ->groupBy('dia_semana');

        $slotIds = AgendaSlot::where('professional_id', $professionalId)->pluck('id');

        // Filtra por slot_id só (não por data em SQL): o cast 'date' do Eloquent
        // grava "Y-m-d 00:00:00" no SQLite (a gramática do driver usa o mesmo
        // formato de datetime pra qualquer coluna de data), então comparar a
        // string "Y-m-d" pura contra a coluna em SQL nunca bate. O volume por
        // personal é pequeno (só existe linha quando ele mexeu numa data),
        // então filtrar em PHP via Carbon (que normaliza certo na leitura) é
        // mais simples e funciona igual em qualquer banco.
        $ocorrencias = AgendaOcorrencia::whereIn('slot_id', $slotIds)
            ->with('student:id,name')
            ->get()
            ->keyBy(fn ($o) => $o->slot_id.'|'.$o->data->toDateString());

        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $data = $inicio->copy()->addDays($i);
            $dataStr = $data->toDateString();

            $horarios = ($slots->get($i) ?? collect())->map(function (AgendaSlot $slot) use ($ocorrencias, $dataStr) {
                $excecao = $ocorrencias->get($slot->id.'|'.$dataStr);

                return [
                    'slot_id' => $slot->id,
                    'hora' => substr($slot->hora, 0, 5),
                    'duracao_minutos' => $slot->duracao_minutos,
                    'student' => $excecao ? $excecao->student : $slot->student,
                    'titulo' => $excecao ? $excecao->titulo : $slot->titulo,
                    'presenca' => $excecao?->presenca,
                    'observacao' => $excecao?->observacao,
                    'eh_excecao' => (bool) $excecao,
                ];
            })->sortBy('hora')->values();

            $dias[] = [
                'data' => $dataStr,
                'dia_semana' => $i,
                'horarios' => $horarios,
            ];
        }

        return response()->json(['inicio_semana' => $inicio->toDateString(), 'dias' => $dias]);
    }

    private function validarAlunoDoPersonal(?string $studentId, string $professionalId): bool
    {
        if ($studentId === null) {
            return true;
        }

        return Student::where('id', $studentId)->where('professional_id', $professionalId)->exists();
    }

    // POST /agenda/horarios — cria um horário fixo semanal
    public function store(Request $request): JsonResponse
    {
        $professionalId = $request->user()->id;
        $validated = $request->validate([
            'student_id' => ['nullable', 'string'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'dia_semana' => ['required', 'integer', 'min:0', 'max:6'],
            'hora' => ['required', 'date_format:H:i'],
            'duracao_minutos' => ['nullable', 'integer', 'min:15', 'max:480'],
        ]);

        if (empty($validated['student_id']) && empty($validated['titulo'])) {
            return response()->json(['error' => 'Escolha um aluno ou dê um título pro horário.'], 422);
        }

        if (! $this->validarAlunoDoPersonal($validated['student_id'] ?? null, $professionalId)) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $slot = AgendaSlot::create([
            'professional_id' => $professionalId,
            'student_id' => $validated['student_id'] ?? null,
            'titulo' => $validated['titulo'] ?? null,
            'dia_semana' => $validated['dia_semana'],
            'hora' => $validated['hora'],
            'duracao_minutos' => $validated['duracao_minutos'] ?? 60,
        ])->refresh();

        return response()->json(['slot' => $slot], 201);
    }

    // PATCH /agenda/horarios/:id — edita ou desativa (ativo: false) um horário fixo
    public function update(Request $request, string $id): JsonResponse
    {
        $professionalId = $request->user()->id;
        $slot = AgendaSlot::where('id', $id)->where('professional_id', $professionalId)->first();
        if (! $slot) {
            return response()->json(['error' => 'Horário não encontrado'], 404);
        }

        $validated = $request->validate([
            'student_id' => ['nullable', 'string'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'dia_semana' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'hora' => ['sometimes', 'date_format:H:i'],
            'duracao_minutos' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $studentId = array_key_exists('student_id', $validated) ? $validated['student_id'] : $slot->student_id;
        $titulo = array_key_exists('titulo', $validated) ? $validated['titulo'] : $slot->titulo;
        if (empty($studentId) && empty($titulo)) {
            return response()->json(['error' => 'Escolha um aluno ou dê um título pro horário.'], 422);
        }

        if (array_key_exists('student_id', $validated) && ! $this->validarAlunoDoPersonal($validated['student_id'], $professionalId)) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $slot->update($validated);

        return response()->json(['slot' => $slot->fresh()]);
    }

    // PUT /agenda/horarios/:id/ocorrencias — upsert da exceção pontual de uma data
    // (substituto, vago, presença/falta) sem mexer no horário fixo do slot.
    public function upsertOcorrencia(Request $request, string $id): JsonResponse
    {
        $professionalId = $request->user()->id;
        $slot = AgendaSlot::where('id', $id)->where('professional_id', $professionalId)->first();
        if (! $slot) {
            return response()->json(['error' => 'Horário não encontrado'], 404);
        }

        $validated = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'student_id' => ['nullable', 'string'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'presenca' => ['nullable', 'string', 'in:presente,falta'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ]);

        if (array_key_exists('student_id', $validated) && ! $this->validarAlunoDoPersonal($validated['student_id'], $professionalId)) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        // Cada chamada manda só o que mudou (ex: só "presenca", ao marcar
        // falta) — precisa preservar o resto da exceção já existente (ou o
        // padrão do slot, se a exceção ainda nem existe), senão marcar falta
        // apagaria o aluno trocado nesse dia, e trocar o aluno apagaria a
        // presença já registrada.
        $existente = AgendaOcorrencia::where('slot_id', $slot->id)->where('data', $validated['data'])->first();

        $dados = [
            'student_id' => array_key_exists('student_id', $validated)
                ? $validated['student_id']
                : ($existente->student_id ?? $slot->student_id),
            'titulo' => array_key_exists('titulo', $validated)
                ? $validated['titulo']
                : ($existente->titulo ?? $slot->titulo),
            'presenca' => array_key_exists('presenca', $validated) ? $validated['presenca'] : $existente?->presenca,
            'observacao' => array_key_exists('observacao', $validated) ? $validated['observacao'] : $existente?->observacao,
        ];

        $ocorrencia = AgendaOcorrencia::updateOrCreate(
            ['slot_id' => $slot->id, 'data' => $validated['data']],
            $dados
        )->refresh();

        return response()->json(['ocorrencia' => $ocorrencia]);
    }
}
