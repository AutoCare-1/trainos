'use client'

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
    <header className="sticky top-0 z-20 border-b border-white/8 bg-[#070b14]/80 backdrop-blur-xl">
      <div className="max-w-5xl mx-auto px-4 py-3.5 flex items-center justify-between">
        <Link href="/dashboard" className="flex items-center gap-2.5">
          <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-500 text-sm font-black text-[#04110d]">
            T
          </span>
          <span className="font-bold tracking-tight text-white">
            Train<span className="bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">OS</span>
          </span>
        </Link>
        <button
          onClick={sair}
          className="rounded-lg px-3 py-1.5 text-sm text-slate-400 transition hover:bg-white/5 hover:text-white"
        >
          Sair
        </button>
      </div>
    </header>
  )
}
