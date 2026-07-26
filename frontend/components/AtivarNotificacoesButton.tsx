'use client'

import { useEffect, useState } from 'react'
import { ativarPush, estaInstalado, pushSuportado, statusPermissao } from '@/lib/push'

/**
 * Só aparece quando o app está instalado (Web Push não funciona no navegador
 * comum, principalmente no iOS) e a permissão ainda não foi decidida — some
 * sozinho depois de ativado ou negado, sem ficar insistindo.
 */
export default function AtivarNotificacoesButton({ caminhoSubscribe }: { caminhoSubscribe: string }) {
  const [visivel, setVisivel] = useState(false)
  const [ativando, setAtivando] = useState(false)
  const [mensagem, setMensagem] = useState<string | null>(null)

  useEffect(() => {
    setVisivel(estaInstalado() && pushSuportado() && statusPermissao() === 'default')
  }, [])

  async function ativar() {
    setAtivando(true)
    setMensagem(null)
    const resultado = await ativarPush(caminhoSubscribe)
    setAtivando(false)
    if (resultado.ok) {
      setVisivel(false)
    } else {
      setMensagem(resultado.motivo)
      setVisivel(statusPermissao() === 'default')
    }
  }

  if (!visivel) return null

  return (
    <div className="glass mb-4 flex items-center justify-between gap-3 rounded-2xl p-4">
      <div>
        <p className="text-sm font-semibold text-slate-900">Ativar notificações</p>
        <p className="text-xs text-slate-500">Receba avisos de treino, mensagens e conquistas direto no celular.</p>
        {mensagem && <p className="mt-1 text-xs text-rose-500">{mensagem}</p>}
      </div>
      <button onClick={ativar} disabled={ativando} className="btn-primary shrink-0 rounded-xl px-4 py-2 text-xs">
        {ativando ? 'Ativando...' : 'Ativar'}
      </button>
    </div>
  )
}
