'use client'

import { useCallback, useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { ChevronLeft, ChevronRight, Plus, X } from 'lucide-react'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { formatarDataCurta, somarDias } from '@/lib/checkinDates'
import { HorarioAgenda, SemanaAgenda, Student } from '@/lib/types'

const NOME_DIA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']
const NOME_DIA_CURTO = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

// Grade fixa de horários (05h às 22h, de 1 em 1 hora) — o personal só escolhe
// o aluno pra cada linha, em vez de digitar o horário toda vez.
const HORAS_GRADE = Array.from({ length: 18 }, (_, i) => `${String(i + 5).padStart(2, '0')}:00`)

// Coluna fixa e estreita pra hora + 7 colunas iguais que dividem o resto. O
// minmax(0,1fr) é o que deixa as colunas encolherem abaixo do conteúdo, sem
// isso a grade estoura a largura da tela e volta a exigir rolagem lateral.
const COLUNAS_GRADE = '42px repeat(7, minmax(0, 1fr))'

function hojeIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export default function AgendaPage() {
  const router = useRouter()
  const [students, setStudents] = useState<Student[]>([])
  const [refSemana, setRefSemana] = useState<string | null>(null)
  const [semana, setSemana] = useState<SemanaAgenda | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  // Célula selecionada da grade (`${data}|${hora}`). O formulário abre num
  // painel abaixo da grade, não dentro da célula: numa coluna de ~45px (que é
  // o que sobra quando os 7 dias cabem juntos na tela do celular) não cabe
  // select nem input.
  const [selecionada, setSelecionada] = useState<{ data: string; diaSemana: number; hora: string } | null>(null)

  const carregarSemana = useCallback((ref: string | null) => {
    const query = ref ? `?semana=${ref}` : ''
    api
      .get<SemanaAgenda>(`/agenda${query}`)
      .then(setSemana)
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar a agenda'))
  }, [])

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ students: Student[] }>('/alunos')
      .then((d) => setStudents(d.students))
      .catch(() => {})
  }, [router])

  useEffect(() => {
    carregarSemana(refSemana)
  }, [refSemana, carregarSemana])

  function irParaSemana(direcao: 1 | -1) {
    const base = refSemana ?? semana?.inicio_semana ?? hojeIso()
    setRefSemana(somarDias(base, direcao * 7))
  }

  async function criarHorario(diaSemana: number, hora: string, dados: { student_id: string; titulo: string }) {
    setErro(null)
    try {
      await api.post('/agenda/horarios', {
        student_id: dados.student_id || null,
        titulo: dados.titulo || null,
        dia_semana: diaSemana,
        hora,
        duracao_minutos: 60,
      })
      setSelecionada(null)
      carregarSemana(refSemana)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao criar horário')
    }
  }

  async function salvarOcorrencia(
    slotId: string,
    data: string,
    campos: { student_id?: string | null; titulo?: string | null; presenca?: string | null }
  ) {
    setErro(null)
    try {
      await api.patch(`/agenda/horarios/${slotId}/ocorrencias`, { data, ...campos })
      setSelecionada(null)
      carregarSemana(refSemana)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao atualizar o horário')
    }
  }

  async function desativarSlot(slotId: string) {
    if (!confirm('Remover esse horário fixo da agenda? Ele para de aparecer nas próximas semanas.')) return
    setErro(null)
    try {
      await api.patch(`/agenda/horarios/${slotId}`, { ativo: false })
      carregarSemana(refSemana)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao remover o horário')
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <div className="mb-6">
          <h1 className="font-display text-2xl font-bold tracking-tight text-ink">Agenda</h1>
          <span className="title-accent" />
          <p className="mt-2 text-sm text-ink-muted">
            Clique num horário vazio pra colocar um aluno — troque quem ocupa um horário só numa data sem afetar as
            próximas semanas.
          </p>
        </div>

        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}

        <div className="mb-4 flex items-center justify-between">
          <button
            onClick={() => irParaSemana(-1)}
            aria-label="Semana anterior"
            className="flex h-8 w-8 items-center justify-center rounded-lg text-ink-muted transition hover:bg-ink/5"
          >
            <ChevronLeft size={18} />
          </button>
          <div className="text-center text-sm font-medium text-ink-soft">
            {semana && (
              <>
                {formatarDataCurta(semana.inicio_semana)} a {formatarDataCurta(somarDias(semana.inicio_semana, 6))}
                {refSemana && (
                  <button onClick={() => setRefSemana(null)} className="ml-2 text-xs font-semibold text-brand">
                    Hoje
                  </button>
                )}
              </>
            )}
          </div>
          <button
            onClick={() => irParaSemana(1)}
            aria-label="Próxima semana"
            className="flex h-8 w-8 items-center justify-center rounded-lg text-ink-muted transition hover:bg-ink/5"
          >
            <ChevronRight size={18} />
          </button>
        </div>

        {semana === null && !erro && <p className="text-ink-muted">Carregando...</p>}

        {semana && (
          <div className="glass overflow-hidden rounded-2xl">
            {/* Cabeçalho: os 7 dias, sempre visíveis juntos. A hora fica numa
                coluna fixa à esquerda em vez de repetida em cada célula — é o
                que permite as colunas caberem lado a lado até no celular. */}
            <div className="grid border-b border-line-soft" style={{ gridTemplateColumns: COLUNAS_GRADE }}>
              <div />
              {semana.dias.map((dia) => {
                const ehHoje = dia.data === hojeIso()
                return (
                  <div
                    key={dia.data}
                    className={`px-1 py-2 text-center ${ehHoje ? 'bg-brand/10' : ''}`}
                    title={NOME_DIA[dia.dia_semana]}
                  >
                    <p className={`text-[11px] font-semibold uppercase ${ehHoje ? 'text-brand' : 'text-ink-muted'}`}>
                      {NOME_DIA_CURTO[dia.dia_semana]}
                    </p>
                    <p className="text-[10px] text-ink-muted">{formatarDataCurta(dia.data).slice(0, 5)}</p>
                  </div>
                )
              })}
            </div>

            {HORAS_GRADE.map((hora) => (
              <div
                key={hora}
                className="grid border-b border-line-soft last:border-b-0"
                style={{ gridTemplateColumns: COLUNAS_GRADE }}
              >
                <div className="flex items-center justify-end pr-1.5 text-[10px] font-medium text-ink-muted">
                  {hora}
                </div>
                {semana.dias.map((dia) => {
                  const horario = dia.horarios.find((h) => h.hora === hora)
                  const estaSelecionada = selecionada?.data === dia.data && selecionada?.hora === hora
                  return (
                    <CelulaAgenda
                      key={dia.data}
                      horario={horario}
                      ehHoje={dia.data === hojeIso()}
                      selecionada={estaSelecionada}
                      onClick={() =>
                        setSelecionada(
                          estaSelecionada ? null : { data: dia.data, diaSemana: dia.dia_semana, hora }
                        )
                      }
                    />
                  )
                })}
              </div>
            ))}
          </div>
        )}

        {selecionada && semana && (
          <PainelHorario
            selecionada={selecionada}
            horario={semana.dias
              .find((d) => d.data === selecionada.data)
              ?.horarios.find((h) => h.hora === selecionada.hora)}
            students={students}
            onFechar={() => setSelecionada(null)}
            onCriar={(dados) => criarHorario(selecionada.diaSemana, selecionada.hora, dados)}
            onSalvar={(slotId, campos) => salvarOcorrencia(slotId, selecionada.data, campos)}
            onDesativar={desativarSlot}
          />
        )}
      </main>
    </>
  )
}

/**
 * Uma célula da grade: dia x hora. É deliberadamente minúscula — mostra só
 * quem ocupa o horário (ou um "+" quando está livre), porque numa semana
 * inteira lado a lado na tela do celular sobram ~45px por coluna. O que
 * precisa de espaço (trocar aluno, marcar presença) abre no PainelHorario.
 */
function CelulaAgenda({
  horario,
  ehHoje,
  selecionada,
  onClick,
}: {
  horario: HorarioAgenda | undefined
  ehHoje: boolean
  selecionada: boolean
  onClick: () => void
}) {
  const rotulo = horario ? (horario.student ? horario.student.name : horario.titulo ?? 'Vago') : null

  return (
    <button
      onClick={onClick}
      title={rotulo ? `${horario?.hora} — ${rotulo}` : `${horario?.hora ?? ''} livre`}
      className={`min-h-[38px] min-w-0 border-l border-line-soft px-0.5 py-1 text-center transition ${
        selecionada ? 'bg-brand/15 ring-1 ring-inset ring-brand' : ehHoje ? 'bg-brand/5' : 'hover:bg-ink/5'
      }`}
    >
      {rotulo ? (
        <>
          <span
            className={`block truncate text-[10px] font-semibold leading-tight ${
              horario?.student ? 'text-ink' : 'text-ink-muted'
            }`}
          >
            {rotulo}
          </span>
          {(horario?.eh_excecao || horario?.presenca) && (
            <span
              className={`mx-auto mt-0.5 block h-1.5 w-1.5 rounded-full ${
                horario?.presenca === 'presente'
                  ? 'bg-success'
                  : horario?.presenca === 'falta'
                    ? 'bg-danger'
                    : 'bg-warning'
              }`}
            />
          )}
        </>
      ) : (
        <Plus size={11} className="mx-auto text-ink-muted/40" />
      )}
    </button>
  )
}

/**
 * Formulário do horário selecionado, abaixo da grade. Fica aqui e não dentro
 * da célula por espaço: select e input não cabem numa coluna de 45px.
 */
function PainelHorario({
  selecionada,
  horario,
  students,
  onFechar,
  onCriar,
  onSalvar,
  onDesativar,
}: {
  selecionada: { data: string; diaSemana: number; hora: string }
  horario: HorarioAgenda | undefined
  students: Student[]
  onFechar: () => void
  onCriar: (dados: { student_id: string; titulo: string }) => void
  onSalvar: (slotId: string, campos: { student_id?: string | null; titulo?: string | null; presenca?: string | null }) => void
  onDesativar: (slotId: string) => void
}) {
  const [aluno, setAluno] = useState('')
  const [titulo, setTitulo] = useState('')

  return (
    <div className="glass mt-4 rounded-2xl p-4">
      <div className="mb-3 flex items-start justify-between gap-2">
        <div>
          <p className="text-sm font-semibold text-ink">
            {NOME_DIA[selecionada.diaSemana]}, {selecionada.hora}
          </p>
          <p className="text-xs text-ink-muted">{formatarDataCurta(selecionada.data)}</p>
        </div>
        <button onClick={onFechar} aria-label="Fechar" className="text-ink-muted transition hover:text-ink">
          <X size={18} />
        </button>
      </div>

      {!horario ? (
        <form
          onSubmit={(e) => {
            e.preventDefault()
            onCriar({ student_id: aluno, titulo })
            setAluno('')
            setTitulo('')
          }}
          className="space-y-2"
        >
          <select
            value={aluno}
            onChange={(e) => setAluno(e.target.value)}
            className="input-dark w-full rounded-xl px-3 py-2 text-sm"
            autoFocus
          >
            <option value="">Sem aluno (bloqueio pessoal)</option>
            {students.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
          {!aluno && (
            <input
              type="text"
              placeholder="Ou digite um nome/título (substituto sem cadastro, bloqueio pessoal...)"
              value={titulo}
              onChange={(e) => setTitulo(e.target.value)}
              className="input-dark w-full rounded-xl px-3 py-2 text-sm"
            />
          )}
          <button type="submit" className="btn-primary w-full rounded-xl px-4 py-2.5 text-sm">
            Colocar nesse horário
          </button>
          <p className="text-xs text-ink-muted">Esse horário passa a se repetir toda semana.</p>
        </form>
      ) : (
        <HorarioSelecionado
          horario={horario}
          students={students}
          onSalvar={(campos) => onSalvar(horario.slot_id, campos)}
          onDesativar={() => onDesativar(horario.slot_id)}
        />
      )}
    </div>
  )
}

function HorarioSelecionado({
  horario,
  students,
  onSalvar,
  onDesativar,
}: {
  horario: HorarioAgenda
  students: Student[]
  onSalvar: (campos: { student_id?: string | null; titulo?: string | null; presenca?: string | null }) => void
  onDesativar: () => void
}) {
  const [nomeDigitado, setNomeDigitado] = useState(horario.student ? '' : horario.titulo ?? '')

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center gap-1.5">
        <span className="text-sm text-ink-soft">
          {horario.student ? horario.student.name : horario.titulo ?? 'Vago'}
        </span>
        {horario.eh_excecao && (
          <span className="rounded-full bg-warning-soft px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning">
            Trocado só nesse dia
          </span>
        )}
        {horario.presenca && (
          <span
            className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${
              horario.presenca === 'presente' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'
            }`}
          >
            {horario.presenca === 'presente' ? 'Presente' : 'Faltou'}
          </span>
        )}
      </div>

      <select
        defaultValue={horario.student?.id ?? ''}
        onChange={(e) => onSalvar({ student_id: e.target.value || null, titulo: null })}
        className="input-dark w-full rounded-xl px-3 py-2 text-sm"
      >
        <option value="">Vago / digitar nome abaixo</option>
        {students.map((s) => (
          <option key={s.id} value={s.id}>
            {s.name}
          </option>
        ))}
      </select>

      {!horario.student && (
        <form
          onSubmit={(e) => {
            e.preventDefault()
            onSalvar({ student_id: null, titulo: nomeDigitado || null })
          }}
          className="flex gap-1.5"
        >
          <input
            type="text"
            placeholder="Ou digite o nome (substituto avulso, sem cadastro)"
            value={nomeDigitado}
            onChange={(e) => setNomeDigitado(e.target.value)}
            className="input-dark w-full rounded-xl px-3 py-2 text-sm"
          />
          <button type="submit" className="btn-secondary shrink-0 rounded-xl px-3 py-2 text-xs">
            Salvar
          </button>
        </form>
      )}

      {horario.student && (
        <div className="flex gap-2">
          <button
            onClick={() => onSalvar({ presenca: 'presente' })}
            className={`flex-1 rounded-xl px-3 py-2 text-xs font-semibold ${
              horario.presenca === 'presente' ? 'bg-success text-white' : 'glass-hover glass text-ink-soft'
            }`}
          >
            Presente
          </button>
          <button
            onClick={() => onSalvar({ presenca: 'falta' })}
            className={`flex-1 rounded-xl px-3 py-2 text-xs font-semibold ${
              horario.presenca === 'falta' ? 'bg-danger text-white' : 'glass-hover glass text-ink-soft'
            }`}
          >
            Faltou
          </button>
        </div>
      )}

      <button onClick={onDesativar} className="text-xs font-medium text-danger transition hover:underline">
        Remover esse horário fixo da agenda
      </button>
    </div>
  )
}
