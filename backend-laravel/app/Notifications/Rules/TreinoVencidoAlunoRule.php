<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Diferente de TreinoVencendoAlunoRule (aviso ANTES do vencimento): esta dispara
 * uma vez quando o treino já passou da data de expiração. Não depende de
 * arquivamento — arquivar continua 100% manual, feito pelo personal.
 */
class TreinoVencidoAlunoRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'treino_vencido_aluno';
    }

    public function avaliar(): array
    {
        $hoje = now()->toDateString();

        $rows = DB::table('workouts as w')
            ->join('students as s', 's.id', '=', 'w.student_id')
            ->where('w.status', 'sent')
            ->whereNull('w.archived_at')
            ->whereNotNull('w.expires_at')
            ->where('w.expires_at', '<', $hoje)
            ->select(
                'w.id as workout_id', 'w.name as treino', 's.id as student_id',
                's.professional_id', 's.invite_token', 's.lembrar_pagamento_vencimento'
            )
            ->get();

        // Item 9 da revisão externa (ver NotificacaoCandidato): lembrete de pagamento
        // é dado financeiro, não deve ir em texto claro numa notificação que pode
        // ser vista por terceiros na tela de bloqueio — cai no texto genérico
        // sempre que essa opção está ligada, mesmo abrindo mão de citar o treino.
        return $rows->map(function ($r) {
            if ($r->lembrar_pagamento_vencimento) {
                $titulo = NotificacaoCandidato::TITULO_ALUNO_GENERICO;
                $corpo = NotificacaoCandidato::CORPO_ALUNO_GENERICO;
            } else {
                $titulo = 'Seu treino venceu';
                $corpo = "O treino \"{$r->treino}\" venceu. Fale com seu professor sobre o próximo treino.";
            }

            return new NotificacaoCandidato(
                recipient: Student::find($r->student_id),
                professionalId: $r->professional_id,
                studentId: $r->student_id,
                dedupKey: "treino_vencido_aluno:{$r->workout_id}",
                contexto: $r->workout_id,
                titulo: $titulo,
                corpo: $corpo,
                url: "/aluno/{$r->invite_token}",
            );
        })->all();
    }
}
