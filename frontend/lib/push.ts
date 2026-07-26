import { api } from './api'

/**
 * Mesma checagem já usada em InstallAppInstructions — critério oficial pra saber
 * se o app está rodando instalado (tela inicial) e não numa aba comum do navegador.
 * iOS só entrega Web Push nesse modo (Safari normal nunca recebe push, mesmo com
 * permissão concedida).
 */
export function estaInstalado(): boolean {
  if (typeof window === 'undefined') return false
  const standaloneIOS = (window.navigator as Navigator & { standalone?: boolean }).standalone === true
  return window.matchMedia('(display-mode: standalone)').matches || standaloneIOS
}

export function pushSuportado(): boolean {
  return typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

// applicationServerKey exige um Uint8Array com ArrayBuffer de verdade (não
// ArrayBufferLike) — não a string base64url que a API entrega.
function urlBase64ToUint8Array(base64String: string): Uint8Array<ArrayBuffer> {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = window.atob(base64)
  const array = new Uint8Array(new ArrayBuffer(raw.length))
  for (let i = 0; i < raw.length; i++) {
    array[i] = raw.charCodeAt(i)
  }
  return array
}

export type ResultadoAtivarPush = { ok: true } | { ok: false; motivo: string }

/**
 * Pede permissão de notificação e inscreve o navegador pra Web Push. SÓ deve ser
 * chamada a partir de um gesto direto do usuário (onClick de um botão) — iOS exige
 * isso pra Notification.requestPermission(), e nunca deve rodar sozinha ao carregar
 * a página (pediria permissão até pra quem só está visitando pelo navegador comum).
 */
export async function ativarPush(caminhoSubscribe: string): Promise<ResultadoAtivarPush> {
  if (!pushSuportado()) {
    return { ok: false, motivo: 'Seu navegador não tem suporte a notificações push.' }
  }
  if (!estaInstalado()) {
    return { ok: false, motivo: 'Instale o app na tela inicial antes de ativar as notificações.' }
  }

  const permissao = await Notification.requestPermission()
  if (permissao !== 'granted') {
    return { ok: false, motivo: 'Permissão de notificação não concedida.' }
  }

  const registration = await navigator.serviceWorker.register('/sw.js')
  await navigator.serviceWorker.ready

  const { publicKey } = await api.get<{ publicKey: string }>('/push/vapid-public-key')

  let subscription = await registration.pushManager.getSubscription()
  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicKey),
    })
  }

  await api.post(caminhoSubscribe, subscription.toJSON())

  return { ok: true }
}

export async function desativarPush(caminhoUnsubscribe: string): Promise<void> {
  if (!pushSuportado()) return

  const registration = await navigator.serviceWorker.getRegistration()
  const subscription = await registration?.pushManager.getSubscription()
  if (!subscription) return

  const endpoint = subscription.endpoint
  await subscription.unsubscribe()
  await api.delete(caminhoUnsubscribe, { endpoint })
}

/** Pra decidir se mostra o botão "Ativar notificações" (não faz sentido se já negou ou já ativou). */
export function statusPermissao(): NotificationPermission | 'indisponivel' {
  if (!pushSuportado()) return 'indisponivel'
  return Notification.permission
}
