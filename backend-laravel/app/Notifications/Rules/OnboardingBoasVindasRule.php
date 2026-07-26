<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Sequência curta pra aluno novo formar o hábito: dia 1 é boas-vindas pra todo
 * mundo; dia 3 e dia 7 só disparam se o aluno ainda estiver no sinal de risco já
 * usado no dashboard "Meu Negócio" (menos de 3 treinos concluídos) — quem já
 * engajou não precisa do empurrão.
 */
class OnboardingBoasVindasRule implements NotificacaoRule
{
    private const TREINOS_MINIMOS = 3;

    private const MENSAGENS = [
        1 => ['Bem-vindo(a)!', 'Seu treino já está pronto pra começar. Qualquer dúvida, é só chamar no chat.'],
        3 => ['Como estão os primeiros dias?', 'Ainda dá tempo de começar essa semana com o pé direito.'],
        7 => ['Uma semana desde o cadastro', 'Se algo travou pra começar, seu professor pode ajustar o treino com você.'],
    ];

    public function chave(): string
    {
        return 'onboarding_boas_vindas';
    }

    public function avaliar(): array
    {
        $hoje = now()->toDateString();
        $candidatos = [];

        foreach ([1, 3, 7] as $dia) {
            // Limiar calculado em PHP/Carbon, não com datediff() do MySQL — mesma
            // cautela de portabilidade já usada em NegocioController/AlunoController
            // (datediff não existe no SQLite, usado nos testes).
            $dataAlvo = now()->subDays($dia)->toDateString();

            $rows = DB::table('students as s')
                ->select('s.id', 's.professional_id', 's.invite_token')
                ->whereRaw('date(s.created_at) = ?', [$dataAlvo])
                ->when($dia > 1, fn ($q) => $q->whereRaw(
                    '(select count(*) from training_sessions ts where ts.student_id = s.id and ts.status = ?) < ?',
                    ['completed', self::TREINOS_MINIMOS]
                ))
                ->get();

            [$titulo, $corpo] = self::MENSAGENS[$dia];

            foreach ($rows as $r) {
                $candidatos[] = new NotificacaoCandidato(
                    recipient: Student::find($r->id),
                    professionalId: $r->professional_id,
                    studentId: $r->id,
                    dedupKey: "onboarding_boas_vindas:{$r->id}:{$dia}",
                    contexto: (string) $dia,
                    titulo: $titulo,
                    corpo: $corpo,
                    url: "/aluno/{$r->invite_token}",
                );
            }
        }

        return $candidatos;
    }
}
