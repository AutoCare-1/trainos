/**
 * Fila local das ações do treino feitas sem internet (registrar série e
 * concluir a sessão), guardada em IndexedDB — não em memória nem localStorage:
 * a academia é justamente onde o app pode ser fechado/recarregado no meio do
 * treino, e o registro não pode se perder junto.
 *
 * Cada item carrega um `clientEntryId` (UUID gerado aqui) que o backend usa
 * como chave de idempotência: reenviar o mesmo item não duplica a série.
 *
 * Escopo de propósito estreito: só execução do treino. Análise de forma,
 * academia, chat e evolução continuam exigindo conexão.
 */

const BANCO = 'clube-mais-offline'
const LOJA = 'fila'
const VERSAO = 1

export interface ItemSerie {
  tipo: 'serie'
  workoutExerciseId: string
  setNumber: number
  repsDone: number | null
  loadKgDone: number | null
}

export interface ItemConclusao {
  tipo: 'concluir'
  effortRpe: number
  satisfaction: number
  discomfort: string
  comment: string
}

type Payload = ItemSerie | ItemConclusao

interface Envelope {
  token: string
  workoutId: string
  clientEntryId: string
  criadoEm: number
  payload: Payload
}

export type ItemDaFila = Envelope & { seq: number }

function suportaIndexedDb(): boolean {
  return typeof indexedDB !== 'undefined'
}

function abrir(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(BANCO, VERSAO)
    req.onupgradeneeded = () => {
      if (!req.result.objectStoreNames.contains(LOJA)) {
        // autoIncrement dá a ordem de chegada de graça: percorrer a store na
        // ordem da chave é percorrer na ordem em que o aluno registrou — e a
        // ordem importa (concluir tem que sair depois das séries).
        req.result.createObjectStore(LOJA, { keyPath: 'seq', autoIncrement: true })
      }
    }
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

function comLoja<T>(modo: IDBTransactionMode, acao: (loja: IDBObjectStore) => IDBRequest<T>): Promise<T> {
  return abrir().then(
    (db) =>
      new Promise<T>((resolve, reject) => {
        const tx = db.transaction(LOJA, modo)
        const req = acao(tx.objectStore(LOJA))
        req.onsuccess = () => resolve(req.result)
        req.onerror = () => reject(req.error)
        tx.oncomplete = () => db.close()
      })
  )
}

export function novoClientEntryId(): string {
  // randomUUID exige contexto seguro (https ou localhost) — é o caso do app em
  // produção e em dev, mas o fallback evita quebrar o registro da série num
  // acesso por IP na rede local, que é como o personal costuma testar no
  // celular durante uma demonstração.
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

export async function enfileirar(
  token: string,
  workoutId: string,
  payload: Payload,
  clientEntryId = novoClientEntryId()
): Promise<string> {
  if (!suportaIndexedDb()) return clientEntryId

  const envelope: Envelope = { token, workoutId, clientEntryId, criadoEm: Date.now(), payload }
  await comLoja('readwrite', (loja) => loja.add(envelope))
  return clientEntryId
}

export async function listarFila(token: string): Promise<ItemDaFila[]> {
  if (!suportaIndexedDb()) return []

  const todos = await comLoja<ItemDaFila[]>('readonly', (loja) => loja.getAll())
  // Filtra por token porque um mesmo aparelho pode abrir o portal de dois
  // alunos (casal, pai e filho) — a fila de um não pode ser despachada com o
  // link do outro.
  return todos.filter((item) => item.token === token).sort((a, b) => a.seq - b.seq)
}

export async function removerDaFila(seq: number): Promise<void> {
  if (!suportaIndexedDb()) return
  await comLoja('readwrite', (loja) => loja.delete(seq))
}

export async function contarPendentes(token: string): Promise<number> {
  return (await listarFila(token)).length
}
