/**
 * A prescrição de repetições é texto livre digitado pelo personal ("10-12",
 * "10", "8 a 12", "até a falha"). Espelha App\Support\Progressao::parseFaixaReps
 * no backend — os dois precisam concordar sobre o que é uma faixa, senão o app
 * aceita um registro que a sugestão de progressão depois não sabe avaliar.
 */
export function parseFaixaReps(reps: string | null): { min: number; max: number } | null {
  const achados = (reps ?? '').match(/\d+/g)
  if (!achados || achados.length > 2) return null

  const numeros = achados.map(Number)
  const min = Math.min(...numeros)
  const max = Math.max(...numeros)

  return min > 0 ? { min, max } : null
}

/**
 * Valor inicial do campo "Reps" na execução do treino.
 *
 * Só preenche quando a prescrição é um número exato ("10") — aí não há dúvida
 * do que o aluno vai fazer. Numa faixa ("10-12") devolve vazio de propósito:
 * preencher com o topo faria o app registrar sozinho que o aluno bateu a meta
 * em toda série, e a sugestão de progressão (que dispara exatamente nesse
 * critério) passaria a mandar subir carga que ninguém levantou. Preencher com
 * o mínimo tem o problema espelhado — trava o aluno em "manter" pra sempre.
 *
 * Então: número exato preenche, faixa o aluno informa.
 */
export function repsIniciais(repsPrescritas: string | null): string {
  const faixa = parseFaixaReps(repsPrescritas)
  return faixa && faixa.min === faixa.max ? String(faixa.min) : ''
}
