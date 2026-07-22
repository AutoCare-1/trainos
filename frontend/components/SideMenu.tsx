'use client'

import { resolveMediaUrl } from '@/lib/api'

export interface MenuItem {
  id: string
  label: string
  icon: string
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
}: {
  open: boolean
  onClose: () => void
  nome: string
  fotoUrl?: string | null
  subtitulo: string
  items: MenuItem[]
  ativo: string
  onSelect: (id: string) => void
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
        <div className="bg-gradient-to-br from-[#2648b3] to-[#8b7fd6] px-6 py-8 text-center text-white">
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
          <p className="text-lg font-bold uppercase tracking-wide">{nome}</p>
          <p className="mt-0.5 text-xs uppercase tracking-[0.2em] text-white/80">{subtitulo}</p>
        </div>

        <nav className="py-2">
          {items.map((item) => (
            <button
              key={item.id}
              onClick={() => {
                onSelect(item.id)
                onClose()
              }}
              className={`flex w-full items-center gap-4 border-b border-black/6 px-6 py-4 text-left transition ${
                ativo === item.id ? 'bg-[#2648b3]/6' : 'hover:bg-slate-900/3'
              }`}
            >
              <span className="text-xl">{item.icon}</span>
              <span className={`text-sm font-semibold ${ativo === item.id ? 'text-[#2648b3]' : 'text-slate-800'}`}>
                {item.label}
              </span>
            </button>
          ))}
        </nav>
      </aside>
    </>
  )
}
