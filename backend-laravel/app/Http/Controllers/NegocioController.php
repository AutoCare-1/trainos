<?php

namespace App\Http\Controllers;

use App\Support\FinanceiroPersonal;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NegocioController extends Controller
{
    private const DIAS_SEM_TREINAR = 7;

    private const DIAS_SEM_CHECKIN = 7;

    private const DIAS_ALUNO_NOVO = 14;

    private const TREINOS_MINIMOS_ALUNO_NOVO = 3;

    // Diferença em dias de calendário (equivalente ao datediff() do MySQL, que
    // ignora a hora) — calculado em PHP pra não depender de datediff() no SQL,
    // que não existe no SQLite (mesmo padrão já usado nas Rule classes de notificação).
    private static function diasDesde(string $data): int
    {
        return (int) Carbon::parse($data)->startOfDay()->diffInDays(Carbon::now()->startOfDay());
    }

    private static function formatarMotivo(object $r): string
    {
        if ($r->motivo_codigo === 'novo_sem_treinos') {
            $dias = self::diasDesde($r->created_at);
            $sessoes = (int) $r->sessoes_concluidas;

            return "Cadastrado há {$dias}d, ainda com só {$sessoes} treino".($sessoes === 1 ? '' : 's').' concluído'.($sessoes === 1 ? '' : 's');
        }
        if ($r->motivo_codigo === 'sem_treinar') {
            return is_null($r->ultima_sessao_em) ? 'Nunca completou um treino' : 'Sem treinar há '.self::diasDesde($r->ultima_sessao_em).'d';
        }

        return is_null($r->ultimo_checkin_em) ? 'Nunca fez um check-in' : 'Sem check-in há '.self::diasDesde($r->ultimo_checkin_em).'d';
    }

    // GET / — visão geral do negócio do personal: KPIs de base de alunos + quem está em risco de abandono
    public function index(Request $request): JsonResponse
    {
        $professionalId = $request->user()->id;
        $diasSemTreinar = self::DIAS_SEM_TREINAR;
        $diasSemCheckin = self::DIAS_SEM_CHECKIN;
        $diasAlunoNovo = self::DIAS_ALUNO_NOVO;
        // Interpolada direto num CASE da query (constante da classe, nunca vem de
        // input) — cast explícito por defesa em profundidade, mesmo padrão de
        // Estagnacao::compararUltimasSessoes.
        $treinosMinimosAlunoNovo = (int) self::TREINOS_MINIMOS_ALUNO_NOVO;

        // date_trunc/interval não existem no MySQL — limites de data calculados em PHP/Carbon.
        $agora = Carbon::now()->toDateTimeString();
        $inicioMes = Carbon::now()->startOfMonth()->toDateTimeString();
        $inicioMesData = Carbon::now()->startOfMonth()->toDateString();
        $limiteSemTreinar = Carbon::now()->subDays($diasSemTreinar)->toDateTimeString();
        $limiteAlunoNovo = Carbon::now()->subDays($diasAlunoNovo)->toDateTimeString();
        $limiteSemCheckinTs = Carbon::now()->subDays($diasSemCheckin)->toDateTimeString();
        $limiteSemCheckinData = Carbon::now()->subDays($diasSemCheckin)->toDateString();

        // Os LEFT JOIN LATERAL do original viram joins normais contra tabelas derivadas
        // pré-agregadas (mesmo efeito, sem depender de suporte a LATERAL no MySQL) —
        // mesmo padrão já usado em AlunoController::index().
        $kpis = DB::selectOne(
            <<<'SQL'
            select
               count(*) as total_alunos,
               sum(case when s.created_at >= ? then 1 else 0 end) as novos_no_mes,
               sum(case when
                 exists(select 1 from workouts w where w.student_id = s.id and w.status = 'sent')
                 and (ultima_sessao.finished_at is null or ultima_sessao.finished_at < ?)
               then 1 else 0 end) as inativos
             from students s
             left join (
               select ts.student_id, max(ts.finished_at) as finished_at
               from training_sessions ts
               where ts.status = 'completed'
               group by ts.student_id
             ) ultima_sessao on ultima_sessao.student_id = s.id
             where s.professional_id = ?
            SQL,
            [$inicioMes, $limiteSemTreinar, $professionalId]
        );

        $retencao = DB::selectOne(
            <<<'SQL'
            with antigos as (
               select id from students
               where professional_id = ? and created_at < ?
             )
             select
               (select count(*) from antigos) as denominador,
               (select count(*) from antigos a
                where exists (
                  select 1 from training_sessions ts
                  where ts.student_id = a.id and ts.status = 'completed' and ts.finished_at >= ?
                ) or exists (
                  select 1 from checkins c
                  where c.student_id = a.id and c.checkin_date >= ?
                )
               ) as numerador
            SQL,
            [$professionalId, $inicioMes, $inicioMes, $inicioMesData]
        );
        $denominador = (int) $retencao->denominador;
        $retencaoPct = $denominador > 0 ? (int) round(((int) $retencao->numerador / $denominador) * 100) : null;

        $risco = DB::select(
            <<<SQL
            with base as (
               select
                 s.id, s.name, s.created_at,
                 coalesce(sessoes.total, 0) as sessoes_concluidas,
                 exists(select 1 from workouts w where w.student_id = s.id and w.status = 'sent') as tem_treino_enviado,
                 ultima_sessao.finished_at as ultima_sessao_em,
                 ultimo_checkin.checkin_date as ultimo_checkin_em
               from students s
               left join (
                 select ts.student_id, count(*) as total from training_sessions ts
                 where ts.status = 'completed'
                 group by ts.student_id
               ) sessoes on sessoes.student_id = s.id
               left join (
                 select ts.student_id, max(ts.finished_at) as finished_at
                 from training_sessions ts
                 where ts.status = 'completed'
                 group by ts.student_id
               ) ultima_sessao on ultima_sessao.student_id = s.id
               left join (
                 select student_id, max(checkin_date) as checkin_date from checkins group by student_id
               ) ultimo_checkin on ultimo_checkin.student_id = s.id
               where s.professional_id = ?
             ),
             classificado as (
               select
                 id, name, sessoes_concluidas, created_at, ultima_sessao_em, ultimo_checkin_em,
                 case
                   when created_at > ? and sessoes_concluidas < {$treinosMinimosAlunoNovo}
                     then 'novo_sem_treinos'
                   when tem_treino_enviado and (ultima_sessao_em is null or ultima_sessao_em < ?)
                     then 'sem_treinar'
                   when (ultimo_checkin_em is null and created_at < ?)
                     or (ultimo_checkin_em is not null and ultimo_checkin_em < ?)
                     then 'sem_checkin'
                   else null
                 end as motivo_codigo
               from base
             )
             select id, name, sessoes_concluidas, created_at, ultima_sessao_em, ultimo_checkin_em, motivo_codigo
             from classificado
             where motivo_codigo is not null
             order by
               case motivo_codigo when 'novo_sem_treinos' then 0 when 'sem_treinar' then 1 else 2 end,
               created_at desc
            SQL,
            [
                $professionalId,
                $limiteAlunoNovo, $limiteSemTreinar, $limiteSemCheckinTs, $limiteSemCheckinData,
            ]
        );

        $receita = FinanceiroPersonal::calcularReceitaMes($professionalId);
        $despesas = FinanceiroPersonal::calcularDespesasMes($professionalId);
        // Subtração em centavos (não receita['total'] - despesas['total'] direto,
        // que seria float) — mesma cautela usada no resto do cálculo financeiro.
        $resultadoLiquido = Money::fromCents(Money::toCents($receita['total']) - Money::toCents($despesas['total']));

        return response()->json([
            'financeiro' => [
                'mes_referencia' => Carbon::now()->format('Y-m'),
                'receita' => $receita,
                'despesas' => $despesas,
                'resultado_liquido' => $resultadoLiquido,
            ],
            'kpis' => [
                'total_alunos' => (int) $kpis->total_alunos,
                'novos_no_mes' => (int) $kpis->novos_no_mes,
                'inativos' => (int) $kpis->inativos,
                'retencao_pct' => $retencaoPct,
            ],
            'alunos_em_risco' => array_map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'prioridade' => $r->motivo_codigo === 'novo_sem_treinos' ? 'alta' : 'media',
                'motivo' => self::formatarMotivo($r),
            ], $risco),
        ]);
    }
}
