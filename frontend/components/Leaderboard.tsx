'use client'

import Avatar from '@/components/Avatar'
import { LeaderboardEntry } from '@/lib/types'

export default function Leaderboard({
  entries,
  highlightId,
}: {
  entries: LeaderboardEntry[]
  highlightId?: string
}) {
  if (entries.length === 0) {
    return <p className="text-sm text-ink-muted">Ninguém participando ainda.</p>
  }

  return (
    <div className="space-y-1.5">
      {entries.map((e, i) => {
        const destaque = e.student_id === highlightId
        return (
          <div
            key={e.student_id}
            className={`flex items-center gap-3 rounded-xl px-3 py-2.5 ${
              destaque ? 'border border-brand/25 bg-brand/6' : 'bg-ink/3'
            }`}
          >
            <span className="w-6 shrink-0 text-center text-sm font-semibold text-ink-muted">{i + 1}º</span>
            <Avatar nome={e.name} fotoUrl={e.photo_url} tamanho="sm" />
            <span className="flex min-w-0 flex-1 items-baseline gap-1">
              <span className="truncate text-sm font-medium text-ink">{e.name}</span>
              {destaque && <span className="shrink-0 text-xs text-brand">(você)</span>}
            </span>
            <span className="shrink-0 text-sm font-bold text-brand">
              {e.pontos} {Number(e.pontos) === 1 ? 'treino' : 'treinos'}
            </span>
          </div>
        )
      })}
    </div>
  )
}
