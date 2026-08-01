'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { api } from '@/lib/api'
import { StatusAssinatura } from '@/lib/types'

/**
 * Aviso sério (teste grátis expirado / pagamento atrasado / assinatura
 * suspensa) — ao contrário do InstallBanner, não tem "fechar": a assinatura
 * segue precisando de atenção até o personal regularizar em /plano. Só
 * cadastro de aluno novo é bloqueado nesses estados, o resto do app continua
 * funcionando — o texto reflete isso, sem soar mais alarmante do que é.
 */
export default function AssinaturaBanner() {
  const [dados, setDados] = useState<StatusAssinatura | null>(null)

  useEffect(() => {
    api
      .get<StatusAssinatura>('/assinatura')
      .then(setDados)
      .catch(() => {})
  }, [])

  if (!dados) return null

  let mensagem: string | null = null
  if (!dados.em_teste && !dados.plano_chave) {
    mensagem = 'Seu teste grátis acabou — escolha um plano pra continuar cadastrando alunos.'
  } else if (dados.status === 'atrasada') {
    const dias = dados.dias_restantes_carencia ?? 0
    mensagem = `Pagamento não aprovado — restam ${dias} dia${dias === 1 ? '' : 's'} antes de travar novos cadastros de aluno.`
  } else if (dados.status === 'bloqueada') {
    mensagem = 'Assinatura suspensa — cadastro de novos alunos bloqueado até regularizar o pagamento.'
  }

  if (!mensagem) return null

  return (
    <div className="glass mb-4 flex items-center justify-between gap-3 rounded-2xl border-danger p-3.5 [--glass-bg:var(--color-danger-soft)]">
      <p className="min-w-0 flex-1 text-sm text-ink-soft">{mensagem}</p>
      <Link href="/plano" className="btn-cta shrink-0 rounded-xl px-3 py-1.5 text-xs">
        Ver plano
      </Link>
    </div>
  )
}
