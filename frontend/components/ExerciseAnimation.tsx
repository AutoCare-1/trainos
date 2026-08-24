'use client'

import { useEffect, useRef } from 'react'
import { Maximize2 } from 'lucide-react'
import { getMovementPattern, MovementPattern } from '@/lib/exercisePatterns'
import { resolveMediaUrl } from '@/lib/api'

/**
 * Elemento de vídeo com prefixos vendor que os browsers ainda usam pra
 * fullscreen — Safari (desktop e iOS) não implementa a API padrão até hoje.
 * No iOS especificamente só existe fullscreen do próprio <video>
 * (webkitEnterFullscreen), não de um wrapper qualquer — por isso o clique tem
 * que mirar o elemento de vídeo, nunca uma div em volta dele.
 */
type VideoComFullscreenVendor = HTMLVideoElement & {
  webkitEnterFullscreen?: () => void
  webkitRequestFullscreen?: () => void
}

function abrirVideoEmTelaCheia(video: HTMLVideoElement) {
  video.muted = false
  video.controls = true

  const v = video as VideoComFullscreenVendor
  if (v.webkitEnterFullscreen) {
    v.webkitEnterFullscreen()
  } else if (video.requestFullscreen) {
    video.requestFullscreen()
  } else if (v.webkitRequestFullscreen) {
    v.webkitRequestFullscreen()
  }
}

/**
 * A biblioteca tem centenas de exercícios e a maioria não tem foto, então cai
 * nesta animação. A tela de montar treino renderiza a lista inteira de uma vez,
 * o que colocava ~1000 <animateTransform> rodando ao mesmo tempo — a esmagadora
 * maioria fora da viewport, gastando CPU (e bateria, num app que roda no
 * celular) sem ninguém ver.
 *
 * A saída é pausar quem está fora da tela em vez de simplificar o desenho:
 * 6 dos padrões de movimento (agachamento, afundo, panturrilha, hip thrust,
 * encolhimento e o genérico) são feitos SÓ de translação do corpo inteiro, ou
 * seja, sem a animação eles viram a mesma figura em pé e deixam de se
 * distinguir — 27% da biblioteca viraria o mesmo boneco.
 *
 * Um observer só pra todos os SVGs: 600 observers seriam o próprio problema
 * que se quer evitar.
 */
let observador: IntersectionObserver | null = null
function obterObservador(): IntersectionObserver | null {
  if (typeof IntersectionObserver === 'undefined') return null
  observador ??= new IntersectionObserver(
    (entradas) => {
      for (const entrada of entradas) {
        const svg = entrada.target as SVGSVGElement
        // continue, não return: um elemento sem suporte não pode fazer o lote
        // inteiro de entradas ser descartado.
        if (typeof svg.pauseAnimations !== 'function') continue
        if (entrada.isIntersecting) svg.unpauseAnimations()
        else svg.pauseAnimations()
      }
    },
    // Margem pra animação já estar rodando quando o item entra de fato na tela.
    { rootMargin: '200px' }
  )
  return observador
}

// "md" aumentado de 64 pra 112 — é o tamanho usado onde o aluno/personal
// realmente assiste a demonstração pra executar o exercício certo (treino do
// aluno, ficha do treino, gestão de vídeos), então precisa ser bem visível.
const SIZES = { sm: 40, md: 112, lg: 128 } as const

// coordenadas base da figura em pé (viewBox 0 0 100 100)
const HEAD = { cx: 50, cy: 16, r: 7 }
const NECK: [number, number] = [50, 23]
const SHOULDER_L: [number, number] = [40, 25]
const SHOULDER_R: [number, number] = [60, 25]
const HIP_C: [number, number] = [50, 55]
const HIP_L: [number, number] = [44, 55]
const HIP_R: [number, number] = [56, 55]
const ARM_LEN = 28
const LEG_LEN = 40

function RotatingLine({
  pivot,
  length,
  from,
  to,
  dur = 1.5,
  strokeWidth = 3.2,
}: {
  pivot: [number, number]
  length: number
  from: number
  to: number
  dur?: number
  strokeWidth?: number
}) {
  const [px, py] = pivot
  const values = `${from} ${px} ${py};${to} ${px} ${py};${to} ${px} ${py};${from} ${px} ${py}`
  return (
    <g transform={`rotate(${from} ${px} ${py})`}>
      <line
        x1={px}
        y1={py}
        x2={px}
        y2={py + length}
        stroke="currentColor"
        strokeWidth={strokeWidth}
        strokeLinecap="round"
      />
      <animateTransform
        attributeName="transform"
        type="rotate"
        values={values}
        keyTimes="0;0.4;0.6;1"
        dur={`${dur}s`}
        repeatCount="indefinite"
      />
    </g>
  )
}

function Head() {
  return <circle cx={HEAD.cx} cy={HEAD.cy} r={HEAD.r} stroke="currentColor" strokeWidth={3} fill="none" />
}
function ShoulderBar() {
  return (
    <line
      x1={SHOULDER_L[0]}
      y1={SHOULDER_L[1]}
      x2={SHOULDER_R[0]}
      y2={SHOULDER_R[1]}
      stroke="currentColor"
      strokeWidth={3}
      strokeLinecap="round"
    />
  )
}
function Torso() {
  return (
    <line
      x1={NECK[0]}
      y1={NECK[1]}
      x2={HIP_C[0]}
      y2={HIP_C[1]}
      stroke="currentColor"
      strokeWidth={3}
      strokeLinecap="round"
    />
  )
}
function HipBar() {
  return (
    <line
      x1={HIP_L[0]}
      y1={HIP_L[1]}
      x2={HIP_R[0]}
      y2={HIP_R[1]}
      stroke="currentColor"
      strokeWidth={3}
      strokeLinecap="round"
    />
  )
}
function StaticArms() {
  return (
    <>
      <line x1={SHOULDER_L[0]} y1={SHOULDER_L[1]} x2={SHOULDER_L[0]} y2={SHOULDER_L[1] + ARM_LEN} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
      <line x1={SHOULDER_R[0]} y1={SHOULDER_R[1]} x2={SHOULDER_R[0]} y2={SHOULDER_R[1] + ARM_LEN} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
    </>
  )
}
function StaticLegs() {
  return (
    <>
      <line x1={HIP_L[0]} y1={HIP_L[1]} x2={HIP_L[0]} y2={HIP_L[1] + LEG_LEN} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
      <line x1={HIP_R[0]} y1={HIP_R[1]} x2={HIP_R[0]} y2={HIP_R[1] + LEG_LEN} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
    </>
  )
}

function TranslateGroup({
  values,
  dur,
  children,
}: {
  values: string
  dur: number
  children: React.ReactNode
}) {
  return (
    <g>
      {children}
      <animateTransform
        attributeName="transform"
        type="translate"
        values={values}
        keyTimes="0;0.4;0.6;1"
        dur={`${dur}s`}
        repeatCount="indefinite"
      />
    </g>
  )
}

/** Diferente do RotatingLine, aqui o pivô já vem embutido em cada keyframe de
 *  `values` ("ângulo cx cy"), então não é passado à parte. */
function RotateGroup({
  values,
  dur,
  keyTimes = '0;0.33;0.66;1',
  children,
}: {
  values: string
  dur: number
  keyTimes?: string
  children: React.ReactNode
}) {
  return (
    <g>
      {children}
      <animateTransform
        attributeName="transform"
        type="rotate"
        values={values}
        keyTimes={keyTimes}
        dur={`${dur}s`}
        repeatCount="indefinite"
        additive="sum"
      />
    </g>
  )
}

function renderBody(pattern: MovementPattern) {
  switch (pattern) {
    // ─── pressões / puxadas de braço (segmento único, rotaciona no ombro) ───
    case 'horizontalPress':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={-70} to={40} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={70} to={-40} />
        </>
      )
    case 'flye':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={95} to={-15} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={-95} to={15} />
        </>
      )
    case 'verticalPull':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={165} to={45} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={-165} to={-45} />
        </>
      )
    case 'horizontalRow':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={40} to={-70} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={-40} to={70} />
        </>
      )
    case 'overheadPress':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={10} to={165} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={-10} to={-165} />
        </>
      )
    case 'lateralRaise':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={0} to={95} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={0} to={-95} />
        </>
      )
    case 'frontRaise':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={0} to={-85} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={0} to={85} />
        </>
      )

    // ─── dois segmentos (cotovelo é o ponto do exercício) ───
    case 'curl': {
      const elbowL: [number, number] = [SHOULDER_L[0], SHOULDER_L[1] + 14]
      const elbowR: [number, number] = [SHOULDER_R[0], SHOULDER_R[1] + 14]
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <line x1={SHOULDER_L[0]} y1={SHOULDER_L[1]} x2={elbowL[0]} y2={elbowL[1]} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
          <line x1={SHOULDER_R[0]} y1={SHOULDER_R[1]} x2={elbowR[0]} y2={elbowR[1]} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
          <RotatingLine pivot={elbowL} length={14} from={15} to={165} dur={1.3} />
          <RotatingLine pivot={elbowR} length={14} from={-15} to={-165} dur={1.3} />
        </>
      )
    }
    case 'tricepsExtension': {
      const elbowL: [number, number] = [SHOULDER_L[0], SHOULDER_L[1] - 14]
      const elbowR: [number, number] = [SHOULDER_R[0], SHOULDER_R[1] - 14]
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticLegs />
          <line x1={SHOULDER_L[0]} y1={SHOULDER_L[1]} x2={elbowL[0]} y2={elbowL[1]} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
          <line x1={SHOULDER_R[0]} y1={SHOULDER_R[1]} x2={elbowR[0]} y2={elbowR[1]} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
          <RotatingLine pivot={elbowL} length={14} from={15} to={175} dur={1.3} />
          <RotatingLine pivot={elbowR} length={14} from={-15} to={-175} dur={1.3} />
        </>
      )
    }

    // ─── pernas (segmento único no quadril) ───
    case 'hipAbduction':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticArms />
          <RotatingLine pivot={HIP_L} length={LEG_LEN} from={0} to={38} dur={1.3} />
          <RotatingLine pivot={HIP_R} length={LEG_LEN} from={0} to={-38} dur={1.3} />
        </>
      )

    // ─── máquina sentada, vista de lado (dois segmentos na perna) ───
    case 'legExtension':
    case 'legCurl': {
      const seatHip: [number, number] = [38, 55]
      const knee: [number, number] = [58, 55]
      const isExt = pattern === 'legExtension'
      return (
        <>
          <line x1={20} y1={70} x2={70} y2={70} stroke="currentColor" strokeWidth={2.5} strokeLinecap="round" opacity={0.35} />
          <circle cx={35} cy={20} r={7} stroke="currentColor" strokeWidth={3} fill="none" />
          <line x1={35} y1={27} x2={seatHip[0]} y2={seatHip[1]} stroke="currentColor" strokeWidth={3} strokeLinecap="round" />
          <line x1={20} y1={68} x2={seatHip[0]} y2={seatHip[1]} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
          <line x1={seatHip[0]} y1={seatHip[1]} x2={knee[0]} y2={knee[1]} stroke="currentColor" strokeWidth={3.2} strokeLinecap="round" />
          <RotatingLine pivot={knee} length={22} from={isExt ? 10 : -70} to={isExt ? -80 : 110} dur={1.4} />
        </>
      )
    }

    // ─── figura inteira translada (agachar / avançar) ───
    case 'squat':
      return (
        <TranslateGroup values="0 0;0 9;0 9;0 0" dur={1.6}>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticArms /><StaticLegs />
        </TranslateGroup>
      )
    case 'lunge':
      return (
        <TranslateGroup values="0 0;-3 7;-3 7;0 0" dur={1.6}>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticArms /><StaticLegs />
        </TranslateGroup>
      )
    case 'calfRaise':
      return (
        <TranslateGroup values="0 0;0 -3;0 -3;0 0" dur={1.1}>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticArms /><StaticLegs />
        </TranslateGroup>
      )

    // ─── quadril sobe/desce, tronco parado ───
    case 'hipThrust':
      return (
        <>
          <Head /><ShoulderBar /><Torso /><StaticArms />
          <TranslateGroup values="0 0;0 -6;0 -6;0 0" dur={1.5}>
            <HipBar /><StaticLegs />
          </TranslateGroup>
        </>
      )

    // ─── tronco flexiona no quadril, pernas fixas ───
    case 'hinge':
      return (
        <>
          <HipBar /><StaticLegs />
          <RotateGroup values="0 50 55;28 50 55;28 50 55;0 50 55" dur={1.7}>
            <Head /><ShoulderBar /><Torso /><StaticArms />
          </RotateGroup>
        </>
      )

    // ─── torção do tronco em pé ───
    case 'twist':
      return (
        <>
          <HipBar /><StaticLegs />
          <RotateGroup values="0 50 55;-18 50 55;18 50 55;0 50 55" dur={1.8} keyTimes="0;0.33;0.66;1">
            <Head /><ShoulderBar /><Torso /><StaticArms />
          </RotateGroup>
        </>
      )

    // ─── ombros sobem (encolhimento) ───
    case 'shrug':
      return (
        <>
          <Torso /><HipBar /><StaticLegs />
          <TranslateGroup values="0 0;0 -4;0 -4;0 0" dur={1}>
            <Head /><ShoulderBar /><StaticArms />
          </TranslateGroup>
        </>
      )

    // ─── deitado (prancha estática / abdominal) ───
    case 'plank':
      return (
        <g transform="rotate(90 50 50)">
          <TranslateGroup values="0 0;0 -1.5;0 1.5;0 0" dur={0.5}>
            <Head /><ShoulderBar /><Torso /><HipBar /><StaticArms /><StaticLegs />
          </TranslateGroup>
        </g>
      )
    case 'crunch':
      return (
        <g transform="rotate(90 50 50)">
          <HipBar /><StaticLegs />
          <RotateGroup values="0 50 55;-26 50 55;-26 50 55;0 50 55" dur={1.3}>
            <Head /><ShoulderBar /><Torso /><StaticArms />
          </RotateGroup>
        </g>
      )

    // ─── polichinelo: pula + braços e pernas abrem juntos ───
    case 'cardio':
      return (
        <TranslateGroup values="0 0;0 -6;0 -6;0 0" dur={0.9}>
          <Head /><ShoulderBar /><Torso /><HipBar />
          <RotatingLine pivot={SHOULDER_L} length={ARM_LEN} from={0} to={100} dur={0.9} />
          <RotatingLine pivot={SHOULDER_R} length={ARM_LEN} from={0} to={-100} dur={0.9} />
          <RotatingLine pivot={HIP_L} length={LEG_LEN} from={0} to={35} dur={0.9} />
          <RotatingLine pivot={HIP_R} length={LEG_LEN} from={0} to={-35} dur={0.9} />
        </TranslateGroup>
      )

    case 'generic':
    default:
      return (
        <TranslateGroup values="0 0;0 -3;0 -3;0 0" dur={1.6}>
          <Head /><ShoulderBar /><Torso /><HipBar /><StaticArms /><StaticLegs />
        </TranslateGroup>
      )
  }
}

export default function ExerciseAnimation({
  name,
  muscleGroup,
  imageUrl,
  imageCredit,
  videoUrl,
  size = 'md',
  className,
}: {
  name: string
  muscleGroup?: string
  imageUrl?: string | null
  imageCredit?: string | null
  videoUrl?: string | null
  size?: keyof typeof SIZES
  className?: string
}) {
  const px = SIZES[size]
  const svgRef = useRef<SVGSVGElement>(null)
  const videoRef = useRef<HTMLVideoElement>(null)

  // Ao sair da tela cheia (botão nativo, gesto de voltar, ESC), devolve o
  // vídeo ao estado de miniatura — senão ele fica com controles e som
  // ligados, parecendo travado no meio da lista.
  useEffect(() => {
    const video = videoRef.current
    if (!video) return

    const aoSairDaTelaCheia = () => {
      if (document.fullscreenElement === video) return
      video.muted = true
      video.controls = false
    }
    const aoSairDaTelaCheiaIOS = () => {
      video.muted = true
      video.controls = false
    }

    document.addEventListener('fullscreenchange', aoSairDaTelaCheia)
    video.addEventListener('webkitendfullscreen', aoSairDaTelaCheiaIOS)
    return () => {
      document.removeEventListener('fullscreenchange', aoSairDaTelaCheia)
      video.removeEventListener('webkitendfullscreen', aoSairDaTelaCheiaIOS)
    }
  }, [])

  // Hook antes dos returns antecipados de vídeo/imagem: a ref só é preenchida
  // quando o fallback SVG é o que de fato renderiza, e aí o efeito é no-op.
  useEffect(() => {
    const svg = svgRef.current
    const io = obterObservador()
    if (!svg || !io) return

    // De propósito NÃO pausa aqui. O observer já dispara na montagem e pausa o
    // que estiver fora da tela, então o ganho é o mesmo — mas se ele nunca
    // entregar callback (aba em segundo plano, navegador sem suporte), o estado
    // que sobra é "animando", como sempre foi. Pausar de largada faria o modo de
    // falha ser a miniatura congelada, que é bem pior que gastar CPU à toa.
    io.observe(svg)

    return () => io.unobserve(svg)
  }, [])

  if (videoUrl) {
    return (
      <div
        className={className}
        style={{ width: px, height: px, position: 'relative', overflow: 'hidden', cursor: 'pointer' }}
        onClick={() => videoRef.current && abrirVideoEmTelaCheia(videoRef.current)}
        role="button"
        aria-label={`Ver demonstração em tela cheia: ${name}`}
      >
        <video
          ref={videoRef}
          src={resolveMediaUrl(videoUrl)}
          aria-hidden="true"
          width={px}
          height={px}
          style={{ width: '100%', height: '100%', objectFit: 'cover' }}
          autoPlay
          muted
          loop
          playsInline
        />
        <span
          className="pointer-events-none absolute bottom-1 right-1 flex items-center justify-center rounded-full bg-black/50 p-1 text-white"
          aria-hidden="true"
        >
          <Maximize2 size={px <= 40 ? 10 : 14} />
        </span>
      </div>
    )
  }

  if (imageUrl) {
    return (
      <img
        src={imageUrl}
        alt={`Demonstração: ${name}`}
        title={imageCredit ?? undefined}
        width={px}
        height={px}
        className={className}
        style={{ width: px, height: px, objectFit: 'contain' }}
      />
    )
  }

  const pattern = getMovementPattern(name, muscleGroup)
  return (
    <svg
      ref={svgRef}
      viewBox="0 0 100 100"
      width={px}
      height={px}
      className={className}
      role="img"
      aria-label={`Demonstração: ${name}`}
    >
      {renderBody(pattern)}
    </svg>
  )
}
