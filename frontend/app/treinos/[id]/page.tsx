import TreinoDetalheClient from './TreinoDetalheClient'

export default async function TreinoDetalhePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return <TreinoDetalheClient workoutId={id} />
}
