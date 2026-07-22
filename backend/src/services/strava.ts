import { pool } from '../db/pool'
import { DeviceConnection } from '../types'

const STRAVA_CLIENT_ID = process.env.STRAVA_CLIENT_ID
const STRAVA_CLIENT_SECRET = process.env.STRAVA_CLIENT_SECRET

function credenciais(): { clientId: string; clientSecret: string } {
  if (!STRAVA_CLIENT_ID || !STRAVA_CLIENT_SECRET) {
    throw new Error('STRAVA_CLIENT_ID / STRAVA_CLIENT_SECRET não configurados no .env')
  }
  return { clientId: STRAVA_CLIENT_ID, clientSecret: STRAVA_CLIENT_SECRET }
}

export function montarUrlAutorizacao(redirectUri: string, state: string): string {
  const { clientId } = credenciais()
  const params = new URLSearchParams({
    client_id: clientId,
    redirect_uri: redirectUri,
    response_type: 'code',
    approval_prompt: 'auto',
    scope: 'activity:read_all',
    state,
  })
  return `https://www.strava.com/oauth/authorize?${params.toString()}`
}

interface TokenResponse {
  access_token: string
  refresh_token: string
  expires_at: number // unix timestamp (segundos)
  athlete?: { id: number }
}

export async function trocarCodigoPorToken(code: string): Promise<TokenResponse> {
  const { clientId, clientSecret } = credenciais()
  const res = await fetch('https://www.strava.com/oauth/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      client_id: clientId,
      client_secret: clientSecret,
      code,
      grant_type: 'authorization_code',
    }),
  })
  if (!res.ok) {
    throw new Error(`Strava rejeitou a troca de código: ${res.status} ${await res.text()}`)
  }
  return res.json() as Promise<TokenResponse>
}

async function renovarToken(conexao: DeviceConnection): Promise<DeviceConnection> {
  const { clientId, clientSecret } = credenciais()
  const res = await fetch('https://www.strava.com/oauth/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      client_id: clientId,
      client_secret: clientSecret,
      refresh_token: conexao.refresh_token,
      grant_type: 'refresh_token',
    }),
  })
  if (!res.ok) {
    throw new Error(`Falha ao renovar token do Strava: ${res.status} ${await res.text()}`)
  }
  const dados = (await res.json()) as TokenResponse

  const { rows } = await pool.query<DeviceConnection>(
    `update device_connections
     set access_token = $1, refresh_token = $2, expires_at = to_timestamp($3)
     where id = $4
     returning *`,
    [dados.access_token, dados.refresh_token, dados.expires_at, conexao.id]
  )
  return rows[0]
}

// Garante um access_token válido, renovando automaticamente se estiver perto de expirar.
async function tokenValido(conexao: DeviceConnection): Promise<DeviceConnection> {
  const expiraEm = new Date(conexao.expires_at).getTime()
  const margemMs = 5 * 60 * 1000 // renova com 5min de folga
  if (expiraEm - Date.now() > margemMs) return conexao
  return renovarToken(conexao)
}

interface AtividadeStrava {
  id: number
  name: string
  type: string
  start_date: string
  moving_time: number
  distance: number
  calories?: number
  average_heartrate?: number
}

export async function sincronizarAtividades(studentId: string): Promise<number> {
  const { rows } = await pool.query<DeviceConnection>(
    `select * from device_connections where student_id = $1 and provider = 'strava'`,
    [studentId]
  )
  const conexao = rows[0]
  if (!conexao) throw new Error('Aluno não conectou o Strava ainda')

  const atual = await tokenValido(conexao)

  const res = await fetch('https://www.strava.com/api/v3/athlete/activities?per_page=30', {
    headers: { Authorization: `Bearer ${atual.access_token}` },
  })
  if (!res.ok) {
    throw new Error(`Erro ao buscar atividades no Strava: ${res.status} ${await res.text()}`)
  }
  const atividades = (await res.json()) as AtividadeStrava[]

  let inseridas = 0
  for (const a of atividades) {
    const { rowCount } = await pool.query(
      `insert into external_activities
         (student_id, provider, external_id, activity_type, name, started_at, duration_seconds, distance_meters, calories, avg_heart_rate, raw_payload)
       values ($1, 'strava', $2, $3, $4, $5, $6, $7, $8, $9, $10)
       on conflict (provider, external_id) do nothing`,
      [
        studentId,
        String(a.id),
        a.type,
        a.name,
        a.start_date,
        a.moving_time,
        a.distance,
        a.calories ?? null,
        a.average_heartrate ?? null,
        JSON.stringify(a),
      ]
    )
    if (rowCount) inseridas++
  }
  return inseridas
}
