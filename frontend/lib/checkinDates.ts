// Utilitários de data pro histórico de check-ins. Usam sempre o construtor local
// `new Date(ano, mes, dia)` — nunca `new Date(string)` nem `toISOString()` — pra
// não sofrer deslocamento de fuso horário na hora de somar dias/meses.

const NOME_MES: Record<number, string> = {
  1: 'Janeiro',
  2: 'Fevereiro',
  3: 'Março',
  4: 'Abril',
  5: 'Maio',
  6: 'Junho',
  7: 'Julho',
  8: 'Agosto',
  9: 'Setembro',
  10: 'Outubro',
  11: 'Novembro',
  12: 'Dezembro',
}

export function nomeMes(mes: number): string {
  return NOME_MES[mes] ?? ''
}

export function formatarDataCurta(iso: string): string {
  const [, mes, dia] = iso.split('-')
  return `${dia}/${mes}`
}

export function somarDias(iso: string, dias: number): string {
  const [ano, mes, dia] = iso.split('-').map(Number)
  const d = new Date(ano, mes - 1, dia + dias)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function primeiroDiaMes(ano: number, mes: number): string {
  const d = new Date(ano, mes - 1, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

export function primeiroDiaAno(ano: number): string {
  return `${ano}-01-01`
}

export function formatarDataLonga(iso: string): string {
  const [ano, mes, dia] = iso.split('-').map(Number)
  const d = new Date(ano, mes - 1, dia)
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })
}
