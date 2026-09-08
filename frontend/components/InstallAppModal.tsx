'use client'

import { X } from 'lucide-react'
import InstallAppInstructions from '@/components/InstallAppInstructions'

export default function InstallAppModal({
  open,
  onClose,
  onJaInstalei,
}: {
  open: boolean
  onClose: () => void
  /** Quando presente, mostra um "Já instalei" que dispensa o convite de vez.
   *  É o único jeito de o convite sumir na aba do Safari no iOS, que não avisa
   *  quando o app é instalado. */
  onJaInstalei?: () => void
}) {
  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center" onClick={onClose}>
      <div
        onClick={(e) => e.stopPropagation()}
        className="max-h-[85vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:rounded-3xl"
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-display text-lg font-bold text-ink">Instalar na tela inicial</h2>
          <button
            onClick={onClose}
            aria-label="Fechar"
            className="flex h-8 w-8 items-center justify-center rounded-full text-ink-muted transition hover:bg-ink/5 hover:text-ink-soft"
          >
            <X size={18} />
          </button>
        </div>
        <InstallAppInstructions />

        {onJaInstalei && (
          <button
            onClick={onJaInstalei}
            className="mt-5 w-full rounded-xl border border-ink/10 py-2.5 text-sm font-medium text-ink-soft transition hover:bg-ink/5"
          >
            Já instalei, não mostrar de novo
          </button>
        )}
      </div>
    </div>
  )
}
