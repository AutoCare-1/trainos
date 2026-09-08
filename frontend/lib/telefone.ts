/**
 * Máscara de telefone brasileiro pro cadastro de aluno.
 *
 * Aceita qualquer coisa digitada, guarda só os dígitos e formata conforme vai
 * digitando:
 *   ""            → ""
 *   "11"          → "(11"
 *   "1198"        → "(11) 98"
 *   10 dígitos    → "(11) 3456-7890"   (fixo)
 *   11 dígitos    → "(11) 98765-4321"  (celular)
 * Corta em 11 dígitos. É idempotente — reformatar um valor já mascarado dá o
 * mesmo resultado, então dá pra usar tanto no onChange quanto ao carregar um
 * telefone que já estava salvo sem máscara.
 */
export function formatarTelefone(valor: string): string {
  const d = (valor ?? '').replace(/\D/g, '').slice(0, 11)
  if (d.length === 0) return ''
  if (d.length <= 2) return `(${d}`
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`
  if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`
  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`
}
