'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { TipoNotificacao } from '@/lib/types'

const ROTULO_CATEGORIA: Record<string, string> = {
  habito: 'Hábito e engajamento',
  celebracao: 'Celebração e gamificação',
  informativo: 'Informativo',
  gestao: 'Gestão',
}

const ORDEM_CATEGORIA = ['habito', 'celebracao', 'informativo', 'gestao']

export default function NotificacoesPage() {
  const router = useRouter()
  const [categorias, setCategorias] = useState<Record<string, TipoNotificacao[]>>({})
  const [carregando, setCarregando] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [salvando, setSalvando] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ categorias: Record<string, TipoNotificacao[]> }>('/notificacoes/tipos')
      .then((data) => setCategorias(data.categorias))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar notificações'))
      .finally(() => setCarregando(false))
  }, [router])

  async function alternar(tipo: TipoNotificacao) {
    const novo = !tipo.enabled
    setCategorias((prev) => ({
      ...prev,
      [tipo.categoria]: prev[tipo.categoria].map((t) => (t.chave === tipo.chave ? { ...t, enabled: novo } : t)),
    }))
    setSalvando(tipo.chave)
    try {
      await api.patch(`/notificacoes/tipos/${tipo.chave}`, { enabled: novo })
    } catch {
      setCategorias((prev) => ({
        ...prev,
        [tipo.categoria]: prev[tipo.categoria].map((t) => (t.chave === tipo.chave ? { ...t, enabled: !novo } : t)),
      }))
    } finally {
      setSalvando(null)
    }
  }

  const categoriasOrdenadas = ORDEM_CATEGORIA.filter((c) => categorias[c]?.length)

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Notificações</h1>
          <p className="mt-1 text-sm text-slate-500">
            Escolha quais avisos os alunos (e você) recebem por push. Isso vale pra todos os alunos por enquanto.
          </p>
        </div>

        {carregando && <p className="text-slate-500">Carregando...</p>}
        {erro && <p className="text-sm text-rose-500">{erro}</p>}

        {categoriasOrdenadas.map((categoria) => (
          <div key={categoria} className="mb-6">
            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
              {ROTULO_CATEGORIA[categoria] ?? categoria}
            </h2>
            <div className="glass divide-y divide-black/6 rounded-2xl">
              {categorias[categoria].map((tipo) => (
                <div key={tipo.chave} className="flex items-center justify-between gap-4 p-4">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="font-medium text-slate-900">{tipo.nome}</p>
                      <span className="shrink-0 rounded-full bg-slate-900/6 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        {tipo.publico === 'aluno' ? 'Aluno' : 'Você'}
                      </span>
                    </div>
                    {tipo.descricao && <p className="mt-0.5 text-sm text-slate-500">{tipo.descricao}</p>}
                  </div>
                  <button
                    onClick={() => alternar(tipo)}
                    disabled={salvando === tipo.chave}
                    aria-pressed={tipo.enabled}
                    className={`flex shrink-0 items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-medium transition ${
                      tipo.enabled
                        ? 'border border-violet-300 bg-violet-50 text-violet-700'
                        : 'border border-black/10 bg-black/4 text-slate-500'
                    }`}
                  >
                    {tipo.enabled ? 'Ativado' : 'Desativado'}
                  </button>
                </div>
              ))}
            </div>
          </div>
        ))}
      </main>
    </>
  )
}
