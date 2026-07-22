'use client'

interface Ponto {
  recorded_at: string
  weight_kg: number | null
}

const W = 320
const H = 140
const PAD_X = 8
const PAD_TOP = 16
const PAD_BOTTOM = 24

function formatarData(iso: string): string {
  // recorded_at vem como "2026-06-21" (date puro) ou já como ISO datetime completo
  const d = iso.includes('T') ? new Date(iso) : new Date(iso + 'T00:00:00')
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', timeZone: 'UTC' })
}

export default function WeightChart({ pontos }: { pontos: Ponto[] }) {
  const validos = pontos.filter((p) => p.weight_kg != null) as { recorded_at: string; weight_kg: number }[]

  if (validos.length === 0) {
    return (
      <div className="flex h-[140px] items-center justify-center text-sm text-slate-400">
        Sem medições registradas ainda.
      </div>
    )
  }

  const pesos = validos.map((p) => p.weight_kg)
  const min = Math.min(...pesos)
  const max = Math.max(...pesos)
  const margem = Math.max((max - min) * 0.15, 1)
  const yMin = min - margem
  const yMax = max + margem

  const plotW = W - PAD_X * 2
  const plotH = H - PAD_TOP - PAD_BOTTOM

  function x(i: number): number {
    if (validos.length === 1) return PAD_X + plotW / 2
    return PAD_X + (i / (validos.length - 1)) * plotW
  }
  function y(peso: number): number {
    return PAD_TOP + plotH - ((peso - yMin) / (yMax - yMin)) * plotH
  }

  const pontosXY = validos.map((p, i) => ({ x: x(i), y: y(p.weight_kg), peso: p.weight_kg, data: p.recorded_at }))
  const linha = pontosXY.map((p) => `${p.x},${p.y}`).join(' ')
  const area = `${PAD_X},${PAD_TOP + plotH} ${linha} ${PAD_X + plotW},${PAD_TOP + plotH}`

  const primeiro = validos[0]
  const ultimo = validos[validos.length - 1]
  const delta = ultimo.weight_kg - primeiro.weight_kg

  return (
    <div>
      <svg viewBox={`0 0 ${W} ${H}`} className="w-full" style={{ maxHeight: 160 }}>
        <defs>
          <linearGradient id="weightFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#2648b3" stopOpacity="0.18" />
            <stop offset="100%" stopColor="#2648b3" stopOpacity="0" />
          </linearGradient>
        </defs>

        {validos.length > 1 && <polygon points={area} fill="url(#weightFill)" />}
        {validos.length > 1 && (
          <polyline points={linha} fill="none" stroke="#2648b3" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
        )}

        {pontosXY.map((p, i) => (
          <g key={i}>
            <circle cx={p.x} cy={p.y} r={3.5} fill="#ffffff" stroke="#2648b3" strokeWidth={2} />
            <title>
              {formatarData(p.data)}: {p.peso}kg
            </title>
          </g>
        ))}

        <text x={PAD_X} y={H - 6} fontSize={10} fill="#94a3b8">
          {formatarData(primeiro.recorded_at)}
        </text>
        <text x={W - PAD_X} y={H - 6} fontSize={10} fill="#94a3b8" textAnchor="end">
          {formatarData(ultimo.recorded_at)}
        </text>
      </svg>
      <div className="mt-1 flex items-center justify-between text-sm">
        <span className="text-slate-500">
          {primeiro.weight_kg}kg → <span className="font-semibold text-slate-900">{ultimo.weight_kg}kg</span>
        </span>
        {validos.length > 1 && (
          <span className="font-semibold text-[#2648b3]">
            {delta > 0 ? '+' : ''}
            {delta.toFixed(1)}kg no período
          </span>
        )}
      </div>
    </div>
  )
}
