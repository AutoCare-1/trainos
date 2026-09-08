import { expect, test } from '@playwright/test'

// Dados do App\Console\Commands\SeedE2e — rodar `php artisan e2e:seed` antes.
const TOKEN_ALUNO = 'e2e-token-fixo-aluno'

test('aluno acessa via portal, abre o treino do dia e registra uma série', async ({ page }) => {
  await page.goto(`/aluno/${TOKEN_ALUNO}`)

  await page.getByRole('button', { name: 'Iniciar treino' }).click()

  // "Reps" e "Carga (kg)" são <label> sem htmlFor/id associado ao <input> (bug
  // de acessibilidade à parte, fora do escopo deste teste) — getByLabel não
  // funciona aqui; os atributos inputMode distinguem os dois campos.
  await page.locator('input[inputmode="numeric"]').fill('10')
  await page.locator('input[inputmode="decimal"]').fill('20')
  await page.getByRole('button', { name: /^✓ 1\/3$/ }).click()

  await expect(page.getByText('1/3 séries')).toBeVisible()
})
