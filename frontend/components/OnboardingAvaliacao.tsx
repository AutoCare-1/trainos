'use client'

import { useRef, useState } from 'react'
import Image from 'next/image'
import Avatar from '@/components/Avatar'
import { ANAMNESE_VAZIA, LOCAL_TREINO_OPCOES, OBJETIVOS_OPCOES } from '@/lib/anamnese'
import { PAR_Q_PERGUNTAS, PAR_Q_VAZIO } from '@/lib/parq'
import { Anamnese, ParQAnswers } from '@/lib/types'

function alternarNoConjunto(lista: string[], valor: string): string[] {
  return lista.includes(valor) ? lista.filter((v) => v !== valor) : [...lista, valor]
}

export default function OnboardingAvaliacao({
  nome,
  onEnviar,
}: {
  nome: string
  onEnviar: (
    parQ: ParQAnswers,
    healthNotes: string,
    foto: File | null,
    birthDate: string,
    anamnese: Anamnese
  ) => Promise<void>
}) {
  const [parQ, setParQ] = useState<ParQAnswers>(PAR_Q_VAZIO)
  const [healthNotes, setHealthNotes] = useState('')
  const [birthDate, setBirthDate] = useState('')
  const [anamnese, setAnamnese] = useState<Anamnese>(ANAMNESE_VAZIA)
  const [foto, setFoto] = useState<File | null>(null)
  const [fotoPreview, setFotoPreview] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const fotoInputRef = useRef<HTMLInputElement | null>(null)

  function escolherFoto(file: File) {
    setFoto(file)
    setFotoPreview(URL.createObjectURL(file))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setEnviando(true)
    setErro(null)
    try {
      await onEnviar(parQ, healthNotes, foto, birthDate, anamnese)
    } catch {
      setErro('Não foi possível enviar. Tente de novo.')
    } finally {
      setEnviando(false)
    }
  }

  const inputCls = 'input-dark w-full rounded-xl px-4 py-2.5 text-sm'
  const labelCls = 'mb-1.5 block text-sm font-medium text-ink-soft'
  const secaoTituloCls = 'mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted'

  return (
    <main className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
      <div className="w-full max-w-md">
        <div className="mb-6 flex flex-col items-center text-center">
          <Image src="/clubemais-logo.png" alt="Clube Mais" width={200} height={56} priority className="h-12 w-auto" />
          <h1 className="mt-5 text-xl font-bold text-ink">Oi, {nome.split(' ')[0]}!</h1>
          <p className="mt-2 text-sm text-ink-muted">
            Antes de ver seu treino, responda essa anamnese inicial — leva só alguns minutos e ajuda seu professor a
            montar o treino certo pra você.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="glass space-y-6 rounded-2xl p-6">
          <div className="flex flex-col items-center gap-2">
            {fotoPreview ? (
              // eslint-disable-next-line @next/next/no-img-element -- preview local, ainda não é URL do backend
              <img src={fotoPreview} alt="Sua foto" className="h-20 w-20 rounded-full object-cover" />
            ) : (
              <Avatar nome={nome} tamanho="lg" />
            )}
            <button
              type="button"
              onClick={() => fotoInputRef.current?.click()}
              className="text-xs font-medium text-brand"
            >
              {fotoPreview ? 'Trocar foto' : 'Adicionar sua foto (opcional)'}
            </button>
            <input
              ref={fotoInputRef}
              type="file"
              accept="image/*"
              capture="user"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) escolherFoto(file)
              }}
            />
          </div>

          <div>
            <label className={labelCls}>Data de nascimento (opcional)</label>
            <input type="date" value={birthDate} onChange={(e) => setBirthDate(e.target.value)} className={inputCls} />
          </div>

          {/* Saúde — PAR-Q (já existia) */}
          <div>
            <h2 className={secaoTituloCls}>Saúde</h2>
            <div className="space-y-2.5">
              {PAR_Q_PERGUNTAS.map(({ chave, texto }) => (
                <label key={chave} className="flex cursor-pointer items-start gap-2.5 text-sm text-ink-soft">
                  <input
                    type="checkbox"
                    checked={parQ[chave]}
                    onChange={(e) => setParQ({ ...parQ, [chave]: e.target.checked })}
                    className="mt-0.5 h-4 w-4 shrink-0 accent-brand"
                  />
                  {texto}
                </label>
              ))}
            </div>
          </div>

          {/* Condições de saúde (complemento) */}
          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Condições de saúde</h2>
            <div>
              <label className={labelCls}>Possui alguma restrição médica para atividade física?</label>
              <input
                value={anamnese.condicoes_saude.restricao_medica}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    condicoes_saude: { ...anamnese.condicoes_saude, restricao_medica: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Já teve ou tem alguma doença diagnosticada?</label>
              <input
                value={anamnese.condicoes_saude.doenca_diagnosticada}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    condicoes_saude: { ...anamnese.condicoes_saude, doenca_diagnosticada: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Já sofreu alguma lesão?</label>
              <input
                value={anamnese.condicoes_saude.lesao}
                onChange={(e) =>
                  setAnamnese({ ...anamnese, condicoes_saude: { ...anamnese.condicoes_saude, lesao: e.target.value } })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Faz uso de medicamentos? Quais?</label>
              <input
                value={anamnese.condicoes_saude.medicamentos}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    condicoes_saude: { ...anamnese.condicoes_saude, medicamentos: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Faz uso de suplementos alimentares? Quais?</label>
              <input
                value={anamnese.condicoes_saude.suplementos}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    condicoes_saude: { ...anamnese.condicoes_saude, suplementos: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Possui alergias?</label>
              <input
                value={anamnese.condicoes_saude.alergias}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    condicoes_saude: { ...anamnese.condicoes_saude, alergias: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Cirurgias ou outras observações de saúde (opcional)</label>
              <textarea
                value={healthNotes}
                onChange={(e) => setHealthNotes(e.target.value)}
                rows={2}
                className={inputCls}
              />
            </div>
          </div>

          {/* Histórico de atividade física */}
          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Histórico de atividade física</h2>
            <div>
              <label className={labelCls}>Já praticou algum tipo de exercício físico regularmente? Quais?</label>
              <input
                value={anamnese.historico_atividade_fisica.ja_praticou}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    historico_atividade_fisica: { ...anamnese.historico_atividade_fisica, ja_praticou: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Atualmente pratica algum esporte ou atividade física?</label>
              <input
                value={anamnese.historico_atividade_fisica.pratica_atualmente}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    historico_atividade_fisica: {
                      ...anamnese.historico_atividade_fisica,
                      pratica_atualmente: e.target.value,
                    },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Exercícios/modalidades que mais gosta</label>
              <input
                value={anamnese.historico_atividade_fisica.modalidades_favoritas}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    historico_atividade_fisica: {
                      ...anamnese.historico_atividade_fisica,
                      modalidades_favoritas: e.target.value,
                    },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Exercícios que não gosta de fazer</label>
              <input
                value={anamnese.historico_atividade_fisica.modalidades_nao_gosta}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    historico_atividade_fisica: {
                      ...anamnese.historico_atividade_fisica,
                      modalidades_nao_gosta: e.target.value,
                    },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <span className={labelCls}>Já treinou com personal trainer antes?</span>
              <div className="flex gap-4 text-sm text-ink-soft">
                <label className="flex cursor-pointer items-center gap-1.5">
                  <input
                    type="radio"
                    name="treinou_com_personal"
                    checked={anamnese.historico_atividade_fisica.treinou_com_personal === true}
                    onChange={() =>
                      setAnamnese({
                        ...anamnese,
                        historico_atividade_fisica: { ...anamnese.historico_atividade_fisica, treinou_com_personal: true },
                      })
                    }
                    className="accent-brand"
                  />
                  Sim
                </label>
                <label className="flex cursor-pointer items-center gap-1.5">
                  <input
                    type="radio"
                    name="treinou_com_personal"
                    checked={anamnese.historico_atividade_fisica.treinou_com_personal === false}
                    onChange={() =>
                      setAnamnese({
                        ...anamnese,
                        historico_atividade_fisica: {
                          ...anamnese.historico_atividade_fisica,
                          treinou_com_personal: false,
                        },
                      })
                    }
                    className="accent-brand"
                  />
                  Não
                </label>
              </div>
            </div>
          </div>

          {/* Objetivos */}
          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Objetivos</h2>
            <div className="grid grid-cols-2 gap-2">
              {OBJETIVOS_OPCOES.map(({ valor, label }) => (
                <label key={valor} className="flex cursor-pointer items-start gap-2 text-sm text-ink-soft">
                  <input
                    type="checkbox"
                    checked={anamnese.objetivos.selecionados.includes(valor)}
                    onChange={() =>
                      setAnamnese({
                        ...anamnese,
                        objetivos: {
                          ...anamnese.objetivos,
                          selecionados: alternarNoConjunto(anamnese.objetivos.selecionados, valor),
                        },
                      })
                    }
                    className="mt-0.5 h-4 w-4 shrink-0 accent-brand"
                  />
                  {label}
                </label>
              ))}
            </div>
            <div>
              <label className={labelCls}>Outro objetivo (opcional)</label>
              <input
                value={anamnese.objetivos.outro}
                onChange={(e) => setAnamnese({ ...anamnese, objetivos: { ...anamnese.objetivos, outro: e.target.value } })}
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Em quanto tempo gostaria de atingir seus objetivos?</label>
              <input
                value={anamnese.objetivos.prazo}
                onChange={(e) => setAnamnese({ ...anamnese, objetivos: { ...anamnese.objetivos, prazo: e.target.value } })}
                className={inputCls}
              />
            </div>
          </div>

          {/* Estilo de vida */}
          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Estilo de vida</h2>
            <div>
              <label className={labelCls}>Profissão</label>
              <input
                value={anamnese.estilo_de_vida.profissao}
                onChange={(e) =>
                  setAnamnese({ ...anamnese, estilo_de_vida: { ...anamnese.estilo_de_vida, profissao: e.target.value } })
                }
                className={inputCls}
              />
            </div>
            <div>
              <span className={labelCls}>Nível de estresse</span>
              <div className="flex gap-4 text-sm text-ink-soft">
                {(['baixo', 'medio', 'alto'] as const).map((valor) => (
                  <label key={valor} className="flex cursor-pointer items-center gap-1.5 capitalize">
                    <input
                      type="radio"
                      name="nivel_estresse"
                      checked={anamnese.estilo_de_vida.nivel_estresse === valor}
                      onChange={() =>
                        setAnamnese({
                          ...anamnese,
                          estilo_de_vida: { ...anamnese.estilo_de_vida, nivel_estresse: valor },
                        })
                      }
                      className="accent-brand"
                    />
                    {valor === 'medio' ? 'Médio' : valor}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <span className={labelCls}>Qualidade do sono</span>
              <div className="flex gap-4 text-sm text-ink-soft">
                {(['boa', 'regular', 'ruim'] as const).map((valor) => (
                  <label key={valor} className="flex cursor-pointer items-center gap-1.5 capitalize">
                    <input
                      type="radio"
                      name="qualidade_sono"
                      checked={anamnese.estilo_de_vida.qualidade_sono === valor}
                      onChange={() =>
                        setAnamnese({
                          ...anamnese,
                          estilo_de_vida: { ...anamnese.estilo_de_vida, qualidade_sono: valor },
                        })
                      }
                      className="accent-brand"
                    />
                    {valor}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <label className={labelCls}>Média de horas de sono por noite</label>
              <input
                value={anamnese.estilo_de_vida.horas_sono}
                onChange={(e) =>
                  setAnamnese({ ...anamnese, estilo_de_vida: { ...anamnese.estilo_de_vida, horas_sono: e.target.value } })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Como é sua alimentação no dia a dia?</label>
              <input
                value={anamnese.estilo_de_vida.alimentacao}
                onChange={(e) =>
                  setAnamnese({ ...anamnese, estilo_de_vida: { ...anamnese.estilo_de_vida, alimentacao: e.target.value } })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Segue algum plano alimentar específico?</label>
              <input
                value={anamnese.estilo_de_vida.plano_alimentar}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    estilo_de_vida: { ...anamnese.estilo_de_vida, plano_alimentar: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Frequência de consumo de álcool</label>
              <input
                value={anamnese.estilo_de_vida.frequencia_alcool}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    estilo_de_vida: { ...anamnese.estilo_de_vida, frequencia_alcool: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <span className={labelCls}>Hábito de fumar?</span>
              <div className="flex gap-4 text-sm text-ink-soft">
                <label className="flex cursor-pointer items-center gap-1.5">
                  <input
                    type="radio"
                    name="fumante"
                    checked={anamnese.estilo_de_vida.fumante === true}
                    onChange={() =>
                      setAnamnese({ ...anamnese, estilo_de_vida: { ...anamnese.estilo_de_vida, fumante: true } })
                    }
                    className="accent-brand"
                  />
                  Sim
                </label>
                <label className="flex cursor-pointer items-center gap-1.5">
                  <input
                    type="radio"
                    name="fumante"
                    checked={anamnese.estilo_de_vida.fumante === false}
                    onChange={() =>
                      setAnamnese({
                        ...anamnese,
                        estilo_de_vida: { ...anamnese.estilo_de_vida, fumante: false, tempo_fumante: '' },
                      })
                    }
                    className="accent-brand"
                  />
                  Não
                </label>
              </div>
              {anamnese.estilo_de_vida.fumante && (
                <input
                  placeholder="Há quanto tempo?"
                  value={anamnese.estilo_de_vida.tempo_fumante}
                  onChange={(e) =>
                    setAnamnese({
                      ...anamnese,
                      estilo_de_vida: { ...anamnese.estilo_de_vida, tempo_fumante: e.target.value },
                    })
                  }
                  className={`${inputCls} mt-2`}
                />
              )}
            </div>
          </div>

          {/* Aspectos motivacionais e preferências */}
          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Motivação e preferências</h2>
            <div>
              <label className={labelCls}>O que te motiva a treinar?</label>
              <input
                value={anamnese.motivacao.motivacao}
                onChange={(e) => setAnamnese({ ...anamnese, motivacao: { ...anamnese.motivacao, motivacao: e.target.value } })}
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>O que pode atrapalhar sua rotina de treinos?</label>
              <input
                value={anamnese.motivacao.obstaculos}
                onChange={(e) => setAnamnese({ ...anamnese, motivacao: { ...anamnese.motivacao, obstaculos: e.target.value } })}
                className={inputCls}
              />
            </div>
            <div>
              <span className={labelCls}>Prefere treinos</span>
              <div className="flex flex-col gap-1.5 text-sm text-ink-soft">
                <label className="flex cursor-pointer items-center gap-1.5">
                  <input
                    type="radio"
                    name="preferencia_intensidade"
                    checked={anamnese.motivacao.preferencia_intensidade === 'curtos_intensos'}
                    onChange={() =>
                      setAnamnese({
                        ...anamnese,
                        motivacao: { ...anamnese.motivacao, preferencia_intensidade: 'curtos_intensos' },
                      })
                    }
                    className="accent-brand"
                  />
                  Curtos e intensos
                </label>
                <label className="flex cursor-pointer items-center gap-1.5">
                  <input
                    type="radio"
                    name="preferencia_intensidade"
                    checked={anamnese.motivacao.preferencia_intensidade === 'longos_moderados'}
                    onChange={() =>
                      setAnamnese({
                        ...anamnese,
                        motivacao: { ...anamnese.motivacao, preferencia_intensidade: 'longos_moderados' },
                      })
                    }
                    className="accent-brand"
                  />
                  Longos e moderados
                </label>
              </div>
            </div>
            <div>
              <span className={labelCls}>Prefere treinar</span>
              <div className="flex flex-col gap-1.5 text-sm text-ink-soft">
                {(
                  [
                    ['sozinho', 'Sozinho'],
                    ['grupo', 'Em grupo'],
                    ['acompanhamento', 'Com acompanhamento constante'],
                  ] as const
                ).map(([valor, label]) => (
                  <label key={valor} className="flex cursor-pointer items-center gap-1.5">
                    <input
                      type="radio"
                      name="preferencia_companhia"
                      checked={anamnese.motivacao.preferencia_companhia === valor}
                      onChange={() =>
                        setAnamnese({
                          ...anamnese,
                          motivacao: { ...anamnese.motivacao, preferencia_companhia: valor },
                        })
                      }
                      className="accent-brand"
                    />
                    {label}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <label className={labelCls}>Horários de maior energia/disponibilidade</label>
              <input
                value={anamnese.motivacao.horario_disponivel}
                onChange={(e) =>
                  setAnamnese({ ...anamnese, motivacao: { ...anamnese.motivacao, horario_disponivel: e.target.value } })
                }
                className={inputCls}
              />
            </div>
          </div>

          {/* Disponibilidade */}
          <div className="space-y-3">
            <h2 className={secaoTituloCls}>Disponibilidade</h2>
            <div>
              <label className={labelCls}>Quantas vezes por semana pode treinar?</label>
              <input
                value={anamnese.disponibilidade.vezes_por_semana}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    disponibilidade: { ...anamnese.disponibilidade, vezes_por_semana: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Quanto tempo tem disponível por treino?</label>
              <input
                value={anamnese.disponibilidade.tempo_por_treino}
                onChange={(e) =>
                  setAnamnese({
                    ...anamnese,
                    disponibilidade: { ...anamnese.disponibilidade, tempo_por_treino: e.target.value },
                  })
                }
                className={inputCls}
              />
            </div>
            <div>
              <span className={labelCls}>Onde pretende treinar?</span>
              <div className="grid grid-cols-2 gap-2">
                {LOCAL_TREINO_OPCOES.map(({ valor, label }) => (
                  <label key={valor} className="flex cursor-pointer items-center gap-2 text-sm text-ink-soft">
                    <input
                      type="checkbox"
                      checked={anamnese.disponibilidade.local_treino.includes(valor)}
                      onChange={() =>
                        setAnamnese({
                          ...anamnese,
                          disponibilidade: {
                            ...anamnese.disponibilidade,
                            local_treino: alternarNoConjunto(anamnese.disponibilidade.local_treino, valor),
                          },
                        })
                      }
                      className="h-4 w-4 shrink-0 accent-brand"
                    />
                    {label}
                  </label>
                ))}
              </div>
            </div>
          </div>

          {/* Histórico familiar */}
          <div>
            <h2 className={secaoTituloCls}>Histórico familiar</h2>
            <label className={labelCls}>
              Casos de doenças crônicas na família (diabetes, hipertensão, cardíacas, câncer, etc.)
            </label>
            <textarea
              value={anamnese.historico_familiar}
              onChange={(e) => setAnamnese({ ...anamnese, historico_familiar: e.target.value })}
              rows={2}
              className={inputCls}
            />
          </div>

          {erro && <p className="text-sm text-danger">{erro}</p>}

          <button type="submit" disabled={enviando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {enviando ? 'Enviando...' : 'Continuar'}
          </button>
        </form>
      </div>
    </main>
  )
}
