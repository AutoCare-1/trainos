<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Dispara exatamente no dia em que o aluno cruza 3, 7 ou 14 dias sem treinar —
 * tom cresce com o limiar, nunca culpa.
 *
 * MENSAGENS fica guardado (não vai mais pro payload do push — ver item 9 de
 * revisão externa, frequência de treino não deve aparecer na tela de bloqueio
 * de um aparelho que pode ser compartilhado) como o texto rico pra um futuro
 * centro de notificações dentro do próprio app, onde mostrar detalhe não tem
 * o mesmo risco de exposição.
 */
class SemTreinarDiasRule implements NotificacaoRule
{
    private const MENSAGENS = [
        3 => 'Faz alguns dias que você não treina. Sem pressa, mas se puder encaixar hoje, melhor.',
        7 => 'Já faz uma semana sem treino. Sempre dá pra recomeçar de onde parou.',
        14 => 'Já faz duas semanas. Se algo mudou na sua rotina, seu professor pode ajustar o treino com você.',
    ];

    public function chave(): string
    {
        return 'sem_treinar_dias';
    }

    public function avaliar(): array
    {
        $hoje = now()->toDateString();
        $candidatos = [];

        foreach (config('notificacoes.dias_sem_treinar') as $limiar) {
            // Limiar calculado em PHP/Carbon, não com datediff() do MySQL — mesma
            // cautela de portabilidade já usada em NegocioController/AlunoController
            // (datediff não existe no SQLite, usado nos testes).
            $dataAlvo = now()->subDays($limiar)->toDateString();

            $rows = DB::table('students as s')
                ->select('s.id', 's.professional_id', 's.invite_token')
                ->joinSub(
                    DB::table('workouts')->select('student_id', DB::raw('min(sent_at) as sent_at'))
                        ->where('status', 'sent')->groupBy('student_id'),
                    'primeiro_treino',
                    'primeiro_treino.student_id', '=', 's.id'
                )
                ->leftJoinSub(
                    DB::table('training_sessions')->select('student_id', DB::raw('max(finished_at) as finished_at'))
                        ->where('status', 'completed')->groupBy('student_id'),
                    'ultima_sessao',
                    'ultima_sessao.student_id', '=', 's.id'
                )
                ->whereRaw('date(coalesce(ultima_sessao.finished_at, primeiro_treino.sent_at)) = ?', [$dataAlvo])
                ->get();

            foreach ($rows as $r) {
                $student = Student::find($r->id);
                $candidatos[] = new NotificacaoCandidato(
                    recipient: $student,
                    professionalId: $r->professional_id,
                    studentId: $r->id,
                    dedupKey: "sem_treinar_dias:{$r->id}:{$limiar}:{$hoje}",
                    contexto: (string) $limiar,
                    titulo: NotificacaoCandidato::TITULO_ALUNO_GENERICO,
                    corpo: NotificacaoCandidato::CORPO_ALUNO_GENERICO,
                    url: "/aluno/{$r->invite_token}",
                );
            }
        }

        return $candidatos;
    }
}
