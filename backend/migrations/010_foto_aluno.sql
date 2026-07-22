-- Foto de perfil do aluno, pra facilitar reconhecimento pelo personal e nos quadros
-- de competição/engajamento (desafios).
alter table students add column if not exists photo_url text;
