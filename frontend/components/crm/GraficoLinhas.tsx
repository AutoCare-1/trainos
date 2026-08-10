'use client'

import { useCallback, useEffect, useRef, useState } from 'react'

export interface SerieLinha {
  key: string
  label: string
  cor: string
}

interface Ponto {
  rotulo: string
  [key: string]: string | number
}

const PAD = { top: 18, right: 62, bottom: 30, left: 62 }
const ALTURA = 300

/** Ticks em números redondos (0 / 500 / 1.000), cobrindo min e max. */
function ticksLegiveis(min: number, max: number, alvo = 4): number[] {
  if (min === max) {
    return min === 0 ? [0, 1] : [Math.min(0, min), Math.max(0, max)]
  }
  const bruto = (max - min) / alvo
  const mag = Math.pow(10, Math.floor(Math.log10(Math.abs(bruto) || 1)))
  const passo = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((p) => p >= bruto) ?? mag * 10

  const inicio = Math.floor(min / passo) * passo
  const fim = Math.ceil(max / passo) * passo
  const out: number[] = []
  for (let v = inicio; v <= fim + passo / 2; v += passo) out.push(Number(v.toFixed(6)))
  return out
}

/**
 * Série temporal multi-linha com crosshair e tooltip.
 *
 * Cores vêm dos tokens da marca e foram validadas para daltonismo (protanopia
 * e deuteranopia) — azul/âmbar/teal, pior par ΔE 11,3. Não trocar por
 * vermelho+verde: essa dupla colide (ΔE 5,1) e é ilegível pra quem tem
 * protanopia, que é o caso mais comum.
 */
export default function GraficoLinhas({
  dados,
  series,
  formatarValor,
  formatarEixo,
  destaque,
}: {
  dados: Ponto[]
  series: SerieLinha[]
  formatarValor: (v: number) => string
  formatarEixo: (v: number) => string
  /** Chave da série que recebe rótulo direto na ponta — só uma, pra não colidir. */
  destaque?: string
}) {
  const wrapRef = useRef<HTMLDivElement>(null)
  const [largura, setLargura] = useState(720)
  const [ativo, setAtivo] = useState<number | null>(null)

  useEffect(() => {
    const el = wrapRef.current
    if (!el) return
    const ro = new ResizeObserver(([entry]) => setLargura(entry.contentRect.width))
    ro.observe(el)
    setLargura(el.clientWidth)
    return () => ro.disconnect()
  }, [])

  const w = Math.max(320, largura)
  const plotW = w - PAD.left - PAD.right
  const plotH = ALTURA - PAD.top - PAD.bottom

  const valores = dados.flatMap((d) => series.map((s) => Number(d[s.key]) || 0))
  const minBruto = Math.min(0, ...valores)
  const maxBruto = Math.max(0, ...valores)
  const ticks = ticksLegiveis(minBruto, maxBruto)
  const yMin = ticks[0]
  const yMax = ticks[ticks.length - 1]

  const x = (i: number) => PAD.left + (dados.length === 1 ? plotW / 2 : (i * plotW) / (dados.length - 1))
  const y = (v: number) => PAD.top + plotH - ((v - yMin) / (yMax - yMin || 1)) * plotH

  const aoMover = useCallback(
    (e: React.PointerEvent<SVGSVGElement>) => {
      const box = e.currentTarget.getBoundingClientRect()
      const rel = e.clientX - box.left - PAD.left
      const passo = dados.length > 1 ? plotW / (dados.length - 1) : plotW
      const i = Math.round(rel / passo)
      setAtivo(Math.max(0, Math.min(dados.length - 1, i)))
    },
    [dados.length, plotW]
  )

  const aoTeclado = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return
      e.preventDefault()
      setAtivo((prev) => {
        const base = prev ?? dados.length - 1
        return Math.max(0, Math.min(dados.length - 1, base + (e.key === 'ArrowRight' ? 1 : -1)))
      })
    },
    [dados.length]
  )

  if (dados.length === 0) {
    return <p className="py-10 text-center text-sm text-ink-muted">Sem dados no período.</p>
  }

  const ponto = ativo !== null ? dados[ativo] : null
  // Tooltip acompanha o crosshair, mas nunca sai do card: perto da borda
  // direita ele vira pro outro lado.
  const tooltipEsquerda = ativo !== null && x(ativo) > w * 0.6

  return (
    <div ref={wrapRef} className="relative w-full">
      <svg
        width={w}
        height={ALTURA}
        role="img"
        aria-label="Faturamento, custo e lucro por mês"
        tabIndex={0}
        onPointerMove={aoMover}
        onPointerLeave={() => setAtivo(null)}
        onKeyDown={aoTeclado}
        onBlur={() => setAtivo(null)}
        className="touch-none outline-none focus-visible:ring-2 focus-visible:ring-brand/40"
      >
        {/* Grade: hairline sólida, um passo fora da superfície — nunca tracejada. */}
        {ticks.map((t) => (
          <g key={t}>
            <line
              x1={PAD.left}
              x2={w - PAD.right}
              y1={y(t)}
              y2={y(t)}
              stroke="var(--line)"
              strokeWidth={1}
            />
            <text
              x={PAD.left - 10}
              y={y(t) + 4}
              textAnchor="end"
              className="fill-ink-muted text-[11px] [font-variant-numeric:tabular-nums]"
            >
              {formatarEixo(t)}
            </text>
          </g>
        ))}

        {/* Linha do zero mais firme que a grade — separa lucro de prejuízo. */}
        {yMin < 0 && (
          <line x1={PAD.left} x2={w - PAD.right} y1={y(0)} y2={y(0)} stroke="var(--ink-muted)" strokeWidth={1} />
        )}

        {dados.map((d, i) => (
          <text
            key={d.rotulo}
            x={x(i)}
            y={ALTURA - 8}
            textAnchor="middle"
            className="fill-ink-muted text-[11px]"
          >
            {d.rotulo}
          </text>
        ))}

        {ativo !== null && (
          <line
            x1={x(ativo)}
            x2={x(ativo)}
            y1={PAD.top}
            y2={PAD.top + plotH}
            stroke="var(--ink-muted)"
            strokeWidth={1}
          />
        )}

        {series.map((s) => {
          const d = dados
            .map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(Number(p[s.key]) || 0)}`)
            .join(' ')
          const ultimo = dados.length - 1
          const vUltimo = Number(dados[ultimo][s.key]) || 0
          return (
            <g key={s.key}>
              <path d={d} fill="none" stroke={s.cor} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
              {/* Marcador da ponta com anel de 2px na cor da superfície, pra
                  continuar legível quando duas linhas se cruzam. */}
              <circle cx={x(ultimo)} cy={y(vUltimo)} r={4} fill={s.cor} stroke="#ffffff" strokeWidth={2} />
              {destaque === s.key && (
                <text
                  x={x(ultimo) + 10}
                  y={y(vUltimo) + 4}
                  className="fill-ink text-[11px] font-semibold [font-variant-numeric:tabular-nums]"
                >
                  {formatarValor(vUltimo)}
                </text>
              )}
            </g>
          )
        })}

        {ativo !== null &&
          series.map((s) => (
            <circle
              key={s.key}
              cx={x(ativo)}
              cy={y(Number(dados[ativo][s.key]) || 0)}
              r={4}
              fill={s.cor}
              stroke="#ffffff"
              strokeWidth={2}
            />
          ))}
      </svg>

      {ponto && (
        <div
          className="pointer-events-none absolute top-2 z-10 min-w-[168px] rounded-xl border border-line bg-white/95 p-3 shadow-lg backdrop-blur"
          style={tooltipEsquerda ? { left: 8 } : { right: 8 }}
        >
          <p className="mb-2 text-xs font-medium text-ink-muted">{ponto.rotulo}</p>
          {series.map((s) => (
            <div key={s.key} className="flex items-center justify-between gap-4 py-0.5">
              <span className="flex items-center gap-1.5">
                {/* Chave da série é um traço, não um quadrado — em densidade de
                    tooltip o bloco preenchido pesa demais pro papel de rótulo. */}
                <span className="inline-block h-0.5 w-3 rounded-full" style={{ background: s.cor }} />
                <span className="text-xs text-ink-muted">{s.label}</span>
              </span>
              <span className="text-sm font-semibold text-ink [font-variant-numeric:tabular-nums]">
                {formatarValor(Number(ponto[s.key]) || 0)}
              </span>
            </div>
          ))}
        </div>
      )}

      {/* Legenda sempre presente com 2+ séries — identidade nunca depende só da cor. */}
      <div className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1.5">
        {series.map((s) => (
          <span key={s.key} className="flex items-center gap-1.5">
            <span className="inline-block h-0.5 w-4 rounded-full" style={{ background: s.cor }} />
            <span className="text-xs text-ink-soft">{s.label}</span>
          </span>
        ))}
      </div>
    </div>
  )
}
