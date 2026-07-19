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
    <header className="border-b border-slate-200 bg-white">
      <div className="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <Link href="/dashboard" className="font-bold text-slate-900">
          TrainOS
        </Link>
        <button onClick={sair} className="text-sm text-slate-500 hover:text-slate-800">
          Sair
        </button>
      </div>
    </header>
  )
}
