const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3002'

export class ApiError extends Error {}

function getToken(): string | null {
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

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body: body ? JSON.stringify(body) : undefined }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}
