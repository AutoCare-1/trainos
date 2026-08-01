<?php

namespace App\Support;

use App\Models\Professional;
use App\Models\ProfessionalSubscription;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Calcula o status efetivo da assinatura do personal com o TrainOS (teste
 * grátis / ativa / atrasada dentro da carência / bloqueada) e o limite de
 * alunos que vale nesse momento. Usado tanto por AssinaturaController (pra
 * mostrar a tela "Meu Plano") quanto por AlunoController::store (pra travar
 * cadastro de aluno novo) — um único lugar decide a regra, os dois só leem.
 *
 * Item combinado com a Carol: só o cadastro de aluno novo é travado; o resto
 * do app continua acessível mesmo com assinatura atrasada/bloqueada. Por isso
 * essa checagem não vira middleware global (JwtAuthenticate é
 * deliberadamente livre de banco em toda rota, ver comentário lá) — só entra
 * nos dois pontos que realmente precisam.
 */
class Assinatura
{
    public const MOTIVO_TESTE_EXPIRADO = 'teste_expirado';

    public const MOTIVO_LIMITE_PLANO = 'limite_plano';

    public const MOTIVO_PAGAMENTO_ATRASADO = 'pagamento_atrasado';

    /**
     * @return array{
     *   subscription: ?ProfessionalSubscription,
     *   em_teste: bool,
     *   dias_restantes_teste: ?int,
     *   dias_restantes_carencia: ?int,
     *   limite_alunos: ?int,
     *   motivo_bloqueio: ?string,
     * }
     */
    public static function status(Professional $professional): array
    {
        $subscription = ProfessionalSubscription::where('professional_id', $professional->id)->first();

        $diasTeste = (int) config('planos_assinatura.dias_teste_gratis');
        // Student não tem cast de created_at (useCurrent() do banco, não do
        // Eloquent) — Professional é igual, precisa de parse manual, mesmo
        // ajuste já feito em PortalController::revisao.
        $criadoEm = Carbon::parse($professional->created_at);
        // Carbon 3 devolve float por padrão (diff exato) — arredonda pra baixo
        // pra virar "dias inteiros já passados", senão a UI mostra "5.93 dias".
        $diasDesdeCriacao = (int) $criadoEm->diffInDays(now());
        $diasRestantesTeste = max(0, $diasTeste - $diasDesdeCriacao);
        $emTeste = ! $subscription && $diasDesdeCriacao < $diasTeste;

        if ($emTeste) {
            return [
                'subscription' => null,
                'em_teste' => true,
                'dias_restantes_teste' => $diasRestantesTeste,
                'dias_restantes_carencia' => null,
                'limite_alunos' => null,
                'motivo_bloqueio' => null,
            ];
        }

        if (! $subscription || in_array($subscription->status, [
            ProfessionalSubscription::STATUS_BLOQUEADA,
            ProfessionalSubscription::STATUS_CANCELADA,
            ProfessionalSubscription::STATUS_PENDENTE,
        ], true)) {
            return [
                'subscription' => $subscription,
                'em_teste' => false,
                'dias_restantes_teste' => 0,
                'dias_restantes_carencia' => null,
                'limite_alunos' => 0,
                'motivo_bloqueio' => $subscription ? self::MOTIVO_PAGAMENTO_ATRASADO : self::MOTIVO_TESTE_EXPIRADO,
            ];
        }

        $plano = config("planos_assinatura.planos.{$subscription->plano_chave}");
        $limiteAlunos = $plano['limite_alunos'] ?? 0;

        $diasRestantesCarencia = null;
        if ($subscription->status === ProfessionalSubscription::STATUS_ATRASADA && $subscription->atraso_desde) {
            $diasCarencia = (int) config('planos_assinatura.dias_carencia');
            $diasEmAtraso = (int) Carbon::parse($subscription->atraso_desde)->diffInDays(now());
            $diasRestantesCarencia = max(0, $diasCarencia - $diasEmAtraso);
        }

        return [
            'subscription' => $subscription,
            'em_teste' => false,
            'dias_restantes_teste' => 0,
            'dias_restantes_carencia' => $diasRestantesCarencia,
            'limite_alunos' => $limiteAlunos,
            'motivo_bloqueio' => null,
        ];
    }

    /** Quantos alunos ativos esse personal já tem — usado tanto na tela de status quanto na trava. */
    public static function alunosAtivos(Professional $professional): int
    {
        return Student::where('professional_id', $professional->id)->where('status', 'active')->count();
    }
}
