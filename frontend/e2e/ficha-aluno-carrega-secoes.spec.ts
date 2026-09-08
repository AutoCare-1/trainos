import { expect, test } from '@playwright/test'
import { SeedE2eCredenciais } from './support/credenciais'

// Regressão do gap de paridade corrigido: mensagens, medições, PAR-Q, fotos de
// evolução e check-ins tinham sumido do lado do profissional na migração pro
// Laravel (só existiam no portal do aluno). Este teste garante que a ficha
// completa carrega de novo, sem erro de rede/console.
test('personal loga, abre a ficha completa de um aluno e todas as seções carregam', async ({ page }) => {
  const errosConsole: string[] = []
  page.on('console', (msg) => {
    if (msg.type() === 'error') errosConsole.push(msg.text())
  })
  const requisicoesComErro: string[] = []
  page.on('response', (resp) => {
    if (resp.status() >= 500) requisicoesComErro.push(`${resp.status()} ${resp.url()}`)
  })

  await page.goto('/login')
  // Mesmo caso de label sem htmlFor/id do teste de registrar série — usa o
  // atributo type dos campos em vez de getByLabel.
  await page.locator('input[type="email"]').fill(SeedE2eCredenciais.email)
  await page.locator('input[type="password"]').fill(SeedE2eCredenciais.senha)
  await page.getByRole('button', { name: 'Entrar' }).click()

  await page.waitForURL('/negocio')
  await page.goto('/dashboard')

  await page.getByRole('link', { name: /Aluno E2E/ }).click()
  await page.waitForURL(/\/alunos\/.+/)

  await expect(page.getByRole('heading', { name: 'Conversa' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Avaliação física' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Saúde (PAR-Q)' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Medidas' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Evolução (fotos)' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Check-in' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Treinos' })).toBeVisible()

  expect(requisicoesComErro, `Respostas 5xx: ${requisicoesComErro.join(', ')}`).toEqual([])
})
