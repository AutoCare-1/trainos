<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Item 4 da revisão: o dedup já não era diário (a versão anterior usava
 * "ano-mês" na chave, então no pior caso reenviava 1x/mês — nunca "todo santo
 * dia" como a revisão descreveu). Mesmo assim, dedup por mês-calendário tem uma
 * irregularidade real: se o aluno cruza o limiar no dia 28, o próximo aviso
 * podia vir só 3 dias depois (dia 1 do mês seguinte). Troquei por uma janela
 * rolante de N dias (config `dias_lembrete_avaliacao_pendente`) contada a
 * partir do dia em que cruzou o limiar, não do calendário — sem depender de
 * consultar o notification_logs de dentro da regra (mantém a regra
 * autocontida, só calcula um "bucket" pra chave de dedup).
 */
class AvaliacaoPendenteRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'avaliacao_pendente';
    }

    public function avaliar(): array
    {
        $diasLimiar = config('notificacoes.dias_avaliacao_pendente');
        $intervaloReenvio = config('notificacoes.dias_lembrete_avaliacao_pendente');

        // Limiar calculado em PHP/Carbon, não com datediff() do MySQL — mesma
        // cautela de portabilidade já usada em NegocioController/AlunoController
        // (datediff não existe no SQLite, usado nos testes).
        $limite = now()->subDays($diasLimiar)->toDateString();

        $rows = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->selectRaw('coalesce((select max(bm.recorded_at) from body_measurements bm where bm.student_id = s.id), s.created_at) as referencia')
            ->whereRaw(
                'date(coalesce((select max(bm.recorded_at) from body_measurements bm where bm.student_id = s.id), s.created_at)) <= ?',
                [$limite]
            )
            ->get();

        return $rows->map(function ($r) use ($diasLimiar, $intervaloReenvio) {
            $diasSemAvaliar = (int) now()->startOfDay()->diffInDays(Carbon::parse($r->referencia)->startOfDay());
            $bucket = intdiv($diasSemAvaliar - $diasLimiar, $intervaloReenvio);

            return new NotificacaoCandidato(
                recipient: Student::find($r->id),
                professionalId: $r->professional_id,
                studentId: $r->id,
                dedupKey: "avaliacao_pendente:{$r->id}:{$bucket}",
                contexto: null,
                // Item 9 da revisão: não expõe "avaliação"/medidas corporais na tela de bloqueio.
                titulo: NotificacaoCandidato::TITULO_ALUNO_GENERICO,
                corpo: NotificacaoCandidato::CORPO_ALUNO_GENERICO,
                url: "/aluno/{$r->invite_token}",
            );
        })->all();
    }
}
