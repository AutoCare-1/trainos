'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { ContentFormat, ContentIdea } from '@/lib/types'

const FORMATO_LABEL: Record<ContentFormat, string> = {
  post: 'Post',
  story: 'Story',
  reels: 'Reels',
}

const FORMATO_ESTILO: Record<ContentFormat, string> = {
  post: 'bg-[#2648b3]/10 text-[#2648b3]',
  story: 'bg-violet-500/10 text-violet-600',
  reels: 'bg-emerald-500/10 text-emerald-600',
}

function BookmarkIcon({ preenchido }: { preenchido: boolean }) {
  return (
    <svg
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill={preenchido ? 'currentColor' : 'none'}
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
    </svg>
  )
}

export default function ConteudoPage() {
  const router = useRouter()
  const [ideias, setIdeias] = useState<ContentIdea[] | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [gerando, setGerando] = useState(false)
  const [direcionamento, setDirecionamento] = useState('')
  const [filtroFormato, setFiltroFormato] = useState<'todos' | ContentFormat>('todos')

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ ideas: ContentIdea[] }>('/conteudo')
      .then((data) => setIdeias(data.ideas))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar ideias'))
  }, [router])

  async function gerarIdeias() {
    setGerando(true)
    setErro(null)
    try {
      const { ideas } = await api.post<{ ideas: ContentIdea[] }>('/conteudo', {
        direcionamento: direcionamento.trim() || undefined,
      })
      setIdeias((prev) => [...ideas, ...(prev ?? [])])
      setDirecionamento('')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao gerar ideias')
    } finally {
      setGerando(false)
    }
  }

  async function alternarFavorito(idea: ContentIdea) {
    const novoValor = !idea.saved
    setIdeias((prev) => prev?.map((i) => (i.id === idea.id ? { ...i, saved: novoValor } : i)) ?? prev)
    try {
      await api.patch(`/conteudo/${idea.id}`, { saved: novoValor })
    } catch {
      setIdeias((prev) => prev?.map((i) => (i.id === idea.id ? { ...i, saved: !novoValor } : i)) ?? prev)
    }
  }

  const ideiasFiltradas = ideias?.filter((i) => filtroFormato === 'todos' || i.format === filtroFormato) ?? []

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <div className="mb-6">
          <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Conteúdo</h1>
          <p className="text-sm text-slate-500">
            Ideias de post, story e reels pro seu Instagram — cada ideia já junta uma tendência de
            formato em alta com um dado real (e anônimo) da sua base de alunos.
          </p>
        </div>

        <div className="glass mb-6 rounded-2xl p-5">
          <label className="mb-1.5 block text-xs text-slate-500">Direcionamento (opcional)</label>
          <div className="flex flex-col gap-2 sm:flex-row">
            <input
              type="text"
              value={direcionamento}
              onChange={(e) => setDirecionamento(e.target.value)}
              placeholder="Ex: quero ideias sobre hipertrofia"
              className="input-dark flex-1 rounded-xl px-4 py-2.5 text-sm"
            />
            <button
              onClick={gerarIdeias}
              disabled={gerando}
              className="btn-primary shrink-0 rounded-xl px-5 py-2.5 text-sm"
            >
              {gerando ? 'Gerando...' : 'Gerar ideias'}
            </button>
          </div>
          {erro && <p className="mt-3 text-sm text-rose-500">{erro}</p>}
        </div>

        <div className="mb-4 flex gap-1 rounded-lg bg-slate-900/5 p-1 w-fit">
          {(['todos', 'post', 'story', 'reels'] as const).map((f) => (
            <button
              key={f}
              onClick={() => setFiltroFormato(f)}
              className={`rounded-md px-3 py-1.5 text-xs font-medium transition ${
                filtroFormato === f ? 'bg-white text-slate-900 shadow' : 'text-slate-500'
              }`}
            >
              {f === 'todos' ? 'Todos' : FORMATO_LABEL[f]}
            </button>
          ))}
        </div>

        {ideias === null && !erro && <p className="text-slate-500">Carregando...</p>}

        {ideias !== null && ideiasFiltradas.length === 0 && (
          <div className="glass rounded-2xl border-dashed p-10 text-center">
            <p className="text-slate-500">Nenhuma ideia por aqui ainda.</p>
            <p className="mt-1 text-sm text-slate-400">Clique em &quot;Gerar ideias&quot; pra começar.</p>
          </div>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          {ideiasFiltradas.map((idea) => (
            <div key={idea.id} className="glass rounded-2xl p-5">
              <div className="mb-2 flex items-start justify-between gap-2">
                <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${FORMATO_ESTILO[idea.format]}`}>
                  {FORMATO_LABEL[idea.format]}
                </span>
                <button
                  onClick={() => alternarFavorito(idea)}
                  aria-label={idea.saved ? 'Remover dos favoritos' : 'Favoritar'}
                  className={`shrink-0 transition ${idea.saved ? 'text-amber-500' : 'text-slate-300 hover:text-slate-400'}`}
                >
                  <BookmarkIcon preenchido={idea.saved} />
                </button>
              </div>
              <h2 className="mb-1.5 font-semibold text-slate-900">{idea.title}</h2>
              <p className="mb-3 text-sm text-slate-600">{idea.description}</p>
              <div className="rounded-xl bg-slate-900/3 p-3">
                <p className="mb-1 text-[10px] uppercase tracking-wider text-slate-400">Legenda sugerida</p>
                <p className="text-sm text-slate-700">{idea.caption_suggestion}</p>
              </div>
            </div>
          ))}
        </div>
      </main>
    </>
  )
}
