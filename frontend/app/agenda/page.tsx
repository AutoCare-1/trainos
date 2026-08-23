'use client'

import { useCallback, useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { ChevronLeft, ChevronRight, Plus, X } from 'lucide-react'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { formatarDataCurta, somarDias } from '@/lib/checkinDates'
import { DiaAgenda, HorarioAgenda, SemanaAgenda, Student } from '@/lib/types'

const NOME_DIA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']
const NOME_DIA_CURTO = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']
// "domingo"/"sábado" são masculinos ("no domingo"), os demais dias são
// femininos ("na segunda") — só pro aria-label ficar gramaticalmente certo.
const PREPOSICAO_DIA = ['no', 'na', 'na', 'na', 'na', 'na', 'no']

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
  const [novoAberto, setNovoAberto] = useState<number | null>(null) // dia_semana do form aberto
  const [editando, setEditando] = useState<string | null>(null) // `${slot_id}|${data}` da linha em edição

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

  async function criarHorario(diaSemana: number, dados: { student_id: string; titulo: string; hora: string; duracao_minutos: string }) {
    setErro(null)
    try {
      await api.post('/agenda/horarios', {
        student_id: dados.student_id || null,
        titulo: dados.titulo || null,
        dia_semana: diaSemana,
        hora: dados.hora,
        duracao_minutos: dados.duracao_minutos ? Number(dados.duracao_minutos) : undefined,
      })
      setNovoAberto(null)
      carregarSemana(refSemana)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao criar horário')
    }
  }

  async function salvarOcorrencia(
    slotId: string,
    data: string,
    campos: { student_id?: string | null; presenca?: string | null }
  ) {
    setErro(null)
    try {
      await api.patch(`/agenda/horarios/${slotId}/ocorrencias`, { data, ...campos })
      setEditando(null)
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
            Seus horários fixos da semana — mude quem ocupa um horário só numa data, sem afetar as próximas.
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

        <div className="chat-scroll -mx-4 overflow-x-auto px-4 pb-2">
          <div className="grid grid-cols-7 gap-3" style={{ minWidth: '980px' }}>
            {semana?.dias.map((dia) => (
              <DiaCard
                key={dia.data}
                dia={dia}
                students={students}
                hoje={dia.data === hojeIso()}
                novoAberto={novoAberto === dia.dia_semana}
                onAbrirNovo={() => setNovoAberto(novoAberto === dia.dia_semana ? null : dia.dia_semana)}
                onCriarHorario={(dados) => criarHorario(dia.dia_semana, dados)}
                editando={editando}
                onEditar={setEditando}
                onSalvarOcorrencia={(slotId, campos) => salvarOcorrencia(slotId, dia.data, campos)}
                onDesativarSlot={desativarSlot}
              />
            ))}
          </div>
        </div>
      </main>
    </>
  )
}

function DiaCard({
  dia,
  students,
  hoje,
  novoAberto,
  onAbrirNovo,
  onCriarHorario,
  editando,
  onEditar,
  onSalvarOcorrencia,
  onDesativarSlot,
}: {
  dia: DiaAgenda
  students: Student[]
  hoje: boolean
  novoAberto: boolean
  onAbrirNovo: () => void
  onCriarHorario: (dados: { student_id: string; titulo: string; hora: string; duracao_minutos: string }) => void
  editando: string | null
  onEditar: (chave: string | null) => void
  onSalvarOcorrencia: (slotId: string, campos: { student_id?: string | null; presenca?: string | null }) => void
  onDesativarSlot: (slotId: string) => void
}) {
  const [novoAluno, setNovoAluno] = useState('')
  const [novoTitulo, setNovoTitulo] = useState('')
  const [novaHora, setNovaHora] = useState('')
  const [novaDuracao, setNovaDuracao] = useState('')

  return (
    <div className={`glass min-w-0 rounded-2xl p-3 ${hoje ? 'border-brand/40' : ''}`} title={NOME_DIA[dia.dia_semana]}>
      <div className="mb-2 flex items-start justify-between gap-1">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">{NOME_DIA_CURTO[dia.dia_semana]}</p>
          <p className="text-xs text-ink-muted">{formatarDataCurta(dia.data)}</p>
        </div>
        <button
          onClick={onAbrirNovo}
          aria-label={`Novo horário ${PREPOSICAO_DIA[dia.dia_semana]} ${NOME_DIA[dia.dia_semana]}`}
          className="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-brand transition hover:bg-brand/10"
        >
          <Plus size={14} />
        </button>
      </div>

      {dia.horarios.length === 0 && !novoAberto && (
        <p className="text-xs text-ink-muted">Nada marcado.</p>
      )}

      <div className="space-y-2">
        {dia.horarios.map((h) => (
          <HorarioRow
            key={h.slot_id}
            horario={h}
            students={students}
            emEdicao={editando === `${h.slot_id}|${dia.data}`}
            onEditar={() => onEditar(editando === `${h.slot_id}|${dia.data}` ? null : `${h.slot_id}|${dia.data}`)}
            onSalvar={(campos) => onSalvarOcorrencia(h.slot_id, campos)}
            onDesativar={() => onDesativarSlot(h.slot_id)}
          />
        ))}
      </div>

      {novoAberto && (
        <form
          onSubmit={(e) => {
            e.preventDefault()
            onCriarHorario({ student_id: novoAluno, titulo: novoTitulo, hora: novaHora, duracao_minutos: novaDuracao })
            setNovoAluno('')
            setNovoTitulo('')
            setNovaHora('')
            setNovaDuracao('')
          }}
          className="mt-3 space-y-2 border-t border-line-soft pt-3"
        >
          <select
            value={novoAluno}
            onChange={(e) => setNovoAluno(e.target.value)}
            className="input-dark w-full rounded-xl px-3 py-2 text-sm"
          >
            <option value="">Sem aluno (bloqueio pessoal)</option>
            {students.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
          <input
            type="text"
            placeholder="Título (ex: Revisão de consultoria) — opcional se escolheu aluno"
            value={novoTitulo}
            onChange={(e) => setNovoTitulo(e.target.value)}
            className="input-dark w-full rounded-xl px-3 py-2 text-sm"
          />
          <div className="flex gap-2">
            <input
              type="time"
              required
              value={novaHora}
              onChange={(e) => setNovaHora(e.target.value)}
              className="input-dark w-full rounded-xl px-3 py-2 text-sm"
            />
            <input
              type="number"
              min={15}
              step={15}
              placeholder="60 min"
              value={novaDuracao}
              onChange={(e) => setNovaDuracao(e.target.value)}
              className="input-dark w-full rounded-xl px-3 py-2 text-sm"
            />
          </div>
          <button type="submit" className="btn-secondary w-full rounded-xl px-4 py-2 text-sm">
            Salvar horário fixo
          </button>
        </form>
      )}
    </div>
  )
}

function HorarioRow({
  horario,
  students,
  emEdicao,
  onEditar,
  onSalvar,
  onDesativar,
}: {
  horario: HorarioAgenda
  students: Student[]
  emEdicao: boolean
  onEditar: () => void
  onSalvar: (campos: { student_id?: string | null; presenca?: string | null }) => void
  onDesativar: () => void
}) {
  const rotulo = horario.student ? horario.student.name : horario.titulo ?? 'Vago'

  return (
    <div className="glass-flat rounded-xl p-2.5">
      <button onClick={onEditar} className="flex w-full items-start justify-between gap-1 text-left">
        <div className="min-w-0">
          <p className="text-xs font-semibold text-ink">{horario.hora}</p>
          <p className="truncate text-xs text-ink-soft">{rotulo}</p>
          <div className="mt-1 flex flex-wrap items-center gap-1">
            {horario.eh_excecao && (
              <span className="rounded-full bg-warning-soft px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-warning">
                Trocado
              </span>
            )}
            {horario.presenca && (
              <span
                className={`rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide ${
                  horario.presenca === 'presente' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'
                }`}
              >
                {horario.presenca === 'presente' ? 'Presente' : 'Faltou'}
              </span>
            )}
          </div>
        </div>
        {emEdicao ? <X size={16} className="shrink-0 text-ink-muted" /> : null}
      </button>

      {emEdicao && (
        <div className="mt-3 space-y-2 border-t border-line-soft pt-3">
          <select
            defaultValue={horario.student?.id ?? ''}
            onChange={(e) => onSalvar({ student_id: e.target.value || null })}
            className="input-dark w-full rounded-xl px-3 py-2 text-sm"
          >
            <option value="">Vago (ninguém nesse dia)</option>
            {students.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>

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
      )}
    </div>
  )
}
