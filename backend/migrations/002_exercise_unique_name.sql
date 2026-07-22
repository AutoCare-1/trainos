-- Garante que não haja exercícios duplicados por nome, e permite que o seed
-- seja reexecutado com segurança (ON CONFLICT (name) DO NOTHING).
create unique index if not exists idx_exercises_name on exercises (name);
