'use client'

import { useEffect, useState } from 'react'
import Image from 'next/image'
import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
import InstallAppModal from '@/components/InstallAppModal'
import SideMenu, { MenuItem } from '@/components/SideMenu'
import { api, clearToken } from '@/lib/api'
import { Professional } from '@/lib/types'

function itemAtivo(pathname: string): string {
  if (pathname.startsWith('/alunos/novo')) return 'novo-aluno'
  if (pathname.startsWith('/alunos') || pathname === '/dashboard') return 'dashboard'
  if (pathname.startsWith('/desafios')) return 'desafios'
  if (pathname.startsWith('/videos')) return 'videos'
  if (pathname.startsWith('/modelos')) return 'modelos'
  if (pathname.startsWith('/academia')) return 'academia'
  if (pathname.startsWith('/conteudo')) return 'conteudo'
  return ''
}

export default function Navbar() {
  const router = useRouter()
  const pathname = usePathname()
  const [menuAberto, setMenuAberto] = useState(false)
  const [instalarAberto, setInstalarAberto] = useState(false)
  const [profissional, setProfissional] = useState<Professional | null>(null)

  const menuItems: MenuItem[] = [
    { id: 'dashboard', label: 'Meus alunos', icon: '', href: '/dashboard' },
    { id: 'desafios', label: 'Desafios', icon: '', href: '/desafios' },
    { id: 'videos', label: 'Vídeos dos exercícios', icon: '', href: '/videos' },
    { id: 'modelos', label: 'Modelos de treino', icon: '', href: '/modelos' },
    { id: 'academia', label: 'Análises de academia', icon: '', href: '/academia' },
    { id: 'conteudo', label: 'Conteúdo', icon: '', href: '/conteudo' },
    { id: 'novo-aluno', label: 'Cadastrar aluno', icon: '', href: '/alunos/novo' },
    { id: 'instalar', label: 'Instalar app', icon: '', onClick: () => setInstalarAberto(true) },
  ]

  useEffect(() => {
    api
      .get<{ professional: Professional }>('/auth/me')
      .then((d) => setProfissional(d.professional))
      .catch(() => {})
  }, [])

  function sair() {
    clearToken()
    router.push('/login')
  }

  return (
    <>
      <header className="sticky top-0 z-20 border-b border-black/8 bg-white/85 backdrop-blur-xl">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
          <button
            onClick={() => setMenuAberto(true)}
            aria-label="Abrir menu"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-900/5"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="4" y1="7" x2="20" y2="7" />
              <line x1="4" y1="12" x2="20" y2="12" />
              <line x1="4" y1="17" x2="20" y2="17" />
            </svg>
          </button>
          <Link href="/dashboard" className="flex flex-1 items-center justify-center gap-2.5 sm:flex-initial sm:justify-start">
            <Image src="/clubemais-logo.png" alt="Clube Mais" width={140} height={39} priority className="h-7 w-auto" />
            <span className="hidden items-center border-l border-black/10 pl-2.5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 sm:flex">
              Personal
            </span>
          </Link>
          <div className="w-9 shrink-0 sm:hidden" />
        </div>
      </header>

      <SideMenu
        open={menuAberto}
        onClose={() => setMenuAberto(false)}
        nome={profissional?.name ?? 'Personal'}
        subtitulo="Clube Mais"
        items={menuItems}
        ativo={itemAtivo(pathname)}
        rodape={
          <button
            onClick={sair}
            className="flex w-full items-center gap-4 border-t border-black/6 px-6 py-4 text-left text-rose-600 transition hover:bg-rose-50"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            <span className="text-sm font-semibold">Sair</span>
          </button>
        }
      />

      <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
    </>
  )
}
