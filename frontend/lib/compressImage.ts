/**
 * Redimensiona e comprime uma imagem no navegador antes do upload, via Canvas —
 * sem depender de biblioteca externa. Evita sobrecarregar disco e a chamada de
 * visão da IA com fotos gigantes vindas direto da câmera do celular.
 *
 * 900px de lado maior: o custo da chamada de visão da Anthropic é ~proporcional
 * a largura×altura da imagem, então cair de 1280 pra 900 corta ~metade dos
 * tokens de entrada por foto. 900px ainda é nítido o bastante pra "o agachamento
 * desce o suficiente?" ou "que aparelhos tem nessa academia?".
 */
export async function comprimirImagem(file: File, maxLado = 900, qualidade = 0.72): Promise<Blob> {
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
