'use client'

import { useRef, useState } from 'react'
import { api, ApiError } from '@/lib/api'
import { FormAnalysisResult } from '@/lib/types'

const ESTILO_PRIORIDADE: Record<string, string> = {
  good: 'border-emerald-400/30 bg-emerald-500/8 text-emerald-700',
  warning: 'border-amber-400/30 bg-amber-500/8 text-amber-700',
  critical: 'border-rose-400/30 bg-rose-500/8 text-rose-700',
}

const ICONE_PRIORIDADE: Record<string, string> = {
  good: '🟢',
  warning: '🟡',
  critical: '🔴',
}

export default function FormCorrectionModal({
  open,
  onClose,
  token,
  exerciseId,
  exerciseName,
  workoutId,
}: {
  open: boolean
  onClose: () => void
  token: string
  exerciseId: string
  exerciseName: string
  workoutId?: string
}) {
  const inputRef = useRef<HTMLInputElement | null>(null)
  const [videoFile, setVideoFile] = useState<File | null>(null)
  const [analisando, setAnalisando] = useState(false)
  const [resultado, setResultado] = useState<FormAnalysisResult | null>(null)
  const [erro, setErro] = useState<string | null>(null)

  function fechar() {
    setVideoFile(null)
    setResultado(null)
    setErro(null)
    onClose()
  }

  async function analisar() {
    if (!videoFile) return
    setAnalisando(true)
    setErro(null)
    try {
      const formData = new FormData()
      formData.append('video', videoFile)
      formData.append('exercise_id', exerciseId)
      formData.append('exercise_name', exerciseName)
      if (workoutId) formData.append('workout_id', workoutId)

      const { analysis } = await api.postFile<{ analysis: FormAnalysisResult }>(`/portal/${token}/forma`, formData)
      setResultado(analysis)
      setVideoFile(null)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao analisar o vídeo')
    } finally {
      setAnalisando(false)
    }
  }

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center" onClick={fechar}>
      <div
        onClick={(e) => e.stopPropagation()}
        className="max-h-[85vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:rounded-3xl"
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-bold text-slate-900">Analisar forma</h2>
          <button
            onClick={fechar}
            aria-label="Fechar"
            className="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-900/5 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        {!resultado && (
          <>
            <p className="mb-4 text-sm text-slate-500">
              Filme uma série de <strong className="text-slate-900">{exerciseName}</strong> (5 a 15 segundos,
              qualquer ângulo) e a Coach IA analisa amplitude, postura e velocidade.
            </p>

            <input
              ref={inputRef}
              type="file"
              accept="video/mp4,video/quicktime"
              capture="environment"
              className="hidden"
              onChange={(e) => {
                setErro(null)
                setVideoFile(e.target.files?.[0] ?? null)
              }}
            />

            <button
              onClick={() => inputRef.current?.click()}
              disabled={analisando}
              className="glass glass-hover w-full rounded-xl px-4 py-3 text-sm font-medium text-slate-700"
            >
              {videoFile ? `✓ ${videoFile.name}` : '🎥 Selecionar vídeo'}
            </button>

            {erro && <p className="mt-3 text-sm text-rose-500">{erro}</p>}

            <button
              onClick={analisar}
              disabled={!videoFile || analisando}
              className="btn-primary mt-4 w-full rounded-xl px-4 py-3 text-sm disabled:opacity-50"
            >
              {analisando ? 'Analisando (20-30s)...' : 'Analisar forma'}
            </button>
          </>
        )}

        {resultado && (
          <>
            <div className="space-y-3">
              {resultado.three_key_feedback.map((item, i) => (
                <div key={i} className={`rounded-2xl border p-4 ${ESTILO_PRIORIDADE[item.priority] ?? ''}`}>
                  <p className="mb-1 text-sm font-semibold">
                    {ICONE_PRIORIDADE[item.priority] ?? ''} {item.title}
                  </p>
                  <p className="text-sm text-slate-700">{item.feedback}</p>
                </div>
              ))}
            </div>

            {resultado.safety_notes && resultado.safety_notes !== 'Seguro' && (
              <div className="mt-3 rounded-2xl border border-amber-400/30 bg-amber-500/8 p-4">
                <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                  Observação de segurança
                </p>
                <p className="text-sm text-slate-700">{resultado.safety_notes}</p>
              </div>
            )}

            <button
              onClick={() => setResultado(null)}
              className="glass glass-hover mt-4 w-full rounded-xl px-4 py-3 text-sm font-medium text-slate-700"
            >
              ← Analisar outro vídeo
            </button>
          </>
        )}
      </div>
    </div>
  )
}
