import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

// public/sw-cache-policy.js é um script clássico (o Service Worker carrega com
// importScripts, e módulo ESM em Service Worker ainda não é seguro no Safari
// do iPhone — que é justamente onde o aluno mais usa o app). Pra testar sem
// navegador, avalia o arquivo entregando um `self` de mentira e lê a função
// que ele publica ali.
const caminho = fileURLToPath(new URL('../public/sw-cache-policy.js', import.meta.url))
const escopo = {}
new Function('self', readFileSync(caminho, 'utf8'))(escopo)
const { politicaDeCache } = escopo

const API = 'http://localhost:3003'
const APP = 'http://localhost:3101'

const req = (url, extras = {}) => ({ url, method: 'GET', destination: '', mode: 'no-cors', ...extras })

test('vídeo de demonstração nunca entra no cache', () => {
  // Os três jeitos de o vídeo aparecer: upload do personal, destination do
  // navegador num <video>, e extensão de arquivo em qualquer outro caminho.
  assert.equal(politicaDeCache(req(`${API}/uploads/exercise-videos/supino.mp4`)), null)
  assert.equal(politicaDeCache(req(`${APP}/algum/caminho.webm`)), null)
  assert.equal(politicaDeCache(req(`${APP}/_next/static/media/demo.mp4`)), null)
  assert.equal(politicaDeCache(req(`${API}/qualquer/coisa`, { destination: 'video' })), null)
  assert.equal(politicaDeCache(req(`${API}/qualquer/coisa`, { destination: 'audio' })), null)
})

test('nenhuma mídia enviada por usuário entra no cache', () => {
  // /uploads/ serve foto de check-in, mídia de academia e vídeo de exercício —
  // nada disso é necessário pra registrar série sem rede.
  assert.equal(politicaDeCache(req(`${API}/uploads/checkins/foto.jpg`)), null)
  assert.equal(politicaDeCache(req(`${API}/uploads/gym-media/academia.png`)), null)
})

test('o payload do treino do dia é cacheado como dado', () => {
  assert.equal(politicaDeCache(req(`${API}/portal/abc123`)), 'dados')
  assert.equal(politicaDeCache(req(`${API}/portal/abc123?workout_id=xyz`)), 'dados')
})

test('sub-rotas do portal que dependem de dado vivo ou mídia ficam de fora', () => {
  for (const sub of ['mensagens', 'academia', 'postural', 'checkins', 'body-photos']) {
    assert.equal(politicaDeCache(req(`${API}/portal/abc123/${sub}`)), null, sub)
  }
})

test('assets estáticos do app são cacheados', () => {
  assert.equal(politicaDeCache(req(`${APP}/_next/static/chunks/main-abc123.js`)), 'estatico')
  assert.equal(politicaDeCache(req(`${APP}/exercise-photos/supino-reto.png`)), 'estatico')
  assert.equal(politicaDeCache(req(`${APP}/manifest.json`)), 'estatico')
  assert.equal(politicaDeCache(req(`${APP}/icon-192.png`)), 'estatico')
})

test('só a navegação do portal do aluno funciona offline', () => {
  const navegacao = { mode: 'navigate', destination: 'document' }
  assert.equal(politicaDeCache(req(`${APP}/aluno/abc123`, navegacao)), 'navegacao')
  // Telas do personal exigem rede — não faz parte do escopo offline.
  assert.equal(politicaDeCache(req(`${APP}/dashboard`, navegacao)), null)
  assert.equal(politicaDeCache(req(`${APP}/treinos/novo`, navegacao)), null)
})

test('escrita nunca passa pelo cache (vai pra fila offline em IndexedDB)', () => {
  assert.equal(politicaDeCache(req(`${API}/portal/abc123/sessoes/s1/registros`, { method: 'POST' })), null)
  assert.equal(politicaDeCache(req(`${API}/portal/abc123`, { method: 'POST' })), null)
  assert.equal(politicaDeCache(req(`${API}/portal/abc123`, { method: 'PATCH' })), null)
})

test('URL inválida não quebra a decisão', () => {
  assert.equal(politicaDeCache(req('nao-e-url')), null)
})
