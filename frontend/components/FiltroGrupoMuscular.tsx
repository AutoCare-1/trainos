'use client'

/**
 * Chips pra filtrar a biblioteca por grupo muscular.
 *
 * Existe porque a biblioteca tem 646 exercícios: os cabeçalhos de grupo já
 * separavam visualmente, mas achar "Peito" ainda exigia rolar por tudo que
 * vinha antes. O contador em cada chip evita o clique às cegas — dá pra ver
 * que "Pernas" tem 79 e "Trapézio" 16 antes de escolher.
 */
export default function FiltroGrupoMuscular({
  grupos,
  selecionado,
  onSelecionar,
  total,
}: {
  grupos: { grupo: string; total: number }[]
  selecionado: string | null
  onSelecionar: (grupo: string | null) => void
  total: number
}) {
  if (grupos.length === 0) return null

  return (
    <div
      className="mb-3 flex flex-wrap gap-1.5"
      role="group"
      aria-label="Filtrar exercícios por grupo muscular"
    >
      <Chip
        rotulo="Todos"
        contagem={total}
        ativo={selecionado === null}
        onClick={() => onSelecionar(null)}
      />
      {grupos.map(({ grupo, total: qtd }) => (
        <Chip
          key={grupo}
          rotulo={grupo}
          contagem={qtd}
          ativo={selecionado === grupo}
          onClick={() => onSelecionar(selecionado === grupo ? null : grupo)}
        />
      ))}
    </div>
  )
}

function Chip({
  rotulo,
  contagem,
  ativo,
  onClick,
}: {
  rotulo: string
  contagem: number
  ativo: boolean
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={ativo}
      className={`rounded-full px-3 py-1 text-xs transition ${
        ativo ? 'bg-brand text-white' : 'glass-flat text-ink-soft hover:text-ink'
      }`}
    >
      {rotulo}
      <span className={`ml-1.5 [font-variant-numeric:tabular-nums] ${ativo ? 'text-white/70' : 'text-ink-muted'}`}>
        {contagem}
      </span>
    </button>
  )
}
