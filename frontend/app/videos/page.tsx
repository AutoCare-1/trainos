'use client'

import { useEffect, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import { api, ApiError } from '@/lib/api'
import { Exercise } from '@/lib/types'

export default function VideosPage() {
  const router = useRouter()
  const [exercises, setExercises] = useState<Exercise[] | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState<string | null>(null)
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

  const porGrupo = (exercises ?? []).reduce<Record<string, Exercise[]>>((acc, ex) => {
    acc[ex.muscle_group] = acc[ex.muscle_group] ?? []
    acc[ex.muscle_group].push(ex)
    return acc
  }, {})

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <BackLink href="/dashboard" label="Voltar ao painel" />
        <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Vídeos dos exercícios</h1>
        <p className="mb-6 text-sm text-slate-500">
          Envie ou grave seu próprio vídeo de demonstração pra qualquer exercício. Ele substitui o padrão só para os
          seus alunos — os outros profissionais continuam vendo o vídeo original.
        </p>

        {erro && <p className="mb-4 text-sm text-rose-500">{erro}</p>}
        {exercises === null && !erro && <p className="text-slate-500">Carregando...</p>}

        <div className="space-y-6">
          {Object.entries(porGrupo).map(([grupo, itens]) => (
            <div key={grupo}>
              <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{grupo}</h2>
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
                      className="shrink-0 rounded-xl text-[#2648b3]"
                    />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium text-slate-900">{ex.name}</p>
                      <p className="text-xs text-slate-500">
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
                          className="glass glass-hover rounded-xl px-3 py-2 text-xs font-medium text-slate-700"
                        >
                          {enviando === ex.id ? 'Enviando...' : 'Gravar'}
                        </button>
                        <button
                          onClick={() => inputsGaleria.current[ex.id]?.click()}
                          disabled={enviando === ex.id}
                          className="glass glass-hover rounded-xl px-3 py-2 text-xs font-medium text-slate-700"
                        >
                          Galeria
                        </button>
                      </div>
                      {ex.video_customizado && (
                        <button
                          onClick={() => restaurarPadrao(ex.id)}
                          disabled={enviando === ex.id}
                          className="text-xs text-rose-500 transition hover:text-rose-600"
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
