import DesafioDetalheClient from './DesafioDetalheClient'

export default async function DesafioDetalhePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return <DesafioDetalheClient challengeId={id} />
}
