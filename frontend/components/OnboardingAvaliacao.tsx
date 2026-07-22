'use client'

import { useRef, useState } from 'react'
import Image from 'next/image'
import Avatar from '@/components/Avatar'
import { PAR_Q_PERGUNTAS, PAR_Q_VAZIO } from '@/lib/parq'
import { ParQAnswers } from '@/lib/types'

export default function OnboardingAvaliacao({
  nome,
  onEnviar,
}: {
  nome: string
  onEnviar: (parQ: ParQAnswers, healthNotes: string, foto: File | null) => Promise<void>
}) {
  const [parQ, setParQ] = useState<ParQAnswers>(PAR_Q_VAZIO)
  const [healthNotes, setHealthNotes] = useState('')
  const [foto, setFoto] = useState<File | null>(null)
  const [fotoPreview, setFotoPreview] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const fotoInputRef = useRef<HTMLInputElement | null>(null)

  function escolherFoto(file: File) {
    setFoto(file)
    setFotoPreview(URL.createObjectURL(file))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setEnviando(true)
    setErro(null)
    try {
      await onEnviar(parQ, healthNotes, foto)
    } catch {
      setErro('Não foi possível enviar. Tente de novo.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <main className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
      <div className="w-full max-w-md">
        <div className="mb-6 flex flex-col items-center text-center">
          <Image src="/clubemais-logo.png" alt="Clube Mais" width={200} height={56} priority className="h-12 w-auto" />
          <h1 className="mt-5 text-xl font-bold text-slate-900">Oi, {nome.split(' ')[0]}!</h1>
          <p className="mt-2 text-sm text-slate-500">
            Antes de ver seu treino, responda essas perguntas rápidas de segurança — leva menos de 1 minuto.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="glass space-y-4 rounded-2xl p-6">
          <div className="flex flex-col items-center gap-2">
            {fotoPreview ? (
              // eslint-disable-next-line @next/next/no-img-element -- preview local, ainda não é URL do backend
              <img src={fotoPreview} alt="Sua foto" className="h-20 w-20 rounded-full object-cover" />
            ) : (
              <Avatar nome={nome} tamanho="lg" />
            )}
            <button
              type="button"
              onClick={() => fotoInputRef.current?.click()}
              className="text-xs font-medium text-[#2648b3]"
            >
              {fotoPreview ? 'Trocar foto' : 'Adicionar sua foto (opcional)'}
            </button>
            <input
              ref={fotoInputRef}
              type="file"
              accept="image/*"
              capture="user"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) escolherFoto(file)
              }}
            />
          </div>

          <div className="space-y-2.5">
            {PAR_Q_PERGUNTAS.map(({ chave, texto }) => (
              <label key={chave} className="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={parQ[chave]}
                  onChange={(e) => setParQ({ ...parQ, [chave]: e.target.checked })}
                  className="mt-0.5 h-4 w-4 shrink-0 accent-[#2648b3]"
                />
                {texto}
              </label>
            ))}
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">
              Alguma cirurgia, medicamento ou observação? (opcional)
            </label>
            <textarea
              value={healthNotes}
              onChange={(e) => setHealthNotes(e.target.value)}
              rows={2}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          {erro && <p className="text-sm text-rose-500">{erro}</p>}

          <button type="submit" disabled={enviando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {enviando ? 'Enviando...' : 'Continuar'}
          </button>
        </form>
      </div>
    </main>
  )
}
