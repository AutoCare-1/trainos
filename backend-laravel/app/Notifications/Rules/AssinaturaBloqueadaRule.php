<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/**
 * Avisa o personal quando a carência de pagamento esgota e a assinatura vira
 * "bloqueada" (VerificarCarenciaAssinatura) — só cadastro de aluno novo é
 * travado a partir daqui, o resto do app continua funcionando.
 */
class AssinaturaBloqueadaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'assinatura_bloqueada';
    }

    public function avaliar(): array
    {
        $rows = DB::table('professional_subscriptions as ps')
            ->where('ps.status', 'bloqueada')
            ->select('ps.id', 'ps.professional_id', 'ps.atraso_desde')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: null,
            dedupKey: "assinatura_bloqueada:{$r->id}:{$r->atraso_desde}",
            contexto: $r->id,
            titulo: 'Assinatura suspensa',
            corpo: 'Sua assinatura do TrainOS foi suspensa por falta de pagamento — você não consegue cadastrar alunos novos até regularizar em "Meu Plano". Seus alunos e treinos atuais continuam disponíveis normalmente.',
            url: '/plano',
        ))->all();
    }
}
