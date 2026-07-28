<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use App\Models\Student;

/**
 * Um disparo candidato encontrado por uma Rule: quem recebe, quem é o personal
 * dono da preferência (mesmo quando o destinatário é o próprio aluno — quem liga
 * /desliga o tipo é sempre o personal), a chave de deduplicação já pronta (a Rule
 * decide sozinha se ela é por dia, por marco único, etc.) e o conteúdo da notificação.
 */
readonly class NotificacaoCandidato
{
    /**
     * Item 9 de uma revisão externa: o texto que aparece na tela de bloqueio não
     * deve expor frequência de treino, dado de saúde ou nome de aluno — um
     * aparelho pode ser compartilhado/visível a terceiros. Tipos que revelam
     * isso (sem_treinar_*, avaliacao_pendente, estagnacao_detectada do lado do
     * aluno; avaliacao_recebida, resumo_semanal_risco, revisao_pendente,
     * mensagem_sem_resposta do lado do personal) usam esses textos genéricos —
     * o detalhe completo só aparece depois de abrir o app pelo link da
     * notificação. Tipos só de celebração (PR, badge, streak, parabéns) e
     * operacionais sem dado sensível (novo treino, aprovação) continuam com
     * texto rico — não há nada ali que alguém precise esconder de quem olhar o
     * aparelho de relance.
     */
    public const string TITULO_ALUNO_GENERICO = 'Clube Mais Personal';

    public const string CORPO_ALUNO_GENERICO = 'Você tem uma novidade no app. Toque para ver.';

    public const string TITULO_PERSONAL_GENERICO = 'Clube Mais Personal';

    public const string CORPO_PERSONAL_GENERICO = 'Você tem uma atualização sobre um aluno. Toque para ver.';

    public function __construct(
        public Student|Professional $recipient,
        public string $professionalId,
        public ?string $studentId,
        public string $dedupKey,
        public ?string $contexto,
        public string $titulo,
        public string $corpo,
        public ?string $url,
        /**
         * Callback opcional chamado por ProcessNotifications::registrarEDisparar
         * SÓ quando o envio for realmente confirmado (não suprimido pelo
         * CoordenadorNotificacoes, não duplicado). Usado por regras que precisam
         * persistir um "estado visto" que vira base de comparação futura (ex:
         * MudancaRankingDesafioRule) — gravar esse estado direto em avaliar(),
         * antes da coordenação decidir se o push realmente sai, marcaria a
         * mudança como notificada mesmo quando ela foi suprimida.
         */
        public ?\Closure $aoConfirmarEnvio = null,
    ) {
    }
}
