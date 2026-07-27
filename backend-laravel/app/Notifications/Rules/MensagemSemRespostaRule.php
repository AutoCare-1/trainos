<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/**
 * Definida como "sem resposta", não "sem lida" — evita depender de um read_at do
 * lado do personal, que hoje não existe (o painel do personal pra ler mensagens do
 * aluno ainda não foi portado pro Laravel, gap de paridade pré-existente e fora de
 * escopo). Checa se a ÚLTIMA mensagem da conversa é do aluno e está velha o
 * bastante sem nenhuma resposta depois dela.
 */
class MensagemSemRespostaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'mensagem_sem_resposta';
    }

    public function avaliar(): array
    {
        $limite = now()->subHours(config('notificacoes.horas_mensagem_sem_resposta'));
        $hoje = now()->toDateString();

        $rows = DB::table('students as s')
            ->select('s.id', 's.name', 's.professional_id')
            ->selectRaw('(select max(created_at) from messages m where m.student_id = s.id) as ultima_geral')
            ->selectRaw("(select max(created_at) from messages m where m.student_id = s.id and m.sender = 'student') as ultima_aluno")
            ->havingRaw('ultima_aluno is not null and ultima_aluno = ultima_geral and ultima_aluno <= ?', [$limite])
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "mensagem_sem_resposta:{$r->id}:{$hoje}",
            contexto: null,
            // Item 9 da revisão: nome do aluno não deve ir na tela de bloqueio.
            titulo: NotificacaoCandidato::TITULO_PERSONAL_GENERICO,
            corpo: NotificacaoCandidato::CORPO_PERSONAL_GENERICO,
            url: "/alunos/{$r->id}",
        ))->all();
    }
}
