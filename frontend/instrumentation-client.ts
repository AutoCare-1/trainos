import * as Sentry from '@sentry/nextjs'

// DSN vazio (padrão em dev/CI) -> SDK vira no-op sozinho, sem precisar de código condicional.
Sentry.init({
  dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,
  tracesSampleRate: 1.0,
})

export const onRouterTransitionStart = Sentry.captureRouterTransitionStart
