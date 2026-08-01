import { useSyncExternalStore } from 'react'

function inscrever(aoMudar: () => void) {
  window.addEventListener('online', aoMudar)
  window.addEventListener('offline', aoMudar)
  return () => {
    window.removeEventListener('online', aoMudar)
    window.removeEventListener('offline', aoMudar)
  }
}

/**
 * `navigator.onLine` é otimista por natureza (diz "online" em Wi-Fi de
 * academia que não chega a lugar nenhum), então serve só pra reagir rápido à
 * mudança — quem decide de verdade se a requisição foi é o próprio fetch
 * falhando. No servidor assume online pra não piscar o aviso de offline na
 * hidratação de quem está com internet normal.
 */
export function useEstaOnline(): boolean {
  return useSyncExternalStore(
    inscrever,
    () => navigator.onLine,
    () => true
  )
}

/**
 * Registra o Service Worker do app. Antes isso só acontecia dentro de
 * ativarPush() — quem nunca ativou notificação ficava sem Service Worker
 * nenhum e, portanto, sem nada em cache pra abrir o treino sem rede.
 */
export async function registrarServiceWorker(): Promise<void> {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return

  try {
    await navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' })
  } catch {
    // Falhar aqui (sem rede no primeiro acesso, contexto não seguro) não pode
    // derrubar a tela — o app segue funcionando, só sem o modo offline.
  }
}
