-- Estruturas de treino além da série tradicional (bi-set, tri-set, superset,
-- circuito, drop-set, rest-pause, cluster, AMRAP, EMOM).
-- group_label agrupa visualmente exercícios executados juntos (ex: "A" para
-- um bi-set A1/A2) — nulo/vazio significa exercício isolado (tradicional).
alter table workout_exercises add column if not exists structure_type text not null default 'tradicional';
alter table workout_exercises add column if not exists group_label text;

alter table workout_template_exercises add column if not exists structure_type text not null default 'tradicional';
alter table workout_template_exercises add column if not exists group_label text;
