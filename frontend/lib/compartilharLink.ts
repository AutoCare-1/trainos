/**
 * Envio do link de acesso do aluno — WhatsApp e área de transferência.
 *
 * Os dois pontos que fazem isso (cadastro recém-criado e ficha do aluno)
 * tinham o mesmo par de problemas: o wa.me ia sem número, obrigando o personal
 * a caçar o contato na lista mesmo com o telefone do aluno na mesma tela; e o
 * writeText ia sem catch, então num contexto não-seguro (acesso por IP na rede
 * local, que é como se testa no celular) falhava calado e o personal achava
 * que tinha copiado.
 */

/**
 * Monta a URL do WhatsApp com o número do aluno quando ele existe.
 *
 * Só dígitos, e prefixo 55 quando o número veio sem DDI — é como o telefone é
 * digitado no Brasil ("(11) 98888-7777"). Número que já venha com DDI ou em
 * formato inesperado é usado como está, e sem telefone cai no wa.me sem
 * destinatário (o comportamento antigo), que ainda abre o seletor de contato.
 */
export function linkWhatsApp(telefone: string | null | undefined, mensagem: string): string {
  const texto = encodeURIComponent(mensagem)
  const digitos = (telefone ?? '').replace(/\D/g, '')

  if (!digitos) return `https://wa.me/?text=${texto}`

  // 10 = fixo com DDD, 11 = celular com DDD. Acima disso o DDI já veio junto.
  const comDdi = digitos.length <= 11 ? `55${digitos}` : digitos

  return `https://wa.me/${comDdi}?text=${texto}`
}

/** Mensagem padrão de convite, igual nos dois lugares que enviam o link. */
export function mensagemConvite(nomeCompleto: string, link: string): string {
  return `Oi, ${nomeCompleto.split(' ')[0]}! Aqui está seu acesso ao Clube Mais: ${link}`
}

/**
 * Copia e diz se deu certo. O chamador usa o retorno pra não mostrar
 * "Link copiado" quando não copiou nada.
 */
export async function copiarTexto(texto: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(texto)
    return true
  } catch {
    return false
  }
}
