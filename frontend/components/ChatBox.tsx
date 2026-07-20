'use client'

import { useEffect, useRef, useState } from 'react'

export interface ChatMessage {
  id: string
  sender: 'student' | 'professional' | 'ai'
  content: string
  created_at: string
}

interface ChatBoxProps {
  messages: ChatMessage[]
  perspective: 'student' | 'professional'
  onSend: (texto: string) => Promise<void>
  placeholder?: string
  aguardandoIa?: boolean
  vazioTexto?: string
}

function hora(iso: string): string {
  return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

export default function ChatBox({
  messages,
  perspective,
  onSend,
  placeholder = 'Escreva sua mensagem...',
  aguardandoIa = false,
  vazioTexto = 'Nenhuma mensagem ainda. Comece a conversa!',
}: ChatBoxProps) {
  const [texto, setTexto] = useState('')
  const [enviando, setEnviando] = useState(false)
  const fimRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    fimRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages.length, aguardandoIa])

  async function enviar(e: React.FormEvent) {
    e.preventDefault()
    const conteudo = texto.trim()
    if (!conteudo || enviando) return
    setEnviando(true)
    setTexto('')
    try {
      await onSend(conteudo)
    } finally {
      setEnviando(false)
    }
  }

  // Do lado de quem olha, as próprias mensagens ficam à direita.
  // No painel do profissional, a IA também fica à direita (ela fala pelo "lado do coach"),
  // mas com visual próprio pra ficar claro que foi resposta automática.
  function ladoDireito(sender: ChatMessage['sender']): boolean {
    if (perspective === 'student') return sender === 'student'
    return sender === 'professional' || sender === 'ai'
  }

  return (
    <div className="flex flex-col h-full">
      <div className="chat-scroll flex-1 space-y-3 overflow-y-auto p-4">
        {messages.length === 0 && !aguardandoIa && (
          <p className="py-8 text-center text-sm text-slate-500">{vazioTexto}</p>
        )}

        {messages.map((m) => {
          const direita = ladoDireito(m.sender)
          const ehIa = m.sender === 'ai'
          return (
            <div key={m.id} className={`flex ${direita ? 'justify-end' : 'justify-start'}`}>
              <div
                className={`max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed ${
                  direita
                    ? ehIa
                      ? 'rounded-br-md border border-violet-400/25 bg-violet-500/15 text-violet-50'
                      : 'rounded-br-md bg-gradient-to-br from-emerald-500 to-cyan-600 text-[#04110d]'
                    : ehIa
                      ? 'rounded-bl-md border border-violet-400/25 bg-violet-500/15 text-violet-50'
                      : 'rounded-bl-md glass text-slate-100'
                }`}
              >
                {ehIa && (
                  <span className="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-violet-300">
                    ✦ Coach IA
                  </span>
                )}
                {!ehIa && m.sender === 'professional' && perspective === 'student' && (
                  <span className="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-300">
                    Seu professor
                  </span>
                )}
                <p className="whitespace-pre-wrap">{m.content}</p>
                <p className={`mt-1 text-right text-[10px] ${direita && !ehIa ? 'text-[#04110d]/60' : 'text-slate-500'}`}>
                  {hora(m.created_at)}
                </p>
              </div>
            </div>
          )
        })}

        {aguardandoIa && (
          <div className="flex justify-start">
            <div className="glass flex items-center gap-1.5 rounded-2xl rounded-bl-md px-4 py-3">
              <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-300 [animation-delay:0ms]" />
              <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-300 [animation-delay:150ms]" />
              <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-300 [animation-delay:300ms]" />
            </div>
          </div>
        )}
        <div ref={fimRef} />
      </div>

      <form onSubmit={enviar} className="flex gap-2 border-t border-white/8 p-3">
        <input
          type="text"
          value={texto}
          onChange={(e) => setTexto(e.target.value)}
          placeholder={placeholder}
          className="input-dark flex-1 rounded-xl px-4 py-2.5 text-sm"
        />
        <button
          type="submit"
          disabled={enviando || !texto.trim()}
          className="btn-primary rounded-xl px-4 py-2.5 text-sm"
        >
          {enviando ? '...' : 'Enviar'}
        </button>
      </form>
    </div>
  )
}
