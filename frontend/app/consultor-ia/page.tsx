'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import ChatBox, { ChatMessage } from '@/components/ChatBox'
import { api, ApiError } from '@/lib/api'
import { ConsultorIaMessage } from '@/lib/types'

function paraMensagemChat(m: ConsultorIaMessage): ChatMessage {
  return {
    id: m.id,
    sender: m.role === 'personal' ? 'student' : 'ai',
    content: m.content,
    created_at: m.created_at,
  }
}

export default function ConsultorIaPage() {
  const router = useRouter()
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [carregando, setCarregando] = useState(true)
  const [aguardando, setAguardando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ messages: ConsultorIaMessage[] }>('/consultor-ia')
      .then((data) => setMessages(data.messages.map(paraMensagemChat)))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar histórico'))
      .finally(() => setCarregando(false))
  }, [router])

  async function enviarMensagem(texto: string) {
    setAguardando(true)
    setErro(null)
    try {
      const resp = await api.post<{ message: ConsultorIaMessage; aiReply: ConsultorIaMessage }>('/consultor-ia/chat', {
        content: texto,
      })
      setMessages((prev) => [...prev, paraMensagemChat(resp.message), paraMensagemChat(resp.aiReply)])
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao falar com o consultor')
    } finally {
      setAguardando(false)
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 py-8">
        <div className="mb-4">
          <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Consultor IA</h1>
          <p className="text-sm text-slate-500">
            Pergunte sobre sua base de alunos em linguagem natural — ex: &quot;quem não fez check-in essa
            semana?&quot;, &quot;quem bateu PR recentemente?&quot;, &quot;como está o João?&quot;.
          </p>
        </div>

        {erro && <p className="mb-3 text-sm text-rose-500">{erro}</p>}

        <div className="glass flex flex-1 flex-col overflow-hidden rounded-2xl" style={{ minHeight: '32rem' }}>
          {carregando ? (
            <p className="p-6 text-sm text-slate-500">Carregando...</p>
          ) : (
            <ChatBox
              messages={messages}
              perspective="student"
              onSend={enviarMensagem}
              aguardandoIa={aguardando}
              placeholder="Pergunte sobre seus alunos..."
              vazioTexto="Nenhuma conversa ainda. Pergunte algo sobre seus alunos pra começar."
              nomeIa="Consultor IA"
            />
          )}
        </div>
      </main>
    </>
  )
}
