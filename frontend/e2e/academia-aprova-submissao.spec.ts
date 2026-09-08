import { expect, test } from '@playwright/test'
import { SeedE2eCredenciais } from './support/credenciais'

test('personal abre uma análise de academia pendente e aprova', async ({ page }) => {
  await page.goto('/login')
  await page.locator('input[type="email"]').fill(SeedE2eCredenciais.email)
  await page.locator('input[type="password"]').fill(SeedE2eCredenciais.senha)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await page.waitForURL('/negocio')

  await page.goto('/academia')
  await page.getByRole('link', { name: /Aluno E2E/ }).click()
  await page.waitForURL(/\/academia\/.+/)
  const urlSubmissao = page.url()

  await page.getByRole('button', { name: '✓ Aprovar treino' }).click()
  await page.waitForURL('/academia')

  // Reabre a mesma submissão e confirma que o status virou aprovado.
  await page.goto(urlSubmissao)
  await expect(page.getByText('Aprovado')).toBeVisible()
})
