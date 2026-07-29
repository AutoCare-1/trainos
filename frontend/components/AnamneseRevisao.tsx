'use client'

import { useState } from 'react'
import Image from 'next/image'
import { ASPECTOS_PROGRESSO_OPCOES, RESPOSTAS_REVISAO_VAZIA } from '@/lib/anamneseRevisao'
import { RespostasRevisao } from '@/lib/types'

function alternarNoConjunto(lista: string[], valor: string): string[] {
  return lista.includes(valor) ? lista.filter((v) => v !== valor) : [...lista, valor]
}

export default function AnamneseRevisao({
  nome,
  nomeTreino,
  onEnviar,
}: {
  nome: string
  nomeTreino: string
  onEnviar: (respostas: RespostasRevisao) => Promise<void>
}) {
  const [respostas, setRespostas] = useState<RespostasRevisao>(RESPOSTAS_REVISAO_VAZIA)
  const [enviando, setEnviando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setEnviando(true)
    setErro(null)
    try {
      await onEnviar(respostas)
    } catch {
      setErro('Não foi possível enviar. Tente de novo.')
    } finally {
      setEnviando(false)
    }
  }

  const inputCls = 'input-dark w-full rounded-xl px-4 py-2.5 text-sm'
  const labelCls = 'mb-1.5 block text-sm font-medium text-slate-600'
  const secaoTituloCls = 'mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500'

  return (
    <main className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
      <div className="w-full max-w-md">
        <div className="mb-6 flex flex-col items-center text-center">
          <Image src="/clubemais-logo.png" alt="Clube Mais" width={200} height={56} priority className="h-12 w-auto" />
          <h1 className="mt-5 text-xl font-bold text-slate-900">Oi, {nome.split(' ')[0]}!</h1>
          <p className="mt-2 text-sm text-slate-500">
            Seu treino &quot;{nomeTreino}&quot; chegou ao fim do período — antes do próximo, seu professor pediu
            pra você responder essa revisão rápida.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="glass space-y-6 rounded-2xl p-6">
          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Avaliação do treino</h2>
            <div>
              <span className={labelCls}>Como você avalia sua experiência com os treinos até agora?</span>
              <div className="flex flex-wrap gap-4 text-sm text-slate-700">
                {(
                  [
                    ['excelente', 'Excelente'],
                    ['boa', 'Boa'],
                    ['regular', 'Regular'],
                    ['ruim', 'Ruim'],
                  ] as const
                ).map(([valor, label]) => (
                  <label key={valor} className="flex cursor-pointer items-center gap-1.5">
                    <input
                      type="radio"
                      name="avaliacao_treino"
                      checked={respostas.avaliacao_treino === valor}
                      onChange={() => setRespostas({ ...respostas, avaliacao_treino: valor })}
                      className="accent-[#2648b3]"
                    />
                    {label}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <label className={labelCls}>O que você mais gostou nos treinos?</label>
              <input
                value={respostas.gostou_mais}
                onChange={(e) => setRespostas({ ...respostas, gostou_mais: e.target.value })}
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>
                Teve algum exercício ou tipo de treino que não gostou ou se sentiu desconfortável?
              </label>
              <input
                value={respostas.nao_gostou}
                onChange={(e) => setRespostas({ ...respostas, nao_gostou: e.target.value })}
                className={inputCls}
              />
            </div>
            <div>
              <span className={labelCls}>Você percebeu evolução nos seus objetivos iniciais?</span>
              <div className="flex flex-col gap-1.5 text-sm text-slate-700">
                {(
                  [
                    ['sim_bastante', 'Sim, bastante'],
                    ['sim_poderia_mais', 'Sim, mas poderia ser mais'],
                    ['pouco', 'Pouco'],
                    ['ainda_nao', 'Ainda não'],
                  ] as const
                ).map(([valor, label]) => (
                  <label key={valor} className="flex cursor-pointer items-center gap-1.5">
                    <input
                      type="radio"
                      name="percebeu_evolucao"
                      checked={respostas.percebeu_evolucao === valor}
                      onChange={() => setRespostas({ ...respostas, percebeu_evolucao: valor })}
                      className="accent-[#2648b3]"
                    />
                    {label}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <span className={labelCls}>Em quais aspectos você sente maior progresso?</span>
              <div className="grid grid-cols-2 gap-2">
                {ASPECTOS_PROGRESSO_OPCOES.map(({ valor, label }) => (
                  <label key={valor} className="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                    <input
                      type="checkbox"
                      checked={respostas.aspectos_progresso.includes(valor)}
                      onChange={() =>
                        setRespostas({
                          ...respostas,
                          aspectos_progresso: alternarNoConjunto(respostas.aspectos_progresso, valor),
                        })
                      }
                      className="mt-0.5 h-4 w-4 shrink-0 accent-[#2648b3]"
                    />
                    {label}
                  </label>
                ))}
              </div>
              <input
                placeholder="Outro (opcional)"
                value={respostas.aspectos_progresso_outro}
                onChange={(e) => setRespostas({ ...respostas, aspectos_progresso_outro: e.target.value })}
                className={`${inputCls} mt-2`}
              />
            </div>
          </div>

          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Adesão e rotina</h2>
            <div>
              <span className={labelCls}>Conseguiu manter a frequência planejada?</span>
              <div className="flex gap-4 text-sm text-slate-700">
                {(
                  [
                    ['sim', 'Sim'],
                    ['parcialmente', 'Parcialmente'],
                    ['nao', 'Não'],
                  ] as const
                ).map(([valor, label]) => (
                  <label key={valor} className="flex cursor-pointer items-center gap-1.5">
                    <input
                      type="radio"
                      name="manteve_frequencia"
                      checked={respostas.manteve_frequencia === valor}
                      onChange={() => setRespostas({ ...respostas, manteve_frequencia: valor })}
                      className="accent-[#2648b3]"
                    />
                    {label}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <label className={labelCls}>Em média, quantos treinos por semana você conseguiu realizar?</label>
              <input
                type="number"
                min={0}
                max={14}
                inputMode="numeric"
                value={respostas.treinos_por_semana}
                onChange={(e) => setRespostas({ ...respostas, treinos_por_semana: e.target.value })}
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Houve alguma dificuldade para manter a rotina?</label>
              <input
                value={respostas.dificuldade_rotina}
                onChange={(e) => setRespostas({ ...respostas, dificuldade_rotina: e.target.value })}
                className={inputCls}
              />
            </div>
          </div>

          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Feedback e ajustes</h2>
            <div>
              <label className={labelCls}>O que podemos melhorar no seu treino daqui para frente?</label>
              <input
                value={respostas.sugestao_melhoria}
                onChange={(e) => setRespostas({ ...respostas, sugestao_melhoria: e.target.value })}
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Gostaria de incluir algum exercício, atividade ou modalidade diferente?</label>
              <input
                value={respostas.sugestao_modalidade}
                onChange={(e) => setRespostas({ ...respostas, sugestao_modalidade: e.target.value })}
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Sugestões gerais para o acompanhamento</label>
              <textarea
                value={respostas.sugestao_geral}
                onChange={(e) => setRespostas({ ...respostas, sugestao_geral: e.target.value })}
                rows={2}
                className={inputCls}
              />
            </div>
          </div>

          {erro && <p className="text-sm text-rose-500">{erro}</p>}

          <button type="submit" disabled={enviando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {enviando ? 'Enviando...' : 'Enviar revisão'}
          </button>
        </form>
      </div>
    </main>
  )
}
