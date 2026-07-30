'use client'

import { Check, Share, MoreVertical } from 'lucide-react'
import { estaInstalado, precisaEstarInstalado, useValorDoNavegador } from '@/lib/push'

type Plataforma = 'ios' | 'android' | 'outro'

// iOS detectado via precisaEstarInstalado() (mesmo critério usado pro gate de
// push) — cobre iPhone/iPod por user agent e iPad, que desde o iPadOS 13 se
// identifica como "Macintosh" no user agent por padrão. Só é chamada no
// navegador (nunca durante SSR).
function detectarPlataforma(): Plataforma {
  if (precisaEstarInstalado()) return 'ios'
  if (/Android/.test(navigator.userAgent)) return 'android'
  return 'outro'
}

export default function InstallAppInstructions() {
  const plataforma = useValorDoNavegador<Plataforma | null>(detectarPlataforma, null)
  const instalado = useValorDoNavegador(estaInstalado, false)

  if (instalado) {
    return (
      <div className="glass rounded-2xl p-6 text-center">
        <span className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-success-soft text-success" aria-hidden="true">
          <Check size={20} strokeWidth={2.5} />
        </span>
        <p className="font-semibold text-ink">Já instalado!</p>
        <p className="mt-1 text-sm text-ink-muted">Você já está usando o app pela tela inicial.</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-ink-muted">
        Adicione um ícone na tela do seu celular pra abrir direto, sem precisar passar pelo navegador toda vez.
      </p>

      {(plataforma === 'ios' || plataforma === null) && (
        <div className="glass rounded-2xl p-5">
          <p className="mb-3 text-sm font-semibold text-ink">iPhone (Safari)</p>
          <ol className="space-y-2 text-sm text-ink-soft">
            <li className="flex items-center gap-1.5">
              1. Toque no ícone de <strong>Compartilhar</strong> (<Share size={14} className="inline" />) na barra do Safari
            </li>
            <li>
              2. Role a lista e toque em <strong>&quot;Adicionar à Tela de Início&quot;</strong>
            </li>
            <li>
              3. Toque em <strong>&quot;Adicionar&quot;</strong> no canto superior direito
            </li>
          </ol>
        </div>
      )}

      {(plataforma === 'android' || plataforma === null) && (
        <div className="glass rounded-2xl p-5">
          <p className="mb-3 text-sm font-semibold text-ink">Android (Chrome)</p>
          <ol className="space-y-2 text-sm text-ink-soft">
            <li className="flex items-center gap-1.5">
              1. Toque no menu (<MoreVertical size={14} className="inline" />) no canto superior direito do navegador
            </li>
            <li>
              2. Toque em <strong>&quot;Adicionar à tela inicial&quot;</strong> ou <strong>&quot;Instalar app&quot;</strong>
            </li>
            <li>
              3. Confirme tocando em <strong>&quot;Instalar&quot;</strong> ou <strong>&quot;Adicionar&quot;</strong>
            </li>
          </ol>
        </div>
      )}

      {plataforma === 'outro' && (
        <div className="glass rounded-2xl p-5 text-center">
          <p className="text-sm text-ink-soft">
            Você está num computador. Abra esse mesmo link no <strong>celular</strong> (iPhone ou Android) pra ver
            o passo a passo e instalar por lá.
          </p>
        </div>
      )}
    </div>
  )
}
