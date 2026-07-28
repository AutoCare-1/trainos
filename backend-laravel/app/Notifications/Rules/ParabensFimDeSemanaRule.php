<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** Toda segunda de manhã, parabeniza quem treinou acima do limiar combinado nos últimos 7 dias. */
class ParabensFimDeSemanaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'parabens_fim_semana';
    }

    public function avaliar(): array
    {
        if (! now()->isMonday() || (int) now()->format('G') < 8) {
            return [];
        }

        $limiar = config('notificacoes.treinos_minimos_parabens_fim_semana');
        $inicio = now()->subDays(7)->startOfDay();
        $fim = now()->subDay()->endOfDay();
        $hoje = now()->toDateString();

        // Filtra em PHP em vez de HAVING sobre a coluna computada: HAVING sem
        // GROUP BY funciona no MySQL (produção) mas quebra no SQLite (suite de
        // testes) com "HAVING clause on a non-aggregate query".
        $rows = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->selectRaw(
                '(select count(*) from training_sessions ts where ts.student_id = s.id and ts.status = ? and ts.finished_at between ? and ?) as total',
                ['completed', $inicio, $fim]
            )
            ->get()
            ->filter(fn ($r) => $r->total >= $limiar);

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "parabens_fim_semana:{$r->id}:{$hoje}",
            contexto: (string) $r->total,
            // Apesar de ser celebração, o corpo original expunha a contagem exata
            // de treinos da semana — mesma classe de dado (frequência de treino)
            // que sem_treinar_* já esconde na tela de bloqueio. Genérico aqui
            // também; o número aparece só depois de abrir o app.
            titulo: NotificacaoCandidato::TITULO_ALUNO_GENERICO,
            corpo: NotificacaoCandidato::CORPO_ALUNO_GENERICO,
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
