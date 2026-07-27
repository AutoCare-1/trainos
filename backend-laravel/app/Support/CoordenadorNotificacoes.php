<?php

namespace App\Support;

use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\Student;
use App\Models\TipoNotificacao;
use Illuminate\Support\Collection;

/**
 * Item 2 de uma revisão externa: com 20+ Rules independentes, sem coordenação
 * entre elas, o mesmo destinatário podia acumular várias notificações no
 * mesmo dia (ex: sem_treinar_hoje + streak_em_risco disparando juntos pro
 * mesmo aluno; onboarding_boas_vindas + sem_treinar_dias competindo pelo
 * mesmo "motivo" pra aluno novo). Duas camadas, aplicadas em
 * ProcessNotifications depois de coletar os candidatos de TODAS as Rules e
 * ANTES de despachar qualquer job:
 *
 * 1) Supressão: pares onde uma regra mais específica cobre o mesmo motivo de
 *    uma mais genérica — a genérica é descartada quando as duas batem pro
 *    mesmo destinatário no mesmo ciclo.
 * 2) Limite diário por destinatário: no máximo LIMITE_DIARIO_POR_DESTINATARIO
 *    notificações por dia, escolhendo por prioridade quando sobra candidato —
 *    conta o que JÁ foi enviado em ciclos anteriores do mesmo dia (via
 *    notification_logs), não só o que está sendo decidido agora, senão o
 *    limite "vaza" entre execuções do Scheduler a cada 15min.
 */
class CoordenadorNotificacoes
{
    public const LIMITE_DIARIO_POR_DESTINATARIO = 2;

    /** Menor número = maior prioridade. Tipo fora da lista cai no fim (99). */
    private const PRIORIDADE = [
        // Acionável/transacional — o aluno ou personal precisa saber pra agir.
        'novo_treino_enviado' => 1,
        'treino_academia_aprovado' => 1,
        'mensagem_nao_lida' => 1,
        'mensagem_sem_resposta' => 1,
        'revisao_pendente' => 1,
        'avaliacao_recebida' => 1,
        // Celebração — reforço positivo, vale a pena preservar mesmo com pouca vaga.
        'novo_recorde_pessoal' => 2,
        'medalha_conquistada' => 2,
        'mudanca_ranking_desafio' => 2,
        'desafio_terminando' => 2,
        'marco_tempo_treinando' => 2,
        'parabens_fim_semana' => 2,
        'comentario_foto_evolucao' => 2,
        // Onboarding — orientação importante nos primeiros dias.
        'onboarding_boas_vindas' => 3,
        // Cutucões de hábito — os que mais colidem entre si, prioridade mais baixa.
        'streak_em_risco' => 4,
        'sem_treinar_hoje' => 4,
        'sem_treinar_dias' => 4,
        'alerta_sexta' => 4,
        'avaliacao_pendente' => 4,
        'estagnacao_detectada' => 4,
        // Resumo/administrativo do personal — menos urgente que os tipos acima.
        'resumo_semanal_risco' => 5,
        'aluno_cadastrado' => 5,
    ];

    /** regra dominante => regras suprimidas quando ambas competem no mesmo ciclo pro mesmo destinatário. */
    private const SUPRESSOES = [
        'onboarding_boas_vindas' => ['sem_treinar_dias', 'sem_treinar_hoje'],
        'streak_em_risco' => ['sem_treinar_hoje'],
    ];

    /**
     * @param  array<int, array{chave: string, candidato: \App\Notifications\Rules\NotificacaoCandidato}>  $itens
     * @return array<int, array{chave: string, candidato: \App\Notifications\Rules\NotificacaoCandidato}>
     */
    public function coordenar(array $itens): array
    {
        if (empty($itens)) {
            return [];
        }

        $publicoPorChave = TipoNotificacao::pluck('publico', 'chave');

        $porDestinatario = collect($itens)->groupBy(
            fn ($item) => get_class($item['candidato']->recipient).':'.$item['candidato']->recipient->getKey()
        );

        $resultado = [];
        foreach ($porDestinatario as $grupo) {
            $grupo = $this->suprimir($grupo->all());
            if (empty($grupo)) {
                continue;
            }

            usort($grupo, fn ($a, $b) => $this->prioridade($a['chave']) <=> $this->prioridade($b['chave']));

            $recipient = $grupo[0]['candidato']->recipient;
            $jaEnviadasHoje = $this->contarEnviadasHoje($recipient, $publicoPorChave);
            $vagas = max(0, self::LIMITE_DIARIO_POR_DESTINATARIO - $jaEnviadasHoje);

            $resultado = array_merge($resultado, array_slice($grupo, 0, $vagas));
        }

        return $resultado;
    }

    /** @param  array<int, array{chave: string, candidato: \App\Notifications\Rules\NotificacaoCandidato}>  $grupo */
    private function suprimir(array $grupo): array
    {
        $chavesPresentes = array_column($grupo, 'chave');

        foreach (self::SUPRESSOES as $dominante => $suprimidas) {
            if (in_array($dominante, $chavesPresentes, true)) {
                $grupo = array_values(array_filter(
                    $grupo,
                    fn ($item) => ! in_array($item['chave'], $suprimidas, true)
                ));
            }
        }

        return $grupo;
    }

    private function prioridade(string $chave): int
    {
        return self::PRIORIDADE[$chave] ?? 99;
    }

    private function contarEnviadasHoje(Student|Professional $recipient, Collection $publicoPorChave): int
    {
        $publicoAlvo = $recipient instanceof Student ? 'aluno' : 'personal';
        $tiposDoPublico = $publicoPorChave->filter(fn ($publico) => $publico === $publicoAlvo)->keys();
        $coluna = $recipient instanceof Student ? 'student_id' : 'professional_id';

        return NotificationLog::whereIn('tipo_chave', $tiposDoPublico)
            ->where($coluna, $recipient->getKey())
            ->whereDate('enviado_em', now()->toDateString())
            ->count();
    }
}
