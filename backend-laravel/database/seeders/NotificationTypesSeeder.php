<?php

namespace Database\Seeders;

use App\Models\TipoNotificacao;
use Illuminate\Database\Seeder;

class NotificationTypesSeeder extends Seeder
{
    /**
     * Catálogo de tipos de notificação. sem_treinar_dias e marco_tempo_treinando
     * cobrem vários limiares (3/7/14 dias; 1/3/6/12 meses) numa regra só, em vez de
     * um toggle por limiar — mesmo evento, só intensidade diferente.
     */
    public function run(): void
    {
        $tipos = [
            // Hábito e engajamento (aluno)
            ['sem_treinar_hoje', 'Sem treinar hoje', 'Lembrete se o aluno não completou o treino do dia até um horário configurável.', 'habito', 'aluno'],
            ['sem_treinar_dias', 'Sem treinar há alguns dias', 'Aviso com tom crescente em 3, 7 e 14 dias sem treinar — sem culpa.', 'habito', 'aluno'],
            ['streak_em_risco', 'Sequência em risco', 'Aluno com sequência ativa de dias treinando prestes a quebrar hoje.', 'habito', 'aluno'],
            ['alerta_sexta', 'Alerta de sexta-feira', 'Mensagem de cuidado/moderação pro fim de semana, toda sexta.', 'habito', 'aluno'],
            ['parabens_fim_semana', 'Parabéns pela semana', 'Parabenização quando o aluno treina acima do limiar combinado na semana.', 'habito', 'aluno'],
            ['onboarding_boas_vindas', 'Boas-vindas (onboarding)', 'Sequência curta (dia 1, 3 e 7) pra alunos novos formarem o hábito.', 'habito', 'aluno'],

            // Celebração e gamificação (aluno)
            ['novo_recorde_pessoal', 'Novo recorde pessoal', 'Aviso quando o aluno bate um PR de carga num exercício.', 'celebracao', 'aluno'],
            ['medalha_conquistada', 'Medalha conquistada', 'Aviso ao atingir um marco de desafio (medalha).', 'celebracao', 'aluno'],
            ['mudanca_ranking_desafio', 'Mudança no ranking do desafio', 'Aviso quando o aluno sobe ou desce de posição num desafio ativo.', 'celebracao', 'aluno'],
            ['desafio_terminando', 'Desafio terminando', 'Aviso 24-48h antes do fim de um desafio que o aluno participa.', 'celebracao', 'aluno'],
            ['marco_tempo_treinando', 'Marco de tempo treinando', 'Aviso em 1, 3, 6 e 12 meses desde o cadastro do aluno.', 'celebracao', 'aluno'],

            // Informativo/operacional (aluno)
            ['novo_treino_enviado', 'Novo treino enviado', 'Aviso quando o personal envia ou atualiza o treino do aluno.', 'informativo', 'aluno'],
            ['treino_academia_aprovado', 'Treino de academia aprovado', 'Aviso quando o personal aprova a sugestão da análise de academia.', 'informativo', 'aluno'],
            ['mensagem_nao_lida', 'Mensagem não lida', 'Aviso quando uma mensagem do personal ou da Coach IA fica sem abrir por algumas horas.', 'informativo', 'aluno'],
            ['avaliacao_pendente', 'Avaliação pendente', 'Aviso quando o aluno passa 30+ dias sem atualizar as medidas corporais.', 'informativo', 'aluno'],
            ['estagnacao_detectada', 'Estagnação detectada', 'Aviso quando a carga de um exercício não evolui nas últimas sessões.', 'informativo', 'aluno'],
            ['comentario_foto_evolucao', 'Comentário da Coach IA na foto', 'Aviso quando a Coach IA comenta uma foto de evolução física recém-enviada.', 'informativo', 'aluno'],

            // Gestão (personal)
            ['resumo_semanal_risco', 'Resumo semanal de risco', 'Toda segunda, resumo de quantos alunos estão em risco de abandono.', 'gestao', 'personal'],
            ['avaliacao_recebida', 'Avaliação recebida', 'Aviso quando um aluno completa o PAR-Q ou a avaliação física pela primeira vez.', 'gestao', 'personal'],
            ['revisao_pendente', 'Revisão pendente', 'Aviso quando uma análise de academia aguarda aprovação do personal.', 'gestao', 'personal'],
            ['mensagem_sem_resposta', 'Mensagem sem resposta', 'Aviso quando um aluno espera resposta do personal há mais de algumas horas.', 'gestao', 'personal'],
            ['aluno_cadastrado', 'Novo aluno cadastrado', 'Aviso quando um novo aluno é cadastrado.', 'gestao', 'personal'],
        ];

        foreach ($tipos as [$chave, $nome, $descricao, $categoria, $publico]) {
            TipoNotificacao::updateOrCreate(
                ['chave' => $chave],
                ['nome' => $nome, 'descricao' => $descricao, 'categoria' => $categoria, 'publico' => $publico, 'ativo_por_padrao' => true]
            );
        }
    }
}
