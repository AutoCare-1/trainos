import { resolveMediaUrl } from '@/lib/api'

const CORES = ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5']

function iniciais(nome: string): string {
  const partes = nome.trim().split(/\s+/)
  const primeira = partes[0]?.[0] ?? ''
  const ultima = partes.length > 1 ? partes[partes.length - 1][0] : ''
  return (primeira + ultima).toUpperCase()
}

export default function Avatar({
  nome,
  fotoUrl,
  tamanho = 'md',
}: {
  nome: string
  fotoUrl?: string | null
  tamanho?: 'sm' | 'md' | 'lg'
}) {
  const classes = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-11 w-11 text-sm',
    lg: 'h-14 w-14 text-lg',
  }[tamanho]

  if (fotoUrl) {
    // eslint-disable-next-line @next/next/no-img-element -- foto vem do backend (não do domínio otimizado pelo next/image)
    return (
      <img
        src={resolveMediaUrl(fotoUrl)}
        alt={nome}
        className={`${classes} shrink-0 rounded-full object-cover`}
      />
    )
  }

  const idx = nome.length % CORES.length
  return (
    <span
      className={`flex ${classes} shrink-0 items-center justify-center rounded-full ${CORES[idx]} font-bold text-[#04110d]`}
    >
      {iniciais(nome)}
    </span>
  )
}
