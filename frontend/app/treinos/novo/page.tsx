import { Suspense } from 'react'
import NovoTreinoClient from './NovoTreinoClient'

export default function NovoTreinoPage() {
  return (
    <Suspense fallback={null}>
      <NovoTreinoClient />
    </Suspense>
  )
}
