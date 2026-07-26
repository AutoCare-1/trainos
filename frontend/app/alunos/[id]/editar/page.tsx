import EditarAlunoClient from './EditarAlunoClient'

export default async function EditarAlunoPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return <EditarAlunoClient studentId={id} />
}
