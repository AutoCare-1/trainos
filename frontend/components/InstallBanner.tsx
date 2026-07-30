'use client'

import { useState } from 'react'
import { X } from 'lucide-react'
import InstallAppModal from '@/components/InstallAppModal'
import { estaInstalado, useValorDoNavegador } from '@/lib/push'

/**
 * Convite discreto pra instalar o app — só existe pra quem ainda está no
 * navegador comum. Desaparece sozinho e de vez assim que o app roda instalado
 * (a própria checagem de estaInstalado() nunca mais volta a false depois disso).
 * O "fechar" é só da sessão atual (não persiste) — continua convidando na
 * próxima visita enquanto não instalar, sem ser bloqueante no meio tempo.
 */
export default function InstallBanner() {
  const mostrar = useValorDoNavegador(() => !estaInstalado(), false)
  const [fechado, setFechado] = useState(false)
  const [modalAberto, setModalAberto] = useState(false)

  if (!mostrar || fechado) return null

  return (
    <>
      <div className="glass mb-4 flex items-center justify-between gap-3 rounded-2xl p-3.5">
        <p className="min-w-0 flex-1 text-sm text-ink-soft">
          <span className="font-semibold text-ink">Instale o app</span> na tela inicial pra abrir mais rápido e
          receber notificações.
        </p>
        <div className="flex shrink-0 items-center gap-2">
          <button onClick={() => setModalAberto(true)} className="btn-primary rounded-xl px-3 py-1.5 text-xs">
            Como instalar
          </button>
          <button
            onClick={() => setFechado(true)}
            aria-label="Fechar"
            className="flex h-7 w-7 items-center justify-center rounded-full text-ink-muted transition hover:bg-ink/5 hover:text-ink-soft"
          >
            <X size={16} />
          </button>
        </div>
      </div>
      <InstallAppModal open={modalAberto} onClose={() => setModalAberto(false)} />
    </>
  )
}
