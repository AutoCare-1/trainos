-- Avaliação física: anamnese resumida (segurança) + perímetros/composição adicionais nas medições.
alter table students add column if not exists par_q_answers jsonb;
alter table students add column if not exists health_notes text;

alter table body_measurements add column if not exists waist_cm numeric;
alter table body_measurements add column if not exists hip_cm numeric;
alter table body_measurements add column if not exists body_fat_pct numeric;
