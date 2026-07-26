<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** session_entries.is_pr já é calculado em PortalController::registrarSerie — aqui só reaproveita pra disparar o push. */
class NovoRecordePessoalRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'novo_recorde_pessoal';
    }

    public function avaliar(): array
    {
        $rows = DB::table('session_entries as se')
            ->join('training_sessions as ts', 'ts.id', '=', 'se.training_session_id')
            ->join('students as s', 's.id', '=', 'ts.student_id')
            ->join('workout_exercises as we', 'we.id', '=', 'se.workout_exercise_id')
            ->join('exercises as e', 'e.id', '=', 'we.exercise_id')
            ->where('se.is_pr', true)
            ->where('se.created_at', '>=', now()->subDay())
            ->select('se.id as entry_id', 's.id as student_id', 's.professional_id', 's.invite_token', 'e.name as exercicio', 'se.load_kg_done')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "novo_recorde_pessoal:{$r->entry_id}",
            contexto: $r->entry_id,
            titulo: 'Novo recorde pessoal!',
            corpo: "Você bateu seu recorde em {$r->exercicio}: {$r->load_kg_done}kg.",
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
