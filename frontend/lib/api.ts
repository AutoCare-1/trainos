export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3002'

/** Uploads salvos pelo backend voltam como caminho relativo (`/uploads/...`); precisam da origem da API pra virar URL válida. */
export function resolveMediaUrl(url: string): string {
  return url.startsWith('/uploads/') ? `${API_URL}${url}` : url
}

export class ApiError extends Error {}

export function getToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem('trainos_token')
}

export function setToken(token: string) {
  localStorage.setItem('trainos_token', token)
}

export function clearToken() {
  localStorage.removeItem('trainos_token')
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken()
  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })

  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    throw new ApiError(data.error ?? `Erro ${res.status}`)
  }
  return data as T
}

async function requestFormData<T>(path: string, formData: FormData, method: string): Promise<T> {
  const token = getToken()
  const res = await fetch(`${API_URL}${path}`, {
    method,
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    body: formData,
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    throw new ApiError(data.error ?? `Erro ${res.status}`)
  }
  return data as T
}

/**
 * Busca uma imagem que exige autenticação (ex: fotos de evolução física, que não
 * ficam sob /uploads público) e devolve uma object URL pra usar num <img src>.
 * Quem chama é responsável por revogar a URL (URL.revokeObjectURL) ao desmontar.
 */
export async function fetchImagemAutenticada(path: string): Promise<string> {
  const token = getToken()
  const res = await fetch(`${API_URL}${path}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  })
  if (!res.ok) throw new ApiError('Não foi possível carregar a imagem')
  const blob = await res.blob()
  return URL.createObjectURL(blob)
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body: body ? JSON.stringify(body) : undefined }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
  postFile: <T>(path: string, formData: FormData) => requestFormData<T>(path, formData, 'POST'),
}
