<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/** Item 13 de uma revisão externa: baixa prioridade, reaproveita a mesma infra. Texto genérico (item 9) — sem nome do aluno na tela de bloqueio. */
class AlunoCadastradoRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'aluno_cadastrado';
    }

    public function avaliar(): array
    {
        $rows = DB::table('students')
            ->where('created_at', '>=', now()->subDay())
            ->select('id', 'professional_id')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "aluno_cadastrado:{$r->id}",
            contexto: null,
            titulo: NotificacaoCandidato::TITULO_PERSONAL_GENERICO,
            corpo: NotificacaoCandidato::CORPO_PERSONAL_GENERICO,
            url: "/alunos/{$r->id}",
        ))->all();
    }
}
