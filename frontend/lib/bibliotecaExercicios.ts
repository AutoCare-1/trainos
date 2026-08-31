import { Exercise } from './types'

/**
 * Filtro e agrupamento da biblioteca de exercícios, compartilhado pelas telas
 * que listam ela inteira (montar treino e gerenciar vídeos).
 *
 * Virou módulo próprio quando a biblioteca passou de 75 pra centenas de
 * exercícios: com esse volume, rolar os grupos até achar um exercício ficou
 * inviável, e as duas telas tinham copiado a mesma lógica de agrupar.
 */

/**
 * Ordem em que os grupos aparecem. É a ordem em que um personal costuma pensar
 * um treino (empurrar, puxar, ombro, braço, perna, core) — não alfabética, que
 * jogaria "Antebraço" e "Core" na frente de "Peito" e "Costas".
 * Grupo que aparecer no banco e não estiver aqui vai pro fim, em ordem
 * alfabética, em vez de sumir da tela.
 */
export const ORDEM_GRUPOS = [
  'Peito',
  'Costas',
  'Ombros',
  'Bíceps',
  'Tríceps',
  'Antebraço',
  'Trapézio',
  'Pernas',
  'Posterior',
  'Glúteos',
  'Panturrilha',
  'Core',
  'Funcional',
  // Grupos da leva complementar, depois dos 13 de musculação de propósito: o
  // uso do dia a dia é montar treino de academia, e empurrar 'Ativação' e
  // 'Mobilidade' pro topo só afastaria 'Peito' e 'Costas' do primeiro toque.
  // Entre si seguem a ordem da sessão: esporte, preparo, e alongamento no fim.
  'Esportivo',
  'Ativação',
  'Mobilidade',
  'Equilíbrio',
  'Prevenção',
  'Alongamento',
] as const

function normalizar(texto: string): string {
  return texto
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .trim()
}

/** Grupos presentes na biblioteca, com quantos exercícios cada um tem. */
export function contarPorGrupo(exercises: Exercise[]): { grupo: string; total: number }[] {
  const contagem = new Map<string, number>()
  for (const ex of exercises) {
    contagem.set(ex.muscle_group, (contagem.get(ex.muscle_group) ?? 0) + 1)
  }

  return [...contagem.entries()]
    .map(([grupo, total]) => ({ grupo, total }))
    .sort((a, b) => posicao(a.grupo) - posicao(b.grupo) || a.grupo.localeCompare(b.grupo, 'pt-BR'))
}

function posicao(grupo: string): number {
  const i = (ORDEM_GRUPOS as readonly string[]).indexOf(grupo)
  return i === -1 ? ORDEM_GRUPOS.length : i
}

/**
 * Aplica busca por nome e filtro de grupo, e devolve já agrupado e ordenado.
 *
 * A busca ignora acento dos dois lados: quem digita "biceps" ou "trice" precisa
 * achar "Rosca de bíceps" e "Tríceps corda" — exigir o acento certo num campo
 * de busca rápida é atrito à toa.
 */
export function filtrarEAgrupar(
  exercises: Exercise[],
  termo: string,
  grupoSelecionado: string | null
): { grupo: string; itens: Exercise[] }[] {
  const busca = normalizar(termo)

  const filtrados = exercises.filter((ex) => {
    if (grupoSelecionado && ex.muscle_group !== grupoSelecionado) return false
    if (!busca) return true
    // O equipamento entra na busca junto com o nome: procurar por "elástico"
    // ou "kettlebell" é um jeito legítimo de achar exercício quando o personal
    // sabe o que tem disponível na sala, mas não o nome exato.
    return (
      normalizar(ex.name).includes(busca) ||
      normalizar(ex.equipment ?? '').includes(busca)
    )
  })

  const porGrupo = new Map<string, Exercise[]>()
  for (const ex of filtrados) {
    const lista = porGrupo.get(ex.muscle_group)
    if (lista) lista.push(ex)
    else porGrupo.set(ex.muscle_group, [ex])
  }

  return [...porGrupo.entries()]
    .map(([grupo, itens]) => ({ grupo, itens }))
    .sort((a, b) => posicao(a.grupo) - posicao(b.grupo) || a.grupo.localeCompare(b.grupo, 'pt-BR'))
}
