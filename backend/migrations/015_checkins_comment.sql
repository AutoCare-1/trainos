-- Comentário opcional que o aluno pode deixar junto com a foto do check-in,
-- pro personal ver junto com a frequência (ex: "hoje treinei cansado, mas fui").
alter table checkins add column if not exists comment text;
