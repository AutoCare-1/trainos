'use client'

import Image from 'next/image'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { clearToken } from '@/lib/api'

export default function Navbar() {
  const router = useRouter()

  function sair() {
    clearToken()
    router.push('/login')
  }

  return (
    <header className="sticky top-0 z-20 border-b border-black/8 bg-white/85 backdrop-blur-xl">
      <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <Link href="/dashboard" className="flex items-center gap-2.5">
          <Image src="/clubemais-logo.png" alt="Clube Mais" width={140} height={39} priority className="h-7 w-auto" />
          <span className="flex items-center border-l border-black/10 pl-2.5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            Personal
          </span>
        </Link>
        <button
          onClick={sair}
          className="flex items-center gap-1.5 rounded-lg border border-black/8 px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          Sair
        </button>
      </div>
    </header>
  )
}
