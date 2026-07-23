import multer from 'multer'
import path from 'path'
import fs from 'fs'
import { nanoid } from 'nanoid'

/** Cria um uploader multer que salva em backend/uploads/<subdir>, aceitando só arquivos com o(s) mimetype(s) prefixado(s). */
export function criarUploader(subdir: string, mimePrefix: string | string[], limiteBytes: number) {
  const uploadDir = path.join(__dirname, '..', '..', 'uploads', subdir)
  fs.mkdirSync(uploadDir, { recursive: true })
  const prefixos = Array.isArray(mimePrefix) ? mimePrefix : [mimePrefix]

  return multer({
    storage: multer.diskStorage({
      destination: uploadDir,
      filename: (_req, file, cb) => cb(null, `${nanoid(12)}${path.extname(file.originalname)}`),
    }),
    limits: { fileSize: limiteBytes },
    fileFilter: (_req, file, cb) => cb(null, prefixos.some((p) => file.mimetype.startsWith(p))),
  })
}

/**
 * Raiz de arquivos sensíveis (ex: fotos de evolução física) que NÃO podem ser
 * servidos via rota estática pública — só por rota autenticada que confere
 * dono do dado antes de ler o arquivo do disco.
 */
export const PRIVATE_UPLOADS_ROOT = path.join(__dirname, '..', '..', 'private-uploads')

/**
 * Cria um uploader multer que salva em backend/private-uploads/<subdir>/<chave>,
 * onde <chave> é o token do aluno (ou outro identificador) presente nos params
 * da rota — separa os arquivos de cada aluno em pastas isoladas.
 */
export function criarUploaderPrivadoPorChave(chaveParam: string, subdir: string, mimePrefix: string, limiteBytes: number) {
  return multer({
    storage: multer.diskStorage({
      destination: (req, _file, cb) => {
        const chave = (req.params as Record<string, string>)[chaveParam]
        // chave vem direto do param da URL (ex: token do aluno) e roda ANTES do
        // handler validar o dono — nunca confiar nela sem checar o formato, senão
        // um valor com ".." escaparia de PRIVATE_UPLOADS_ROOT via path.join.
        if (!chave || !/^[A-Za-z0-9_-]+$/.test(chave)) {
          cb(new Error('Identificador inválido'), '')
          return
        }
        const dir = path.join(PRIVATE_UPLOADS_ROOT, subdir, chave)
        fs.mkdirSync(dir, { recursive: true })
        cb(null, dir)
      },
      filename: (_req, file, cb) => cb(null, `${nanoid(12)}${path.extname(file.originalname)}`),
    }),
    limits: { fileSize: limiteBytes },
    fileFilter: (_req, file, cb) => cb(null, file.mimetype.startsWith(mimePrefix)),
  })
}
