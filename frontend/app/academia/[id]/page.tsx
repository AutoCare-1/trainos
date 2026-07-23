import AcademiaDetalheClient from './AcademiaDetalheClient'

export default async function AcademiaDetalhePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return <AcademiaDetalheClient submissionId={id} />
}
