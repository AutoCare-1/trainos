'use client'

import { useEffect, useState } from 'react'

type Plataforma = 'ios' | 'android' | 'outro'

function detectarPlataforma(): Plataforma {
  const ua = navigator.userAgent
  if (/iPhone|iPad|iPod/.test(ua)) return 'ios'
  if (/Android/.test(ua)) return 'android'
  return 'outro'
}

function jaInstalado(): boolean {
  const standaloneIOS = (window.navigator as Navigator & { standalone?: boolean }).standalone === true
  return window.matchMedia('(display-mode: standalone)').matches || standaloneIOS
}

export default function InstallAppInstructions() {
  const [plataforma, setPlataforma] = useState<Plataforma | null>(null)
  const [instalado, setInstalado] = useState(false)

  useEffect(() => {
    setPlataforma(detectarPlataforma())
    setInstalado(jaInstalado())
  }, [])

  if (instalado) {
    return (
      <div className="glass rounded-2xl p-6 text-center">
        <span className="mb-2 block text-3xl">✅</span>
        <p className="font-semibold text-slate-900">Já instalado!</p>
        <p className="mt-1 text-sm text-slate-500">Você já está usando o app pela tela inicial.</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-slate-500">
        Adicione um ícone na tela do seu celular pra abrir direto, sem precisar passar pelo navegador toda vez.
      </p>

      {(plataforma === 'ios' || plataforma === null) && (
        <div className="glass rounded-2xl p-5">
          <p className="mb-3 text-sm font-semibold text-slate-900">📱 iPhone (Safari)</p>
          <ol className="space-y-2 text-sm text-slate-600">
            <li>
              1. Toque no ícone de <strong>Compartilhar</strong> (□ com seta ↑) na barra do Safari
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
          <p className="mb-3 text-sm font-semibold text-slate-900">🤖 Android (Chrome)</p>
          <ol className="space-y-2 text-sm text-slate-600">
            <li>
              1. Toque no menu (⋮) no canto superior direito do navegador
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
          <p className="text-sm text-slate-600">
            💻 Você está num computador. Abra esse mesmo link no <strong>celular</strong> (iPhone ou Android) pra ver
            o passo a passo e instalar por lá.
          </p>
        </div>
      )}
    </div>
  )
}
