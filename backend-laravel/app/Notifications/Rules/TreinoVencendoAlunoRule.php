<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Avisa o aluno quando um treino com validade definida (duration_weeks, ver
 * TreinoController::enviar) está a poucos dias de vencer — janela configurável
 * (padrão 7 dias), mesmo padrão de DesafioTerminandoRule. Não confundir "vencer"
 * com "arquivar": vencer só dispara o aviso, quem decide tirar o treino da
 * lista de escolha do aluno é o personal, manualmente.
 */
class TreinoVencendoAlunoRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'treino_vencendo_aluno';
    }

    public function avaliar(): array
    {
        $hoje = now()->toDateString();
        $limite = now()->addDays(config('notificacoes.dias_aviso_treino_vencendo'))->toDateString();

        $rows = DB::table('workouts as w')
            ->join('students as s', 's.id', '=', 'w.student_id')
            ->where('w.status', 'sent')
            ->whereNull('w.archived_at')
            ->whereNotNull('w.expires_at')
            ->where('w.expires_at', '>=', $hoje)
            ->where('w.expires_at', '<=', $limite)
            ->select('w.id as workout_id', 'w.name as treino', 'w.expires_at', 's.id as student_id', 's.professional_id', 's.invite_token')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "treino_vencendo_aluno:{$r->workout_id}",
            contexto: $r->workout_id,
            titulo: 'Seu treino está vencendo',
            corpo: "O treino \"{$r->treino}\" vence em breve. Fale com seu professor sobre o próximo treino.",
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
