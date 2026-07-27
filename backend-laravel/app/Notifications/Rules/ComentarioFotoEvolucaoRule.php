<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Item 13 de uma revisão externa: feature de comentário da Coach IA na foto
 * de evolução já existe (PortalBodyPhotoController::store), mas nunca gerava
 * push. ai_feedback é preenchido de forma síncrona no mesmo request do
 * upload — o aluno já vê o comentário na hora, então o push aqui serve como
 * lembrete assíncrono (ex: aluno fechou o app antes de ler, ou quer ver de
 * novo depois no celular), não como o único jeito de saber. Texto genérico
 * (item 9) — comentário sobre foto do próprio corpo não deve aparecer na tela
 * de bloqueio.
 */
class ComentarioFotoEvolucaoRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'comentario_foto_evolucao';
    }

    public function avaliar(): array
    {
        $rows = DB::table('body_photos as bp')
            ->join('students as s', 's.id', '=', 'bp.student_id')
            ->whereNotNull('bp.ai_feedback')
            ->where('bp.created_at', '>=', now()->subDay())
            ->select('bp.id as foto_id', 's.id as student_id', 's.professional_id', 's.invite_token')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "comentario_foto_evolucao:{$r->foto_id}",
            contexto: $r->foto_id,
            titulo: NotificacaoCandidato::TITULO_ALUNO_GENERICO,
            corpo: NotificacaoCandidato::CORPO_ALUNO_GENERICO,
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
