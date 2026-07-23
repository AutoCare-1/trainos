import ffmpeg from 'fluent-ffmpeg'
import fs from 'fs/promises'
import path from 'path'

const MAX_FRAMES = 8

/** Extrai até MAX_FRAMES frames de um vídeo, espaçados uniformemente, redimensionados a ~1024px de largura. */
export async function extrairFramesDeVideo(caminhoVideo: string, dirSaida: string): Promise<string[]> {
  await fs.mkdir(dirSaida, { recursive: true })
  const duracao = await obterDuracao(caminhoVideo)
  const quantidade = duracao > 0 ? Math.min(MAX_FRAMES, Math.max(1, Math.ceil(duracao / 2))) : MAX_FRAMES
  const timestamps = gerarTimestamps(duracao, quantidade)

  return new Promise((resolve, reject) => {
    const arquivos: string[] = []
    ffmpeg(caminhoVideo)
      .on('filenames', (nomes: string[]) => {
        nomes.forEach((nome) => arquivos.push(path.join(dirSaida, nome)))
      })
      .on('end', () => resolve(arquivos))
      .on('error', reject)
      .screenshots({
        timestamps,
        filename: 'frame-%i.jpg',
        folder: dirSaida,
        size: '1024x?',
      })
  })
}

function obterDuracao(caminhoVideo: string): Promise<number> {
  return new Promise((resolve, reject) => {
    ffmpeg.ffprobe(caminhoVideo, (err, metadata) => {
      if (err) reject(err)
      else resolve(metadata.format.duration ?? 0)
    })
  })
}

function gerarTimestamps(duracao: number, quantidade: number): string[] {
  if (duracao <= 0) return Array.from({ length: quantidade }, (_, i) => `${i}`)
  const intervalo = duracao / (quantidade + 1)
  return Array.from({ length: quantidade }, (_, i) => ((i + 1) * intervalo).toFixed(2))
}
