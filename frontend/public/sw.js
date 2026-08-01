// Service Worker do Clube Mais Personal — Web Push e cache offline do treino
// do dia. A decisão de "o que pode ser cacheado" mora em sw-cache-policy.js
// (arquivo separado pra ser testável fora do navegador).

importScripts('/sw-cache-policy.js')

const CACHE = 'clube-mais-v1'

self.addEventListener('install', () => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((nomes) => Promise.all(nomes.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  )
})

// Assets com hash no nome (/_next/static) e imagens de exercício nunca mudam de
// conteúdo pra uma mesma URL — servir do cache primeiro deixa a tela abrir sem
// rede e evita rodada de revalidação na academia com sinal ruim.
async function cachePrimeiro(requisicao) {
  const guardado = await caches.match(requisicao)
  if (guardado) return guardado

  const resposta = await fetch(requisicao)
  if (resposta.ok) {
    const cache = await caches.open(CACHE)
    cache.put(requisicao, resposta.clone())
  }
  return resposta
}

/**
 * Rede primeiro, cache como rede de segurança: online o aluno sempre vê o dado
 * atual (treino trocado pelo personal, série registrada em outro aparelho), e
 * sem rede cai na última versão que ele já tinha aberto.
 */
async function redePrimeiro(requisicao, ignorarQueryNoFallback) {
  try {
    const resposta = await fetch(requisicao)
    if (resposta.ok) {
      const cache = await caches.open(CACHE)
      cache.put(requisicao, resposta.clone())
    }
    return resposta
  } catch (erro) {
    // `ignoreSearch` porque o portal é aberto com ?workout_id=... quando o aluno
    // escolhe entre treinos vigentes — sem isso, trocar de treino e ficar sem
    // rede não acharia nada no cache, mesmo já tendo o payload guardado.
    const guardado = await caches.match(requisicao, { ignoreSearch: !!ignorarQueryNoFallback })
    if (guardado) return guardado
    throw erro
  }
}

self.addEventListener('fetch', (event) => {
  const requisicao = event.request
  const politica = self.politicaDeCache({
    url: requisicao.url,
    method: requisicao.method,
    destination: requisicao.destination,
    mode: requisicao.mode,
  })

  // Sem política = o Service Worker não se mete: a requisição segue o caminho
  // normal do navegador, como era antes de existir cache aqui.
  if (!politica) return

  if (politica === 'estatico') {
    event.respondWith(cachePrimeiro(requisicao))
    return
  }

  event.respondWith(redePrimeiro(requisicao, politica === 'dados' || politica === 'navegacao'))
})

self.addEventListener('push', (event) => {
  if (!event.data) return

  let payload
  try {
    payload = event.data.json()
  } catch {
    payload = { title: 'Clube Mais', body: event.data.text() }
  }

  const titulo = payload.title || 'Clube Mais'
  const opcoes = {
    body: payload.body || '',
    icon: payload.icon || '/icon-192.png',
    badge: payload.badge || '/icon-192.png',
    data: payload.data || {},
  }

  event.waitUntil(self.registration.showNotification(titulo, opcoes))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  const url = event.notification.data?.url || '/'

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        const clientUrl = new URL(client.url)
        if (clientUrl.pathname === url && 'focus' in client) {
          return client.focus()
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(url)
      }
    })
  )
})
