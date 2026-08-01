<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\ProfessionalNotificationSetting;
use App\Models\TipoNotificacao;
use App\Notifications\PushNotification;
use App\Notifications\Rules\NotificacaoCandidato;
use App\Notifications\Rules\AlertaSextaRule;
use App\Notifications\Rules\AlunoCadastradoRule;
use App\Notifications\Rules\AssinaturaBloqueadaRule;
use App\Notifications\Rules\AssinaturaPagamentoFalhouRule;
use App\Notifications\Rules\AvaliacaoPendenteRule;
use App\Notifications\Rules\AvaliacaoRecebidaRule;
use App\Notifications\Rules\ComentarioFotoEvolucaoRule;
use App\Notifications\Rules\DesafioTerminandoRule;
use App\Notifications\Rules\EstagnacaoDetectadaRule;
use App\Notifications\Rules\MarcoTempoTreinandoRule;
use App\Notifications\Rules\MedalhaConquistadaRule;
use App\Notifications\Rules\MensagemNaoLidaRule;
use App\Notifications\Rules\MensagemSemRespostaRule;
use App\Notifications\Rules\MudancaRankingDesafioRule;
use App\Notifications\Rules\NotificacaoRule;
use App\Notifications\Rules\NovoRecordePessoalRule;
use App\Notifications\Rules\NovoTreinoEnviadoRule;
use App\Notifications\Rules\OnboardingBoasVindasRule;
use App\Notifications\Rules\ParabensFimDeSemanaRule;
use App\Notifications\Rules\ResumoSemanalRiscoRule;
use App\Notifications\Rules\RevisaoPendenteRule;
use App\Notifications\Rules\SemTreinarDiasRule;
use App\Notifications\Rules\SemTreinarHojeRule;
use App\Notifications\Rules\StreakEmRiscoRule;
use App\Notifications\Rules\TreinoAcademiaAprovadoRule;
use App\Notifications\Rules\TreinoVencendoAlunoRule;
use App\Notifications\Rules\TreinoVencendoPersonalRule;
use App\Notifications\Rules\TreinoVencidoAlunoRule;
use App\Notifications\Rules\TreinoVencidoPersonalRule;
use App\Support\CoordenadorNotificacoes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Roda a cada 15min via Scheduler (routes/console.php). Cada Rule decide sozinha
 * sua própria janela de tempo/limiar — este comando coleta os candidatos de
 * TODAS as Rules, filtra por preferência (toggle ligado/desligado), manda pro
 * CoordenadorNotificacoes (item 2 de revisão externa: supressão entre regras
 * que competem pelo mesmo motivo + limite diário por destinatário) e só então
 * garante idempotência via notification_logs e despacha o job de envio real
 * (App\Notifications\PushNotification, que implementa ShouldQueue — o envio em
 * si roda na fila, nunca aqui dentro).
 */
class ProcessNotifications extends Command
{
    protected $signature = 'notifications:process';

    protected $description = 'Avalia todas as regras de notificação e despacha os pushes pendentes, respeitando toggles e evitando duplicidade.';

    /** @var array<string, bool> cache local desta execução — evita reconsultar a mesma preferência a cada candidato */
    private array $cachePreferencia = [];

    /** @return NotificacaoRule[] */
    private function regras(): array
    {
        return [
            new SemTreinarHojeRule,
            new SemTreinarDiasRule,
            new StreakEmRiscoRule,
            new AlertaSextaRule,
            new ParabensFimDeSemanaRule,
            new OnboardingBoasVindasRule,
            new NovoRecordePessoalRule,
            new MedalhaConquistadaRule,
            new MudancaRankingDesafioRule,
            new DesafioTerminandoRule,
            new MarcoTempoTreinandoRule,
            new NovoTreinoEnviadoRule,
            new TreinoAcademiaAprovadoRule,
            new TreinoVencendoAlunoRule,
            new TreinoVencendoPersonalRule,
            new TreinoVencidoAlunoRule,
            new TreinoVencidoPersonalRule,
            new AssinaturaPagamentoFalhouRule,
            new AssinaturaBloqueadaRule,
            new MensagemNaoLidaRule,
            new AvaliacaoPendenteRule,
            new EstagnacaoDetectadaRule,
            new ResumoSemanalRiscoRule,
            new AvaliacaoRecebidaRule,
            new RevisaoPendenteRule,
            new MensagemSemRespostaRule,
            new ComentarioFotoEvolucaoRule,
            new AlunoCadastradoRule,
        ];
    }

    public function handle(): int
    {
        $catalogo = TipoNotificacao::all()->keyBy('chave');

        /** @var array<int, array{chave: string, candidato: NotificacaoCandidato}> $candidatosLiberados */
        $candidatosLiberados = [];

        foreach ($this->regras() as $regra) {
            $chave = $regra->chave();
            $tipo = $catalogo->get($chave);

            if (! $tipo) {
                $this->error("Regra '{$chave}' não tem tipo correspondente em tipos_notificacao — pulando.");
                continue;
            }

            try {
                $candidatos = $regra->avaliar();
            } catch (\Throwable $e) {
                Log::error("[notifications:process] Falha ao avaliar regra '{$chave}': {$e->getMessage()}", ['exception' => $e]);
                $this->error("Erro na regra '{$chave}': {$e->getMessage()}");

                continue;
            }

            foreach ($candidatos as $candidato) {
                if ($this->preferenciaLigada($chave, $candidato, $tipo->ativo_por_padrao)) {
                    $candidatosLiberados[] = ['chave' => $chave, 'candidato' => $candidato];
                }
            }
        }

        $coordenados = (new CoordenadorNotificacoes)->coordenar($candidatosLiberados);
        $suprimidosOuLimitados = count($candidatosLiberados) - count($coordenados);

        $enviados = 0;
        $pulados = 0;
        foreach ($coordenados as $item) {
            if ($this->registrarEDisparar($item['chave'], $item['candidato'])) {
                $enviados++;
            } else {
                $pulados++;
            }
        }

        $this->info(
            "Notificações despachadas: {$enviados}. Já haviam sido enviadas (puladas): {$pulados}. ".
            "Suprimidas/limitadas pela coordenação (supressão de motivo repetido ou limite diário): {$suprimidosOuLimitados}."
        );

        return self::SUCCESS;
    }

    private function preferenciaLigada(string $tipoChave, NotificacaoCandidato $candidato, bool $ativoPorPadrao): bool
    {
        // Preferência específica de aluno (se existir) tem prioridade sobre a global do personal.
        if ($candidato->studentId) {
            $chaveCache = "aluno:{$candidato->professionalId}:{$tipoChave}:{$candidato->studentId}";
            if (! array_key_exists($chaveCache, $this->cachePreferencia)) {
                $config = ProfessionalNotificationSetting::where('professional_id', $candidato->professionalId)
                    ->where('tipo_chave', $tipoChave)
                    ->where('student_id', $candidato->studentId)
                    ->first();
                $this->cachePreferencia[$chaveCache] = $config?->enabled;
            }
            if ($this->cachePreferencia[$chaveCache] !== null) {
                return $this->cachePreferencia[$chaveCache];
            }
        }

        $chaveCacheGlobal = "global:{$candidato->professionalId}:{$tipoChave}";
        if (! array_key_exists($chaveCacheGlobal, $this->cachePreferencia)) {
            $config = ProfessionalNotificationSetting::where('professional_id', $candidato->professionalId)
                ->where('tipo_chave', $tipoChave)
                ->whereNull('student_id')
                ->first();
            $this->cachePreferencia[$chaveCacheGlobal] = $config?->enabled ?? $ativoPorPadrao;
        }

        return $this->cachePreferencia[$chaveCacheGlobal];
    }

    private function registrarEDisparar(string $tipoChave, NotificacaoCandidato $candidato): bool
    {
        $log = NotificationLog::firstOrCreate(
            ['dedup_key' => $candidato->dedupKey],
            [
                'tipo_chave' => $tipoChave,
                'student_id' => $candidato->studentId,
                'professional_id' => $candidato->professionalId,
                'contexto' => $candidato->contexto,
            ]
        );

        if (! $log->wasRecentlyCreated) {
            return false;
        }

        $candidato->recipient->notify(new PushNotification($candidato->titulo, $candidato->corpo, $candidato->url));
        $candidato->aoConfirmarEnvio?->call($this);

        return true;
    }
}
