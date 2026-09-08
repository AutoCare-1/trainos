import { defineConfig, devices } from '@playwright/test'

// Testes de fumaça — 3 caminhos felizes de maior valor, não cobertura ampla.
// baseURL/porta batem com o job `e2e` do CI (.github/workflows/ci.yml); em
// dev local, sobe o dev server na mesma porta antes de rodar (webServer abaixo).
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  reporter: 'list',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:3113',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: process.env.PLAYWRIGHT_BASE_URL
    ? undefined
    : {
        command: 'npm run build && npm run start -- -p 3113',
        url: 'http://localhost:3113',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      },
})
