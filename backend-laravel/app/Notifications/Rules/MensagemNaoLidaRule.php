<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class MensagemNaoLidaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'mensagem_nao_lida';
    }

    public function avaliar(): array
    {
        $limite = now()->subHours(config('notificacoes.horas_mensagem_nao_lida'));

        $rows = DB::table('messages as m')
            ->join('students as s', 's.id', '=', 'm.student_id')
            ->whereIn('m.sender', ['professional', 'ai'])
            ->whereNull('m.read_at')
            ->where('m.created_at', '<=', $limite)
            ->select('m.id as mensagem_id', 's.id as student_id', 's.professional_id', 's.invite_token')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "mensagem_nao_lida:{$r->mensagem_id}",
            contexto: $r->mensagem_id,
            titulo: 'Você tem uma mensagem',
            corpo: 'Tem uma mensagem sua esperando resposta no chat.',
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
