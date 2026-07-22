export interface Badge {
  id: string
  emoji: string
  label: string
}

const BADGE_DEFS: { id: string; emoji: string; label: string; check: (totalSessoes: number, streak: number) => boolean }[] = [
  { id: 'primeiro_treino', emoji: '🥉', label: 'Primeiro treino', check: (total) => total >= 1 },
  { id: 'dez_treinos', emoji: '💪', label: '10 treinos', check: (total) => total >= 10 },
  { id: 'trinta_treinos', emoji: '🏆', label: '30 treinos', check: (total) => total >= 30 },
  { id: 'sequencia_7', emoji: '🔥', label: 'Sequência de 7 dias', check: (_total, streak) => streak >= 7 },
  { id: 'sequencia_30', emoji: '⚡', label: 'Sequência de 30 dias', check: (_total, streak) => streak >= 30 },
]

/** Sequência atual de dias consecutivos (até hoje ou ontem) com pelo menos um treino concluído. */
export function calcularStreak(datasConcluidas: Date[]): number {
  if (datasConcluidas.length === 0) return 0

  const dias = new Set(datasConcluidas.map((d) => d.toISOString().slice(0, 10)))
  const hoje = new Date()
  hoje.setUTCHours(0, 0, 0, 0)

  // a sequência só conta se o aluno treinou hoje ou ontem — senão já quebrou
  const chaveHoje = hoje.toISOString().slice(0, 10)
  const ontem = new Date(hoje)
  ontem.setUTCDate(ontem.getUTCDate() - 1)
  const chaveOntem = ontem.toISOString().slice(0, 10)

  let cursor: Date
  if (dias.has(chaveHoje)) {
    cursor = hoje
  } else if (dias.has(chaveOntem)) {
    cursor = ontem
  } else {
    return 0
  }

  let streak = 0
  while (dias.has(cursor.toISOString().slice(0, 10))) {
    streak++
    cursor.setUTCDate(cursor.getUTCDate() - 1)
  }
  return streak
}

export function calcularBadges(totalSessoes: number, streak: number): Badge[] {
  return BADGE_DEFS.filter((b) => b.check(totalSessoes, streak)).map((b) => ({ id: b.id, emoji: b.emoji, label: b.label }))
}
