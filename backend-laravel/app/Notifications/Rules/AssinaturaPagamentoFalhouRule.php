<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/**
 * Avisa o personal quando a cobrança recorrente da assinatura dele com o
 * TrainOS falha (cartão recusado etc.) — dedup por atraso_desde: dispara uma
 * vez por episódio de atraso, não todo dia enquanto durar a carência (isso é
 * o que AssinaturaBloqueadaRule cobre, quando a carência esgota de vez).
 */
class AssinaturaPagamentoFalhouRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'assinatura_pagamento_falhou';
    }

    public function avaliar(): array
    {
        $rows = DB::table('professional_subscriptions as ps')
            ->where('ps.status', 'atrasada')
            ->whereNotNull('ps.atraso_desde')
            ->select('ps.id', 'ps.professional_id', 'ps.atraso_desde')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: null,
            dedupKey: "assinatura_pagamento_falhou:{$r->id}:{$r->atraso_desde}",
            contexto: $r->id,
            titulo: 'Pagamento da assinatura não aprovado',
            corpo: 'Não conseguimos confirmar o pagamento da sua assinatura do TrainOS. Regularize em "Meu Plano" antes que o cadastro de novos alunos seja bloqueado.',
            url: '/plano',
        ))->all();
    }
}
