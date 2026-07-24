-- O dashboard "Meu Negócio" precisa achar rápido a última sessão concluída de
-- cada aluno de um profissional (pra calcular quem tá em risco); hoje só existe
-- índice em training_sessions(workout_id), forçando scan conforme a base cresce.
create index if not exists idx_training_sessions_student_status on training_sessions(student_id, status, finished_at desc);
