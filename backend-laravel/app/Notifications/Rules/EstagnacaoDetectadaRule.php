<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use App\Support\Estagnacao;

/**
 * Usa App\Support\Estagnacao — mesma fonte de verdade do alerta in-app
 * (AlunoController::contarEstagnacaoPorAluno / alertasEstagnacao), pra nunca
 * ter um veredito de estagnação divergente entre push e in-app pro mesmo
 * aluno/exercício (item 5/6 de revisão externa). dedup inclui o id da última
 * sessão: se o aluno treinar de novo e continuar estagnado, isso conta como um
 * novo evento (sessão nova), não reenvio do mesmo. Texto do payload é
 * genérico (item 9 da revisão) — não expõe qual exercício nem detalhe de
 * performance na tela de bloqueio; o nome do exercício continua visível
 * normalmente dentro do app, só não sai no push.
 */
class EstagnacaoDetectadaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'estagnacao_detectada';
    }

    public function avaliar(): array
    {
        $ontem = now()->subDay();

        $estagnados = collect(Estagnacao::compararUltimasSessoes())
            ->filter(fn ($c) => $c['ultima'] <= $c['anterior'] && $c['finished_at'] >= $ontem);

        if ($estagnados->isEmpty()) {
            return [];
        }

        $alunos = Student::whereIn('id', $estagnados->pluck('student_id')->unique())->get()->keyBy('id');

        return $estagnados->map(function ($c) use ($alunos) {
            $student = $alunos[$c['student_id']];

            return new NotificacaoCandidato(
                recipient: $student,
                professionalId: $student->professional_id,
                studentId: $student->id,
                dedupKey: "estagnacao_detectada:{$c['student_id']}:{$c['exercise_id']}:{$c['session_id']}",
                contexto: $c['exercise_id'],
                titulo: NotificacaoCandidato::TITULO_ALUNO_GENERICO,
                corpo: NotificacaoCandidato::CORPO_ALUNO_GENERICO,
                url: "/aluno/{$student->invite_token}",
            );
        })->all();
    }
}
