'use client'

import { useEffect, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import FiltroGrupoMuscular from '@/components/FiltroGrupoMuscular'
import { contarPorGrupo, filtrarEAgrupar } from '@/lib/bibliotecaExercicios'
import { api, ApiError } from '@/lib/api'
import { Exercise } from '@/lib/types'

export default function VideosPage() {
  const router = useRouter()
  const [exercises, setExercises] = useState<Exercise[] | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState<string | null>(null)
  const [buscaExercicio, setBuscaExercicio] = useState('')
  const [grupoMuscular, setGrupoMuscular] = useState<string | null>(null)
  const inputsGravar = useRef<Record<string, HTMLInputElement | null>>({})
  const inputsGaleria = useRef<Record<string, HTMLInputElement | null>>({})

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ exercises: Exercise[] }>('/exercicios')
      .then((data) => setExercises(data.exercises))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar exercícios'))
  }, [router])

  async function enviarVideo(exerciseId: string, file: File) {
    setErro(null)
    setEnviando(exerciseId)
    try {
      const formData = new FormData()
      formData.append('video', file)
      const { override } = await api.postFile<{ override: { video_url: string } }>(
        `/exercicios/${exerciseId}/video`,
        formData
      )
      setExercises(
        (prev) =>
          prev?.map((ex) =>
            ex.id === exerciseId ? { ...ex, video_url: override.video_url, video_customizado: true } : ex
          ) ?? null
      )
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao enviar vídeo')
    } finally {
      setEnviando(null)
    }
  }

  async function restaurarPadrao(exerciseId: string) {
    setErro(null)
    setEnviando(exerciseId)
    try {
      await api.delete(`/exercicios/${exerciseId}/video`)
      const { exercises: atualizado } = await api.get<{ exercises: Exercise[] }>('/exercicios')
      setExercises(atualizado)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao restaurar vídeo padrão')
    } finally {
      setEnviando(null)
    }
  }

  const gruposDisponiveis = contarPorGrupo(exercises ?? [])
  const gruposFiltrados = filtrarEAgrupar(exercises ?? [], buscaExercicio, grupoMuscular)

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <BackLink href="/dashboard" label="Voltar ao painel" />
        <h1 className="mb-1 font-display text-2xl font-bold tracking-tight text-ink">Vídeos dos exercícios</h1>
        <p className="mb-6 text-sm text-ink-muted">
          Envie ou grave seu próprio vídeo de demonstração pra qualquer exercício. Ele substitui o padrão só para os
          seus alunos — os outros profissionais continuam vendo o vídeo original.
        </p>

        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}
        {exercises === null && !erro && <p className="text-ink-muted">Carregando...</p>}

        {exercises !== null && (
          <input
            type="text"
            value={buscaExercicio}
            onChange={(e) => setBuscaExercicio(e.target.value)}
            placeholder="Buscar por nome ou equipamento..."
            className="input-dark mb-3 w-full rounded-xl px-4 py-2.5 text-sm"
          />
        )}

        {exercises !== null && (
          <FiltroGrupoMuscular
            grupos={gruposDisponiveis}
            selecionado={grupoMuscular}
            onSelecionar={setGrupoMuscular}
            total={exercises.length}
          />
        )}

        <div className="space-y-6">
          {exercises !== null && gruposFiltrados.length === 0 && (
            <p className="text-sm text-ink-muted">Nenhum exercício encontrado.</p>
          )}
          {gruposFiltrados.map(({ grupo, itens }) => (
            <div key={grupo}>
              <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">{grupo}</h2>
              <div className="space-y-2">
                {itens.map((ex) => (
                  <div key={ex.id} className="glass flex items-center gap-3 rounded-2xl p-3">
                    <ExerciseAnimation
                      name={ex.name}
                      muscleGroup={ex.muscle_group}
                      imageUrl={ex.image_url}
                      imageCredit={ex.image_credit}
                      videoUrl={ex.video_url}
                      size="md"
                      className="shrink-0 rounded-xl text-brand"
                    />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium text-ink">{ex.name}</p>
                      <p className="text-xs text-ink-muted">
                        {ex.video_customizado ? 'Vídeo personalizado' : 'Vídeo/imagem padrão'}
                      </p>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1.5">
                      {/* dispara a câmera direto no celular */}
                      <input
                        ref={(el) => {
                          inputsGravar.current[ex.id] = el
                        }}
                        type="file"
                        accept="video/*"
                        capture="environment"
                        className="hidden"
                        onChange={(e) => {
                          const file = e.target.files?.[0]
                          if (file) enviarVideo(ex.id, file)
                          e.target.value = ''
                        }}
                      />
                      {/* sem "capture": abre a galeria/fototeca do celular (ou arquivos, no desktop) */}
                      <input
                        ref={(el) => {
                          inputsGaleria.current[ex.id] = el
                        }}
                        type="file"
                        accept="video/*"
                        className="hidden"
                        onChange={(e) => {
                          const file = e.target.files?.[0]
                          if (file) enviarVideo(ex.id, file)
                          e.target.value = ''
                        }}
                      />
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => inputsGravar.current[ex.id]?.click()}
                          disabled={enviando === ex.id}
                          className="glass glass-hover rounded-xl px-3 py-2 text-xs font-medium text-ink-soft"
                        >
                          {enviando === ex.id ? 'Enviando...' : 'Gravar'}
                        </button>
                        <button
                          onClick={() => inputsGaleria.current[ex.id]?.click()}
                          disabled={enviando === ex.id}
                          className="glass glass-hover rounded-xl px-3 py-2 text-xs font-medium text-ink-soft"
                        >
                          Galeria
                        </button>
                      </div>
                      {ex.video_customizado && (
                        <button
                          onClick={() => restaurarPadrao(ex.id)}
                          disabled={enviando === ex.id}
                          className="text-xs text-danger transition hover:text-danger"
                        >
                          Restaurar padrão
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </main>
    </>
  )
}
