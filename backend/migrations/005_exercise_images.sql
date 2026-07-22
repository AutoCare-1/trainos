-- Imagens reais de demonstração por exercício (fonte: wger.de, CC-BY-SA, com crédito).
-- Onde não houver imagem, o frontend usa a animação boneco-palito como fallback.
alter table exercises add column if not exists image_url text;
alter table exercises add column if not exists image_credit text;
