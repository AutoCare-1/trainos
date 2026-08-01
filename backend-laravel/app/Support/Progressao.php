<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sugestão de progressão de carga/reps pra próxima sessão, a partir do que o
 * aluno realmente executou na última sessão daquele exercício.
 *
 * Complementa (não substitui) o App\Support\Estagnacao: aquele é reativo —
 * avisa depois que o aluno já ficou 4 sessões sem evoluir; este é proativo,
 * sugere o número da próxima sessão assim que existe uma sessão de histórico.
 *
 * Cálculo determinístico de propósito, sem chamada de IA: progressão de carga
 * é matemática bem estabelecida (bateu o topo da faixa em todas as séries ->
 * sobe o menor incremento praticável; não bateu -> mantém; ficou abaixo do
 * mínimo -> mantém ou reduz). Um LLM aqui seria mais caro, mais lento e menos
 * previsível — e o personal precisa confiar no número pra aceitar ou ajustar.
 *
 * A decisão continua sendo do personal: isto devolve sugestão, nunca aplica
 * nada sozinho (mesmo princípio de "IA sugere, humano aprova" usado no resto
 * do produto, ainda que aqui não tenha IA nenhuma).
 */
class Progressao
{
    /**
     * Alvo de aumento quando o aluno bate o topo da faixa. Fica em ~2,5% do que
     * ele já levantava, mas sempre arredondado pra cima até um múltiplo do
     * incremento mínimo do equipamento — não adianta sugerir +0,5kg numa barra
     * onde a menor anilha disponível faz +2,5kg.
     */
    public const PERCENTUAL_ALVO = 0.025;

    /**
     * Menor mudança de carga que dá pra fazer de verdade em cada equipamento,
     * em kg. Barra: par de anilhas de 1,25. Halteres: costumam subir de 1 em 1
     * kg por peça (2kg no par). Máquina/polia: placa inteira do stack.
     * "Peso corporal" é 0 — não existe carga pra somar, a progressão ali é
     * em repetições.
     */
    private const INCREMENTOS = [
        'barra' => 2.5,
        'barra w' => 2.5,
        'barra t' => 2.5,
        'halter' => 2.0,
        'halteres' => 2.0,
        'maquina' => 5.0,
        'polia' => 5.0,
        'peso corporal' => 0.0,
    ];

    /** Equipamento desconhecido/genérico ("Equipamento", "Esteira", null) cai aqui. */
    private const INCREMENTO_PADRAO = 2.5;

    public static function incrementoMinimo(?string $equipment): float
    {
        $chave = Str::ascii(mb_strtolower(trim((string) $equipment)));

        return self::INCREMENTOS[$chave] ?? self::INCREMENTO_PADRAO;
    }

    /**
     * A prescrição de reps é texto livre digitado pelo personal ("10-12", "10",
     * "8 a 12", "até a falha"). Extrai os números que aparecerem: nenhum -> não
     * dá pra avaliar objetivamente (devolve null, e o exercício fica sem
     * sugestão em vez de receber um chute).
     *
     * @return array{min: int, max: int}|null
     */
    public static function parseFaixaReps(?string $reps): ?array
    {
        if (! preg_match_all('/\d+/', (string) $reps, $achados)) {
            return null;
        }

        $numeros = array_map('intval', $achados[0]);
        // Mais de dois números não é faixa ("3x10 de 12 séries"?) — sem sugestão,
        // melhor que interpretar errado a intenção do personal.
        if (count($numeros) > 2) {
            return null;
        }

        $min = min($numeros);
        $max = max($numeros);

        return $min > 0 ? ['min' => $min, 'max' => $max] : null;
    }

    /**
     * Sugestões pra todos os exercícios em que o aluno já tem pelo menos uma
     * sessão concluída registrada, indexadas por exercise_id.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function paraAluno(string $studentId, string $professionalId): array
    {
        $linhas = DB::select(
            <<<'SQL'
            with sessoes as (
              select we.exercise_id, ts.id as session_id, ts.finished_at,
                     row_number() over (partition by we.exercise_id order by ts.finished_at desc) as rn
              from training_sessions ts
              join session_entries se on se.training_session_id = ts.id
              join workout_exercises we on we.id = se.workout_exercise_id
              where ts.student_id = ? and ts.status = 'completed'
              group by we.exercise_id, ts.id, ts.finished_at
            )
            select s.exercise_id, s.finished_at,
                   se.set_number, se.reps_done, se.load_kg_done,
                   we.reps as reps_prescritas, we.sets as series_prescritas,
                   e.name as exercise_name, e.equipment
            from sessoes s
            join session_entries se on se.training_session_id = s.session_id
            join workout_exercises we on we.id = se.workout_exercise_id
                                     and we.exercise_id = s.exercise_id
            join exercises e on e.id = s.exercise_id
            where s.rn = 1
            order by e.name, se.set_number
            SQL,
            [$studentId]
        );

        // Mesma fonte de verdade do alerta in-app e da notificação push — um
        // exercício já sinalizado como estagnado não pode receber sugestão de
        // aumento só porque a última sessão isolada foi boa.
        $estagnados = collect(Estagnacao::compararUltimasSessoes($professionalId))
            ->filter(fn ($c) => $c['student_id'] === $studentId && $c['ultima'] <= $c['anterior'])
            ->pluck('exercise_id')
            ->flip();

        $porExercicio = [];
        foreach ($linhas as $linha) {
            $porExercicio[$linha->exercise_id][] = $linha;
        }

        $sugestoes = [];
        foreach ($porExercicio as $exerciseId => $series) {
            $sugestao = self::avaliar($series, $estagnados->has($exerciseId));
            if ($sugestao) {
                $sugestoes[$exerciseId] = $sugestao;
            }
        }

        return $sugestoes;
    }

    /**
     * @param  object[]  $series  linhas da última sessão concluída, um registro por série
     */
    private static function avaliar(array $series, bool $estagnado): ?array
    {
        $primeira = $series[0];
        $faixa = self::parseFaixaReps($primeira->reps_prescritas);
        if (! $faixa) {
            return null;
        }

        // Carga de referência é a maior da sessão (mesmo critério do Estagnacao),
        // e não a da última série — quem faz série decrescente terminaria a
        // sessão com o menor peso e receberia uma sugestão artificialmente baixa.
        $cargas = array_filter(array_map(fn ($s) => $s->load_kg_done, $series), fn ($c) => $c !== null);
        $cargaAtual = $cargas ? (float) max($cargas) : null;

        $repsPorSerie = array_map(fn ($s) => $s->reps_done, $series);
        // Série sem reps registrado não dá pra julgar como sucesso — conta como
        // "não bateu o topo", nunca como motivo pra subir carga.
        $bateuTopoEmTodas = ! in_array(null, $repsPorSerie, true)
            && count(array_filter($repsPorSerie, fn ($r) => $r >= $faixa['max'])) === count($repsPorSerie);
        $abaixoDoMinimo = count(array_filter($repsPorSerie, fn ($r) => $r === null || $r < $faixa['min']));
        $completouTodasAsSeries = count($series) >= (int) $primeira->series_prescritas;

        $incremento = self::incrementoMinimo($primeira->equipment);
        $base = [
            'exercise_id' => $primeira->exercise_id,
            'exercise_name' => $primeira->exercise_name,
            'equipment' => $primeira->equipment,
            'ultima_sessao_em' => $primeira->finished_at,
            'reps_prescritas' => $primeira->reps_prescritas,
            'series_registradas' => count($series),
            'series_prescritas' => (int) $primeira->series_prescritas,
            'carga_anterior' => $cargaAtual,
            'estagnado' => $estagnado,
            'reps_sugeridas' => null,
        ];

        if ($abaixoDoMinimo > 1 || $estagnado) {
            // Reduzir só faz sentido se ainda sobrar carga depois do desconto —
            // abaixo de um incremento a única saída é manter.
            $podeReduzir = $cargaAtual !== null && $incremento > 0 && $cargaAtual > $incremento;

            return array_merge($base, $podeReduzir ? [
                'acao' => 'reduzir',
                'carga_sugerida' => round($cargaAtual - $incremento, 2),
                'delta_kg' => round(-$incremento, 2),
                'motivo' => 'Ficou abaixo da faixa prescrita em mais de uma série',
            ] : [
                'acao' => 'manter',
                'carga_sugerida' => $cargaAtual,
                'delta_kg' => 0.0,
                'motivo' => 'Ficou abaixo da faixa prescrita — consolidar antes de subir',
            ]);
        }

        if ($abaixoDoMinimo === 1 || ! $completouTodasAsSeries || ! $bateuTopoEmTodas) {
            if ($abaixoDoMinimo === 1) {
                $motivo = 'Ficou abaixo da faixa prescrita em uma série';
            } elseif (! $completouTodasAsSeries) {
                $motivo = 'Não completou todas as séries prescritas';
            } else {
                $motivo = 'Completou dentro da faixa, mas ainda não no topo';
            }

            return array_merge($base, [
                'acao' => 'manter',
                'carga_sugerida' => $cargaAtual,
                'delta_kg' => 0.0,
                'motivo' => $motivo,
            ]);
        }

        // Bateu o topo da faixa em todas as séries. Sem carga pra somar (peso
        // corporal, ou o aluno nunca registrou peso), a progressão é em reps.
        if ($cargaAtual === null || $cargaAtual <= 0 || $incremento <= 0) {
            return array_merge($base, [
                'acao' => 'aumentar_reps',
                'carga_sugerida' => $cargaAtual,
                'delta_kg' => 0.0,
                'reps_sugeridas' => ($faixa['min'] + 1).'-'.($faixa['max'] + 1),
                'motivo' => 'Bateu o topo da faixa em todas as séries',
            ]);
        }

        // Sobe o menor número de incrementos que alcança o alvo de ~2,5%. O teto
        // de 5% sai de graça: se são 2+ passos, é porque um passo sozinho ficava
        // abaixo de 2,5%, então o total fica necessariamente abaixo de 5%. Um
        // passo só pode passar de 5% (ex: placa de 5kg numa máquina em 40kg) —
        // e aí é o menor aumento que a máquina permite mesmo.
        $passos = max(1, (int) ceil(($cargaAtual * self::PERCENTUAL_ALVO) / $incremento));
        $delta = $passos * $incremento;

        return array_merge($base, [
            'acao' => 'aumentar_carga',
            'carga_sugerida' => round($cargaAtual + $delta, 2),
            'delta_kg' => round($delta, 2),
            'motivo' => 'Bateu o topo da faixa em todas as séries',
        ]);
    }
}
