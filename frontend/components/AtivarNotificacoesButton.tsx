'use client'

import { useEffect, useState } from 'react'
import { ativarPush, estaInstalado, precisaEstarInstalado, pushSuportado, statusPermissao } from '@/lib/push'

/**
 * Só aparece quando dá pra ativar de verdade e a permissão ainda não foi
 * decidida — some sozinho depois de ativado ou negado, sem ficar insistindo.
 * No iPhone (precisaEstarInstalado()), exige estar instalado na tela inicial
 * (restrição do Safari). Em qualquer outra plataforma (desktop, Android), o
 * botão aparece numa aba comum do navegador mesmo — é assim que o personal,
 * que usa o painel em desktop, consegue ativar as notificações de gestão.
 */
export default function AtivarNotificacoesButton({ caminhoSubscribe }: { caminhoSubscribe: string }) {
  const [visivel, setVisivel] = useState(false)
  const [ativando, setAtivando] = useState(false)
  const [mensagem, setMensagem] = useState<string | null>(null)

  useEffect(() => {
    const podeAtivar = !precisaEstarInstalado() || estaInstalado()
    setVisivel(podeAtivar && pushSuportado() && statusPermissao() === 'default')
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
