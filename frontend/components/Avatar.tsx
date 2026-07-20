const CORES = [
  'from-emerald-400 to-teal-500',
  'from-cyan-400 to-blue-500',
  'from-violet-400 to-purple-500',
  'from-rose-400 to-pink-500',
  'from-amber-400 to-orange-500',
]

function iniciais(nome: string): string {
  const partes = nome.trim().split(/\s+/)
  const primeira = partes[0]?.[0] ?? ''
  const ultima = partes.length > 1 ? partes[partes.length - 1][0] : ''
  return (primeira + ultima).toUpperCase()
}

export default function Avatar({ nome, tamanho = 'md' }: { nome: string; tamanho?: 'sm' | 'md' | 'lg' }) {
  const idx = nome.length % CORES.length
  const classes = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-11 w-11 text-sm',
    lg: 'h-14 w-14 text-lg',
  }[tamanho]

  return (
    <span
      className={`flex ${classes} shrink-0 items-center justify-center rounded-full bg-gradient-to-br ${CORES[idx]} font-bold text-[#04110d]`}
    >
      {iniciais(nome)}
    </span>
  )
}
