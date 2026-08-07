<?php

namespace App\Support;

use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\Student;
use App\Models\TipoNotificacao;
use App\Notifications\Rules\NotificacaoCandidato;
use Illuminate\Support\Collection;

/**
 * Item 2 de uma revisão externa (e item 2/3 de uma segunda rodada): com 20+
 * Rules independentes, sem coordenação entre elas, o mesmo destinatário podia
 * acumular várias notificações no mesmo dia (ex: sem_treinar_hoje +
 * streak_em_risco disparando juntos pro mesmo aluno; onboarding_boas_vindas +
 * sem_treinar_dias competindo pelo mesmo "motivo" pra aluno novo;
 * aluno_cadastrado + avaliacao_recebida quase sempre no mesmo dia, já que o
 * PAR-Q é preenchido no primeiro acesso). Três camadas, aplicadas em
 * ProcessNotifications depois de coletar os candidatos de TODAS as Rules e
 * ANTES de despachar qualquer job:
 *
 * 1) Supressão: pares onde uma regra mais específica/mais informativa cobre o
 *    mesmo motivo de uma mais genérica — a genérica é descartada quando as
 *    duas batem pro mesmo destinatário E pro mesmo aluno (contexto) no mesmo
 *    ciclo.
 * 2) Consolidação: quando sobra mais de uma instância do MESMO tipo pro
 *    mesmo destinatário no mesmo ciclo (ex: 3 alunos diferentes com
 *    revisao_pendente), vira UMA notificação só, com contagem — em vez de
 *    cada instância competir separadamente pelo limite diário e esconder as
 *    outras.
 * 3) Limite diário por destinatário: no máximo LIMITE_DIARIO_POR_DESTINATARIO
 *    notificações (já consolidadas) por dia, escolhendo por prioridade
 *    quando sobra candidato — conta o que JÁ foi enviado em ciclos
 *    anteriores do mesmo dia (via notification_logs), não só o que está
 *    sendo decidido agora, senão o limite "vaza" entre execuções do
 *    Scheduler a cada 15min.
 */
class CoordenadorNotificacoes
{
    public const LIMITE_DIARIO_POR_DESTINATARIO = 2;

    /** Menor número = maior prioridade. Tipo fora da lista cai no fim (99). */
    private const PRIORIDADE = [
        // Sinais fortes de perda de cliente — não podem ser suprimidos por
        // uma celebração no mesmo dia (item 4 de uma segunda revisão).
        'mensagem_sem_resposta' => 0,
        'resumo_semanal_risco' => 0,
        // Acionável/transacional — o aluno ou personal precisa saber pra agir.
        'novo_treino_enviado' => 1,
        'treino_academia_aprovado' => 1,
        'mensagem_nao_lida' => 1,
        'revisao_pendente' => 1,
        'avaliacao_recebida' => 1,
        'treino_vencendo_aluno' => 1,
        'treino_vencendo_personal' => 1,
        'treino_vencido_aluno' => 1,
        'treino_vencido_personal' => 1,
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
        // Administrativo do personal — menos urgente que os tipos acima.
        'aluno_cadastrado' => 5,
    ];

    /**
     * regra dominante => regras suprimidas quando ambas competem no mesmo
     * ciclo, pro mesmo destinatário E pro mesmo aluno (studentId).
     * avaliacao_recebida > aluno_cadastrado: cadastro e primeira avaliação
     * acontecem quase sempre no mesmo dia (PAR-Q é preenchido no primeiro
     * acesso) — a avaliação é o fato mais informativo dos dois.
     */
    private const SUPRESSOES = [
        'onboarding_boas_vindas' => ['sem_treinar_dias', 'sem_treinar_hoje'],
        'streak_em_risco' => ['sem_treinar_hoje'],
        'avaliacao_recebida' => ['aluno_cadastrado'],
    ];

    /**
     * Tipos (todos do personal) que consolidam em UMA notificação com
     * contagem quando há mais de uma instância pro mesmo destinatário no
     * mesmo ciclo — não tem nome de aluno (mesmo cuidado do payload
     * genérico), só quantidade, que não expõe identidade nem dado sensível.
     */
    private const TEMPLATES_CONSOLIDACAO = [
        'revisao_pendente' => ['titulo' => 'Análises aguardando revisão', 'corpo' => '%d alunos com análise de academia aguardando sua aprovação.', 'url' => '/academia'],
        'mensagem_sem_resposta' => ['titulo' => 'Mensagens sem resposta', 'corpo' => '%d alunos esperando sua resposta no chat.', 'url' => '/dashboard'],
        'avaliacao_recebida' => ['titulo' => 'Novas avaliações recebidas', 'corpo' => '%d alunos completaram a avaliação de saúde.', 'url' => '/dashboard'],
        'aluno_cadastrado' => ['titulo' => 'Novos alunos cadastrados', 'corpo' => '%d novos alunos cadastrados.', 'url' => '/dashboard'],
    ];

    /**
     * @param  array<int, array{chave: string, candidato: NotificacaoCandidato}>  $itens
     * @return array<int, array{chave: string, candidato: NotificacaoCandidato}>
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
            $grupo = $this->consolidar($grupo);
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

    /** @param  array<int, array{chave: string, candidato: NotificacaoCandidato}>  $grupo */
    private function suprimir(array $grupo): array
    {
        foreach (self::SUPRESSOES as $dominante => $suprimidas) {
            $alunosComDominante = collect($grupo)
                ->filter(fn ($item) => $item['chave'] === $dominante)
                ->map(fn ($item) => $item['candidato']->studentId)
                ->filter()
                ->all();

            if (empty($alunosComDominante)) {
                continue;
            }

            $grupo = array_values(array_filter($grupo, function ($item) use ($suprimidas, $alunosComDominante) {
                if (! in_array($item['chave'], $suprimidas, true)) {
                    return true;
                }

                return ! in_array($item['candidato']->studentId, $alunosComDominante, true);
            }));
        }

        return $grupo;
    }

    /**
     * Junta instâncias repetidas do mesmo tipo (mesmo destinatário, mesmo
     * ciclo) numa notificação só, pros tipos com template de consolidação —
     * sem isso, N alunos com o mesmo tipo pendente competiam N vezes
     * separadas pelo limite diário, escondendo itens de ação real do
     * personal (item 3 de uma segunda revisão).
     *
     * @param  array<int, array{chave: string, candidato: NotificacaoCandidato}>  $grupo
     */
    private function consolidar(array $grupo): array
    {
        $porChave = collect($grupo)->groupBy('chave');

        $resultado = [];
        foreach ($porChave as $chave => $itensDaChave) {
            if ($itensDaChave->count() === 1 || ! isset(self::TEMPLATES_CONSOLIDACAO[$chave])) {
                $resultado = array_merge($resultado, $itensDaChave->all());

                continue;
            }

            $template = self::TEMPLATES_CONSOLIDACAO[$chave];
            $primeiro = $itensDaChave->first()['candidato'];
            $studentIds = $itensDaChave->pluck('candidato.studentId')->filter()->sort()->values()->all();

            $resultado[] = [
                'chave' => $chave,
                'candidato' => new NotificacaoCandidato(
                    recipient: $primeiro->recipient,
                    professionalId: $primeiro->professionalId,
                    studentId: null,
                    dedupKey: "grupo:{$chave}:{$primeiro->professionalId}:".implode(',', $studentIds).':'.now()->toDateString(),
                    contexto: (string) $itensDaChave->count(),
                    titulo: $template['titulo'],
                    corpo: sprintf($template['corpo'], $itensDaChave->count()),
                    url: $template['url'],
                ),
            ];
        }

        return $resultado;
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
