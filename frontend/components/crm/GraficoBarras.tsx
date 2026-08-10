'use client'

import { useEffect, useRef, useState } from 'react'

export interface ItemBarra {
  rotulo: string
  valor: number
  /** Texto extra do tooltip (ex.: "128 chamadas"). */
  detalhe?: string
}

const ALTURA_BARRA = 20 // ≤24px: a barra nunca preenche a faixa, sobra ar
const ESPACO = 14
const PAD_ESQ = 132
const PAD_DIR = 92

/** Barra com ponta arredondada (4px) no lado do dado e quadrada na base. */
function caminhoBarra(x0: number, y0: number, comprimento: number, altura: number): string {
  const r = Math.min(4, Math.max(0, comprimento))
  const x1 = x0 + Math.max(comprimento, 0.5)
  if (comprimento <= r) return `M ${x0} ${y0} H ${x1} V ${y0 + altura} H ${x0} Z`
  return [
    `M ${x0} ${y0}`,
    `H ${x1 - r}`,
    `A ${r} ${r} 0 0 1 ${x1} ${y0 + r}`,
    `V ${y0 + altura - r}`,
    `A ${r} ${r} 0 0 1 ${x1 - r} ${y0 + altura}`,
    `H ${x0}`,
    'Z',
  ].join(' ')
}

/**
 * Barras horizontais para comparação de magnitude entre categorias nominais.
 * Uma cor só para todas as barras — colorir cada uma de um tom diferente
 * gastaria o canal de identidade repetindo o que o comprimento já mostra.
 */
export default function GraficoBarras({
  itens,
  cor,
  formatarValor,
  rotuloAcessivel,
}: {
  itens: ItemBarra[]
  cor: string
  formatarValor: (v: number) => string
  rotuloAcessivel: string
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

  if (itens.length === 0) {
    return <p className="py-8 text-center text-sm text-ink-muted">Nenhum registro no período.</p>
  }

  const w = Math.max(320, largura)
  const plotW = Math.max(40, w - PAD_ESQ - PAD_DIR)
  const max = Math.max(...itens.map((i) => i.valor), 0.000001)
  const altura = itens.length * (ALTURA_BARRA + ESPACO)

  return (
    <div ref={wrapRef} className="relative w-full">
      <svg width={w} height={altura} role="img" aria-label={rotuloAcessivel}>
        {itens.map((item, i) => {
          const y0 = i * (ALTURA_BARRA + ESPACO) + ESPACO / 2
          const comp = (item.valor / max) * plotW
          return (
            <g key={i}>
              <text
                x={PAD_ESQ - 12}
                y={y0 + ALTURA_BARRA / 2 + 4}
                textAnchor="end"
                className="fill-ink-soft text-[12px]"
              >
                {item.rotulo}
              </text>

              <path
                d={caminhoBarra(PAD_ESQ, y0, comp, ALTURA_BARRA)}
                fill={cor}
                opacity={ativo === null || ativo === i ? 1 : 0.55}
              />

              {/* Valor na ponta da barra, do lado de fora — nunca dentro, pra
                  não correr o risco de cortar o texto numa barra curta. */}
              <text
                x={PAD_ESQ + comp + 10}
                y={y0 + ALTURA_BARRA / 2 + 4}
                className="fill-ink text-[12px] font-medium [font-variant-numeric:tabular-nums]"
              >
                {formatarValor(item.valor)}
              </text>

              {/* Alvo de hover maior que a marca (inclui o respiro entre barras). */}
              <rect
                x={0}
                y={i * (ALTURA_BARRA + ESPACO)}
                width={w}
                height={ALTURA_BARRA + ESPACO}
                fill="transparent"
                tabIndex={0}
                role="button"
                aria-label={`${item.rotulo}: ${formatarValor(item.valor)}`}
                onPointerEnter={() => setAtivo(i)}
                onPointerLeave={() => setAtivo(null)}
                onFocus={() => setAtivo(i)}
                onBlur={() => setAtivo(null)}
                className="outline-none"
              />
            </g>
          )
        })}
      </svg>

      {ativo !== null && itens[ativo].detalhe && (
        <div className="pointer-events-none absolute right-2 top-2 rounded-xl border border-line bg-white/95 p-3 shadow-lg backdrop-blur">
          <p className="text-sm font-semibold text-ink [font-variant-numeric:tabular-nums]">
            {formatarValor(itens[ativo].valor)}
          </p>
          <p className="text-xs text-ink-muted">{itens[ativo].rotulo}</p>
          <p className="mt-1 text-xs text-ink-muted">{itens[ativo].detalhe}</p>
        </div>
      )}
    </div>
  )
}
