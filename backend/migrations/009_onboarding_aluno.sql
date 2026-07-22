-- Marca quando o próprio aluno preencheu a avaliação de saúde (PAR-Q) no primeiro
-- acesso ao portal, distinguindo de quando o profissional preenche em nome dele.
alter table students add column if not exists onboarding_completed_at timestamptz;
