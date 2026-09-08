'use client'

import { useEffect, useState } from 'react'
import { X } from 'lucide-react'
import InstallAppModal from '@/components/InstallAppModal'
import { estaInstalado, jaInstalado, marcarAppInstalado, useValorDoNavegador } from '@/lib/push'

/**
 * Convite discreto pra instalar o app — só pra quem ainda está no navegador
 * comum. Some de vez quando o app passa a rodar instalado:
 *
 * - dentro do app instalado, `estaInstalado()` já é true e o convite nunca
 *   aparece;
 * - na ABA do navegador, que não sabe sozinha que o app foi instalado, o
 *   convite some quando o Chrome dispara `appinstalled` (Android) ou quando o
 *   usuário confirma "já instalei" no passo a passo (único caminho no iOS, que
 *   não tem esse evento). A confirmação fica gravada e não volta a aparecer.
 *
 * O "×" é só da sessão ("agora não") — na próxima visita convida de novo
 * enquanto não instalar.
 */
export default function InstallBanner() {
  const mostrarInicial = useValorDoNavegador(() => !jaInstalado(), false)
  const [instalou, setInstalou] = useState(false)
  const [fechado, setFechado] = useState(false)
  const [modalAberto, setModalAberto] = useState(false)

  useEffect(() => {
    // Rodou standalone alguma vez: grava, pra a aba comum (Android, storage
    // compartilhado) também parar de convidar. Não mexe em estado — quando
    // `estaInstalado()`, `mostrarInicial` já veio false.
    if (estaInstalado()) {
      marcarAppInstalado()
      return
    }
    // Chrome/Android avisa quando o app é instalado a partir desta aba.
    const aoInstalar = () => {
      marcarAppInstalado()
      setInstalou(true)
    }
    window.addEventListener('appinstalled', aoInstalar)
    return () => window.removeEventListener('appinstalled', aoInstalar)
  }, [])

  if (!mostrarInicial || instalou || fechado) return null

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
      <InstallAppModal
        open={modalAberto}
        onClose={() => setModalAberto(false)}
        onJaInstalei={() => {
          marcarAppInstalado()
          setInstalou(true)
          setModalAberto(false)
        }}
      />
    </>
  )
}
