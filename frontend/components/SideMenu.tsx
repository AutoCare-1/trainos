'use client'

import Link from 'next/link'
import { resolveMediaUrl } from '@/lib/api'

export type CorMenuItem = 'blue' | 'violet' | 'coral' | 'teal' | 'amber' | 'pink' | 'sky'

export interface MenuItem {
  id: string
  label: string
  icon: React.ReactNode
  href?: string
  onClick?: () => void
  cor?: CorMenuItem
}

export default function SideMenu({
  open,
  onClose,
  nome,
  fotoUrl,
  subtitulo,
  items,
  ativo,
  onSelect,
  rodape,
}: {
  open: boolean
  onClose: () => void
  nome: string
  fotoUrl?: string | null
  subtitulo: string
  items: MenuItem[]
  ativo: string
  onSelect?: (id: string) => void
  rodape?: React.ReactNode
}) {
  return (
    <>
      <div
        onClick={onClose}
        className={`fixed inset-0 z-40 bg-black/40 transition-opacity ${
          open ? 'pointer-events-auto opacity-100' : 'pointer-events-none opacity-0'
        }`}
      />
      <aside
        className={`fixed left-0 top-0 z-50 h-full w-[78%] max-w-xs overflow-y-auto bg-white shadow-2xl transition-transform duration-300 ${
          open ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="bg-gradient-to-br from-brand to-accent px-6 py-8 text-center text-white">
          {fotoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element -- foto vem do backend (upload do aluno)
            <img
              src={resolveMediaUrl(fotoUrl)}
              alt={nome}
              className="mx-auto mb-3 h-20 w-20 rounded-full object-cover ring-4 ring-white/20"
            />
          ) : (
            <div className="mx-auto mb-3 flex h-20 w-20 items-center justify-center rounded-full bg-white/20 text-2xl font-bold">
              {nome
                .split(' ')
                .slice(0, 2)
                .map((p) => p[0])
                .join('')
                .toUpperCase()}
            </div>
          )}
          <p className="font-display text-lg font-bold uppercase tracking-wide">{nome}</p>
          <p className="mt-0.5 text-xs uppercase tracking-[0.2em] text-white/80">{subtitulo}</p>
        </div>

        <nav className="py-2">
          {items.map((item) => {
            const conteudo = (
              <>
                <span
                  className={`icon-chip icon-chip-${item.cor ?? 'blue'} ${ativo === item.id ? 'icon-chip-ativo' : ''}`}
                >
                  {item.icon}
                </span>
                <span className={`text-sm font-semibold ${ativo === item.id ? 'text-brand' : 'text-ink'}`}>
                  {item.label}
                </span>
              </>
            )
            const classes = `flex w-full items-center gap-4 border-b border-line-soft px-6 py-4 text-left transition ${
              ativo === item.id ? 'bg-brand/6' : 'hover:bg-ink/3'
            }`

            if (item.href) {
              return (
                <Link key={item.id} href={item.href} onClick={onClose} className={classes}>
                  {conteudo}
                </Link>
              )
            }
            return (
              <button
                key={item.id}
                onClick={() => {
                  if (item.onClick) item.onClick()
                  else onSelect?.(item.id)
                  onClose()
                }}
                className={classes}
              >
                {conteudo}
              </button>
            )
          })}
        </nav>

        {rodape}
      </aside>
    </>
  )
}
