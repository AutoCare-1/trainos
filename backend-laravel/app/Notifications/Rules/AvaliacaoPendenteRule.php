<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** dedup por mês (ver dedupKey) — enquanto ficar pendente, cutuca no máximo 1x/mês, não todo dia. */
class AvaliacaoPendenteRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'avaliacao_pendente';
    }

    public function avaliar(): array
    {
        $mesAtual = now()->format('Y-m');

        // Limiar calculado em PHP/Carbon, não com datediff() do MySQL — mesma
        // cautela de portabilidade já usada em NegocioController/AlunoController
        // (datediff não existe no SQLite, usado nos testes).
        $limite = now()->subDays(config('notificacoes.dias_avaliacao_pendente'))->toDateString();

        $rows = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->whereRaw(
                'date(coalesce((select max(bm.recorded_at) from body_measurements bm where bm.student_id = s.id), s.created_at)) <= ?',
                [$limite]
            )
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "avaliacao_pendente:{$r->id}:{$mesAtual}",
            contexto: null,
            titulo: 'Hora de atualizar sua avaliação',
            corpo: 'Já faz um tempo desde a última medida registrada. Atualizar ajuda a acompanhar sua evolução.',
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
