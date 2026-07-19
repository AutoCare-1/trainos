import 'dotenv/config'
import { pool } from './pool'

const exercicios = [
  { name: 'Agachamento livre', muscle_group: 'Pernas', equipment: 'Barra', instructions: 'Pés na largura dos ombros, desça controlando o quadril para trás, mantenha o tronco ereto.' },
  { name: 'Supino reto', muscle_group: 'Peito', equipment: 'Barra', instructions: 'Deitado no banco, desça a barra até o peito e empurre até a extensão dos cotovelos.' },
  { name: 'Levantamento terra', muscle_group: 'Posterior', equipment: 'Barra', instructions: 'Mantenha a coluna neutra, empurre o chão com os pés, estenda quadril e joelho ao mesmo tempo.' },
  { name: 'Puxada frontal', muscle_group: 'Costas', equipment: 'Polia', instructions: 'Puxe a barra até a altura do queixo, contraindo as escápulas.' },
  { name: 'Desenvolvimento militar', muscle_group: 'Ombros', equipment: 'Barra', instructions: 'Empurre a barra acima da cabeça mantendo o core estável.' },
  { name: 'Remada curvada', muscle_group: 'Costas', equipment: 'Barra', instructions: 'Tronco inclinado à frente, puxe a barra em direção ao abdômen.' },
  { name: 'Leg press 45°', muscle_group: 'Pernas', equipment: 'Máquina', instructions: 'Desça controlando até 90° de flexão de joelho, empurre sem travar o joelho.' },
  { name: 'Rosca direta', muscle_group: 'Bíceps', equipment: 'Barra', instructions: 'Cotovelos fixos ao lado do corpo, flexione o antebraço até a contração máxima.' },
  { name: 'Tríceps corda', muscle_group: 'Tríceps', equipment: 'Polia', instructions: 'Cotovelos fixos, estenda o antebraço até a extensão completa.' },
  { name: 'Prancha abdominal', muscle_group: 'Core', equipment: 'Peso corporal', instructions: 'Mantenha o corpo alinhado da cabeça aos calcanhares, contraindo o abdômen.' },
  { name: 'Cadeira extensora', muscle_group: 'Pernas', equipment: 'Máquina', instructions: 'Estenda o joelho controladamente até quase a extensão completa.' },
  { name: 'Mesa flexora', muscle_group: 'Posterior', equipment: 'Máquina', instructions: 'Flexione o joelho trazendo o calcanhar em direção ao glúteo.' },
  { name: 'Elevação lateral', muscle_group: 'Ombros', equipment: 'Halteres', instructions: 'Eleve os braços lateralmente até a altura dos ombros, cotovelos levemente flexionados.' },
  { name: 'Afundo', muscle_group: 'Pernas', equipment: 'Halteres', instructions: 'Passo à frente, desça o joelho de trás quase até o chão, mantenha o tronco ereto.' },
  { name: 'Remada baixa', muscle_group: 'Costas', equipment: 'Polia', instructions: 'Puxe o cabo em direção ao abdômen mantendo a coluna neutra.' },
]

async function seed() {
  console.log('Inserindo exercícios de exemplo...')
  for (const ex of exercicios) {
    await pool.query(
      `insert into exercises (name, muscle_group, equipment, instructions)
       values ($1, $2, $3, $4)
       on conflict do nothing`,
      [ex.name, ex.muscle_group, ex.equipment, ex.instructions]
    )
  }
  console.log(`${exercicios.length} exercícios prontos.`)
  await pool.end()
}

seed().catch((err) => {
  console.error('Erro no seed:', err)
  process.exit(1)
})
