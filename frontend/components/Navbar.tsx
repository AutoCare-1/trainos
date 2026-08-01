'use client'

import { useEffect, useState } from 'react'
import Image from 'next/image'
import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
import {
  Menu,
  LogOut,
  TrendingUp,
  Wallet,
  Users,
  Trophy,
  Video,
  ClipboardList,
  Dumbbell,
  Sparkles,
  Bot,
  UserPlus,
  Bell,
  Download,
} from 'lucide-react'
import AtivarNotificacoesButton from '@/components/AtivarNotificacoesButton'
import InstallAppModal from '@/components/InstallAppModal'
import InstallBanner from '@/components/InstallBanner'
import SideMenu, { MenuItem } from '@/components/SideMenu'
import { api, clearToken } from '@/lib/api'
import { Professional } from '@/lib/types'

const ICON_SIZE = 20

function itemAtivo(pathname: string): string {
  if (pathname.startsWith('/negocio')) return 'negocio'
  if (pathname.startsWith('/gastos')) return 'gastos'
  if (pathname.startsWith('/alunos/novo')) return 'novo-aluno'
  if (pathname.startsWith('/alunos') || pathname === '/dashboard') return 'dashboard'
  if (pathname.startsWith('/desafios')) return 'desafios'
  if (pathname.startsWith('/videos')) return 'videos'
  if (pathname.startsWith('/modelos')) return 'modelos'
  if (pathname.startsWith('/academia')) return 'academia'
  if (pathname.startsWith('/conteudo')) return 'conteudo'
  if (pathname.startsWith('/consultor-ia')) return 'consultor-ia'
  if (pathname.startsWith('/notificacoes')) return 'notificacoes'
  return ''
}

export default function Navbar() {
  const router = useRouter()
  const pathname = usePathname()
  const [menuAberto, setMenuAberto] = useState(false)
  const [instalarAberto, setInstalarAberto] = useState(false)
  const [profissional, setProfissional] = useState<Professional | null>(null)

  const menuItems: MenuItem[] = [
    { id: 'negocio', label: 'Meu Negócio', icon: <TrendingUp size={ICON_SIZE} />, href: '/negocio', cor: 'violet' },
    { id: 'gastos', label: 'Meus Gastos', icon: <Wallet size={ICON_SIZE} />, href: '/gastos', cor: 'amber' },
    { id: 'dashboard', label: 'Meus alunos', icon: <Users size={ICON_SIZE} />, href: '/dashboard', cor: 'blue' },
    { id: 'desafios', label: 'Desafios', icon: <Trophy size={ICON_SIZE} />, href: '/desafios', cor: 'coral' },
    { id: 'videos', label: 'Vídeos dos exercícios', icon: <Video size={ICON_SIZE} />, href: '/videos', cor: 'sky' },
    { id: 'modelos', label: 'Modelos de treino', icon: <ClipboardList size={ICON_SIZE} />, href: '/modelos', cor: 'teal' },
    { id: 'academia', label: 'Análises de academia', icon: <Dumbbell size={ICON_SIZE} />, href: '/academia', cor: 'pink' },
    { id: 'conteudo', label: 'Ideias de Conteúdo', icon: <Sparkles size={ICON_SIZE} />, href: '/conteudo', cor: 'coral' },
    { id: 'consultor-ia', label: 'Consultor IA', icon: <Bot size={ICON_SIZE} />, href: '/consultor-ia', cor: 'violet' },
    { id: 'novo-aluno', label: 'Cadastrar aluno', icon: <UserPlus size={ICON_SIZE} />, href: '/alunos/novo', cor: 'teal' },
    { id: 'notificacoes', label: 'Notificações', icon: <Bell size={ICON_SIZE} />, href: '/notificacoes', cor: 'sky' },
    { id: 'instalar', label: 'Instalar app', icon: <Download size={ICON_SIZE} />, onClick: () => setInstalarAberto(true), cor: 'blue' },
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
      <header className="chrome-gradient sticky top-0 z-20 shadow-[0_4px_20px_rgba(26,53,133,0.18)]">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
          <button
            onClick={() => setMenuAberto(true)}
            aria-label="Abrir menu"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white transition hover:bg-white/15"
          >
            <Menu size={20} />
          </button>
          <Link href="/dashboard" className="flex flex-1 items-center justify-center gap-2.5 sm:flex-initial sm:justify-start">
            <Image
              src="/clubemais-logo.png"
              alt="Clube Mais"
              width={140}
              height={39}
              priority
              className="chrome-invert h-7 w-auto"
            />
            <span className="hidden items-center border-l border-white/25 pl-2.5 text-xs font-semibold uppercase tracking-[0.18em] text-white/80 sm:flex">
              Personal
            </span>
          </Link>
          <div className="w-9 shrink-0 sm:hidden" />
        </div>
      </header>

      <div className="mx-auto w-full max-w-5xl px-4 pt-4">
        <InstallBanner />
        <AtivarNotificacoesButton caminhoSubscribe="/push/subscribe" />
      </div>

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
            className="flex w-full items-center gap-4 border-t border-line-soft px-6 py-4 text-left text-danger transition hover:bg-danger-soft"
          >
            <LogOut size={20} aria-hidden="true" />
            <span className="text-sm font-semibold">Sair</span>
          </button>
        }
      />

      <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
    </>
  )
}
