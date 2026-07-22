-- Peso e altura do aluno, coletados no cadastro para ajudar a direcionar o treino.
alter table students add column if not exists weight_kg numeric;
alter table students add column if not exists height_cm numeric;
