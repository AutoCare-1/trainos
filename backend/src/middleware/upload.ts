import multer from 'multer'
import path from 'path'
import fs from 'fs'
import { nanoid } from 'nanoid'

/** Cria um uploader multer que salva em backend/uploads/<subdir>, aceitando só arquivos com o mimetype prefixado. */
export function criarUploader(subdir: string, mimePrefix: string, limiteBytes: number) {
  const uploadDir = path.join(__dirname, '..', '..', 'uploads', subdir)
  fs.mkdirSync(uploadDir, { recursive: true })

  return multer({
    storage: multer.diskStorage({
      destination: uploadDir,
      filename: (_req, file, cb) => cb(null, `${nanoid(12)}${path.extname(file.originalname)}`),
    }),
    limits: { fileSize: limiteBytes },
    fileFilter: (_req, file, cb) => cb(null, file.mimetype.startsWith(mimePrefix)),
  })
}
