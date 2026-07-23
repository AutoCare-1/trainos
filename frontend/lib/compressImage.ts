/**
 * Redimensiona e comprime uma imagem no navegador antes do upload, via Canvas —
 * sem depender de biblioteca externa. Evita sobrecarregar disco e a chamada de
 * visão da IA com fotos gigantes vindas direto da câmera do celular.
 */
export async function comprimirImagem(file: File, maxLado = 1280, qualidade = 0.82): Promise<Blob> {
  const bitmap = await createImageBitmap(file)
  const escala = Math.min(1, maxLado / Math.max(bitmap.width, bitmap.height))
  const largura = Math.round(bitmap.width * escala)
  const altura = Math.round(bitmap.height * escala)

  const canvas = document.createElement('canvas')
  canvas.width = largura
  canvas.height = altura
  const ctx = canvas.getContext('2d')
  if (!ctx) return file

  ctx.drawImage(bitmap, 0, 0, largura, altura)

  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob ?? file), 'image/jpeg', qualidade)
  })
}
