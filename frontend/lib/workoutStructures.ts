export type StructureType =
  | 'tradicional'
  | 'bi-set'
  | 'tri-set'
  | 'superset'
  | 'circuito'
  | 'drop-set'
  | 'rest-pause'
  | 'cluster'
  | 'amrap'
  | 'emom'

export const ESTRUTURAS: { value: StructureType; label: string; icone: string; agrupavel: boolean }[] = [
  { value: 'tradicional', label: 'Tradicional', icone: '', agrupavel: false },
  { value: 'bi-set', label: 'Bi-set', icone: '🔗', agrupavel: true },
  { value: 'tri-set', label: 'Tri-set', icone: '🔗', agrupavel: true },
  { value: 'superset', label: 'Superset', icone: '🔗', agrupavel: true },
  { value: 'circuito', label: 'Circuito', icone: '🔁', agrupavel: true },
  { value: 'drop-set', label: 'Drop-set', icone: '⬇️', agrupavel: false },
  { value: 'rest-pause', label: 'Rest-pause', icone: '⏸️', agrupavel: false },
  { value: 'cluster', label: 'Cluster', icone: '🔹', agrupavel: false },
  { value: 'amrap', label: 'AMRAP', icone: '⏱️', agrupavel: false },
  { value: 'emom', label: 'EMOM', icone: '⏱️', agrupavel: false },
]

export function rotuloEstrutura(tipo: string | null | undefined): { label: string; icone: string } {
  const found = ESTRUTURAS.find((e) => e.value === tipo)
  return found ?? ESTRUTURAS[0]
}

interface ComEstrutura {
  structure_type?: string | null
  group_label?: string | null
  order_index?: number
}

export interface GrupoExercicios<T> {
  groupLabel: string | null
  structureType: string
  itens: T[]
}

// Agrupa exercícios consecutivos (na ordem em que já vêm) que compartilham o
// mesmo group_label não-vazio — usado pra exibir bi-sets/circuitos juntos,
// em vez de um exercício por card isolado.
export function agruparExercicios<T extends ComEstrutura>(exercicios: T[]): GrupoExercicios<T>[] {
  const grupos: GrupoExercicios<T>[] = []
  for (const ex of exercicios) {
    const label = ex.group_label?.trim() || null
    const ultimo = grupos[grupos.length - 1]
    if (label && ultimo && ultimo.groupLabel === label) {
      ultimo.itens.push(ex)
    } else {
      grupos.push({ groupLabel: label, structureType: ex.structure_type || 'tradicional', itens: [ex] })
    }
  }
  return grupos
}
