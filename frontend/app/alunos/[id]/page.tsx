import AlunoDetalheClient from './AlunoDetalheClient'

export default async function AlunoDetalhePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return <AlunoDetalheClient studentId={id} />
}
