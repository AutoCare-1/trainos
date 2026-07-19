import PortalAlunoClient from './PortalAlunoClient'

export default async function PortalAlunoPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params
  return <PortalAlunoClient token={token} />
}
