<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class MarcoTempoTreinandoRule implements NotificacaoRule
{
    private const MENSAGENS = [
        1 => '1 mês treinando com a gente. Continue assim!',
        3 => '3 meses de treino. Seu progresso já é visível.',
        6 => '6 meses treinando. Meio ano de consistência!',
        12 => '1 ano treinando com a gente. Marco e tanto!',
    ];

    public function chave(): string
    {
        return 'marco_tempo_treinando';
    }

    public function avaliar(): array
    {
        $candidatos = [];

        foreach (config('notificacoes.meses_marco_tempo_treinando') as $meses) {
            // Limiar calculado em PHP/Carbon, não com date_add() do MySQL — mesma
            // cautela de portabilidade já usada em NegocioController/AlunoController
            // (date_add não existe no SQLite, usado nos testes).
            $dataAlvo = now()->subMonths($meses)->toDateString();

            $rows = DB::table('students as s')
                ->whereRaw('date(s.created_at) = ?', [$dataAlvo])
                ->select('s.id', 's.professional_id', 's.invite_token')
                ->get();

            foreach ($rows as $r) {
                $rotulo = $meses === 1 ? '1 mês' : ($meses === 12 ? '1 ano' : "{$meses} meses");
                $candidatos[] = new NotificacaoCandidato(
                    recipient: Student::find($r->id),
                    professionalId: $r->professional_id,
                    studentId: $r->id,
                    dedupKey: "marco_tempo_treinando:{$r->id}:{$meses}",
                    contexto: (string) $meses,
                    titulo: "{$rotulo} de Clube Mais!",
                    corpo: self::MENSAGENS[$meses],
                    url: "/aluno/{$r->invite_token}",
                );
            }
        }

        return $candidatos;
    }
}
