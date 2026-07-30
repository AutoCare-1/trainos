'use client'

import Link from 'next/link'
import { ChevronLeft } from 'lucide-react'

export default function BackLink({ href, label = 'Voltar' }: { href: string; label?: string }) {
  return (
    <Link
      href={href}
      className="mb-5 inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-ink-muted transition hover:bg-black/5 hover:text-ink"
    >
      <ChevronLeft size={16} strokeWidth={2.2} aria-hidden="true" />
      {label}
    </Link>
  )
}
