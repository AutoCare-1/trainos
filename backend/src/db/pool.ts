import { Pool } from 'pg'

const connectionString = process.env.DATABASE_URL
if (!connectionString) throw new Error('DATABASE_URL não configurada no .env')

export const pool = new Pool({ connectionString })
