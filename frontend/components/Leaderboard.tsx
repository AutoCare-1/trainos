'use client'

import Avatar from '@/components/Avatar'
import { LeaderboardEntry } from '@/lib/types'

const MEDALHAS = ['🥇', '🥈', '🥉']

export default function Leaderboard({
  entries,
  highlightId,
}: {
  entries: LeaderboardEntry[]
  highlightId?: string
}) {
  if (entries.length === 0) {
    return <p className="text-sm text-slate-400">Ninguém participando ainda.</p>
  }

  return (
    <div className="space-y-1.5">
      {entries.map((e, i) => {
        const destaque = e.student_id === highlightId
        return (
          <div
            key={e.student_id}
            className={`flex items-center gap-3 rounded-xl px-3 py-2.5 ${
              destaque ? 'border border-[#2648b3]/25 bg-[#2648b3]/6' : 'bg-slate-900/3'
            }`}
          >
            <span className="w-6 shrink-0 text-center text-sm">{MEDALHAS[i] ?? `${i + 1}º`}</span>
            <Avatar nome={e.name} tamanho="sm" />
            <span className="flex min-w-0 flex-1 items-baseline gap-1">
              <span className="truncate text-sm font-medium text-slate-800">{e.name}</span>
              {destaque && <span className="shrink-0 text-xs text-[#2648b3]">(você)</span>}
            </span>
            <span className="shrink-0 text-sm font-bold text-[#2648b3]">
              {e.pontos} {Number(e.pontos) === 1 ? 'treino' : 'treinos'}
            </span>
          </div>
        )
      })}
    </div>
  )
}
