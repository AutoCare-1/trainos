<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class DesafioTerminandoRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'desafio_terminando';
    }

    public function avaliar(): array
    {
        $limite = now()->addHours(config('notificacoes.horas_desafio_terminando'))->toDateString();
        $hoje = now()->toDateString();

        $rows = DB::table('challenges as c')
            ->join('challenge_participants as cp', 'cp.challenge_id', '=', 'c.id')
            ->join('students as s', 's.id', '=', 'cp.student_id')
            ->where('c.end_date', '>=', $hoje)
            ->where('c.end_date', '<=', $limite)
            ->select('c.id as challenge_id', 'c.name as desafio', 'c.end_date', 's.id as student_id', 's.professional_id', 's.invite_token')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "desafio_terminando:{$r->challenge_id}:{$r->student_id}",
            contexto: $r->challenge_id,
            titulo: 'Seu desafio está terminando',
            corpo: "O desafio \"{$r->desafio}\" termina em breve. Aproveite pra treinar mais uma vez antes do fim.",
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
