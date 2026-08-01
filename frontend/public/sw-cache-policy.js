// Política de cache do Service Worker — que requisição pode ser guardada pra
// funcionar sem rede, e com qual estratégia.
//
// Fica num arquivo próprio (script clássico, carregado com importScripts no
// sw.js) por um motivo prático: assim dá pra testar a decisão fora do
// navegador, sem subir servidor nem simular Service Worker. Ver
// tests/swCachePolicy.test.mjs — é o teste que garante que vídeo nunca entra
// no cache offline.
//
// Escopo deliberadamente estreito: só o necessário pro aluno abrir o treino do
// dia e registrar as séries sem internet na academia. Análise de forma,
// academia, chat, evolução e notificações continuam exigindo conexão.
;(function (raiz) {
  // Vídeo de demonstração é pesado demais pra cache offline (o app tem vídeos
  // enviados pelo personal, sem limite de duração garantido) — offline a tela
  // mostra o estado normal de "sem conexão pra ver o vídeo" e o resto do treino
  // continua funcionando.
  var EXTENSOES_MIDIA = /\.(mp4|webm|mov|m4v|avi|mkv|ogv|mp3|wav|m4a|ogg)$/i

  // Assets do app que dá pra guardar por caminho (todos com hash no nome ou
  // estáveis), pra tela abrir sem rede.
  var ESTATICOS_EXATOS = ['/manifest.json', '/icon-192.png', '/icon-512.png', '/apple-touch-icon.png']
  var ESTATICOS_PREFIXO = ['/_next/static/', '/exercise-photos/', '/clubemais-']

  // Payload do treino do dia: GET /portal/<token> e nada abaixo dele — as
  // sub-rotas (/mensagens, /academia, /postural, /checkins...) dependem de
  // dado vivo ou de mídia e ficam de fora de propósito.
  var ROTA_PORTAL = /^\/portal\/[^/]+$/

  function ehMidia(caminho, destino) {
    if (destino === 'video' || destino === 'audio') return true
    // Tudo que o backend serve em /uploads/ é mídia enviada por usuário (vídeo
    // de exercício, foto de check-in, mídia de academia) — nada disso é
    // necessário pra registrar série offline.
    if (caminho.indexOf('/uploads/') === 0) return true
    return EXTENSOES_MIDIA.test(caminho)
  }

  function comecaCom(caminho, prefixos) {
    for (var i = 0; i < prefixos.length; i++) {
      if (caminho.indexOf(prefixos[i]) === 0) return true
    }
    return false
  }

  /**
   * @returns {'estatico'|'dados'|'navegacao'|null} null = passa direto pela
   * rede, sem tocar no cache (é o padrão pra tudo que não está previsto aqui).
   */
  function politicaDeCache(requisicao) {
    var metodo = (requisicao.method || 'GET').toUpperCase()
    // Só GET entra em cache. POST de série vai pra fila offline em IndexedDB
    // (lib/filaOffline.ts), não pro Cache Storage — quem decide reenviar é a
    // página, que sabe a ordem e as dependências entre os registros.
    if (metodo !== 'GET') return null

    var caminho
    try {
      caminho = new URL(requisicao.url).pathname
    } catch {
      return null
    }

    if (ehMidia(caminho, requisicao.destination)) return null

    if (requisicao.mode === 'navigate') {
      // Só o portal do aluno funciona offline; as telas do personal exigem rede.
      return caminho.indexOf('/aluno/') === 0 ? 'navegacao' : null
    }

    if (ESTATICOS_EXATOS.indexOf(caminho) !== -1 || comecaCom(caminho, ESTATICOS_PREFIXO)) {
      return 'estatico'
    }

    if (ROTA_PORTAL.test(caminho)) return 'dados'

    return null
  }

  raiz.politicaDeCache = politicaDeCache
})(typeof self !== 'undefined' ? self : globalThis)
