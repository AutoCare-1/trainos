#!/bin/sh
# Roda antes do Apache subir a cada deploy/restart.
#
# `migrate --force` é seguro rodar sempre: migration já aplicada não roda de
# novo. `config:cache`/`route:cache` deliberadamente DEPOIS do storage:link e
# ANTES do apache subir — cachear config é o que faz a app não reler o .env a
# cada request, mas se `php artisan` já tivesse cacheado antes deste script
# rodar (não roda, a imagem não faz isso no build), as env vars do Railway
# setadas no deploy não apareceriam.
set -e

php artisan storage:link --force || true
php artisan migrate --force

# Os 3 seeders da biblioteca de exercícios e o mapeamento de vídeo são
# seguros de rodar em TODO deploy: nenhum dos três sobrescreve o que já
# existe (ExerciseSeeder faz updateOrCreate pelo nome — mesmo dado sempre;
# os outros dois só inserem o que falta) e aplicar-demonstracoes ignora
# quem já tem vídeo. Isso é o que faz "corrigir um vídeo" ou "adicionar um
# exercício" virar realidade em produção sozinho, só com git push — sem
# precisar de ninguém entrar no painel do Railway pra rodar comando à mão.
php artisan db:seed --class="Database\\Seeders\\ExerciseSeeder" --force
php artisan db:seed --class="Database\\Seeders\\ExercicioBibliotecaAmpliadaSeeder" --force
php artisan db:seed --class="Database\\Seeders\\ExercicioBibliotecaComplementarSeeder" --force
php artisan exercicios:aplicar-demonstracoes || true

# Catálogo de alimentos (POF/IBGE) e as medidas caseiras. Mesma lógica dos
# seeders acima: a chave é (codigo_pof, codigo_preparo), então rodar de novo
# atualiza em vez de duplicar, e o id do alimento sobrevive — que é o que liga
# o alimento à refeição que o aluno já registrou. É isso que faz uma correção
# na tabela (nome torto, medida errada) chegar em produção só com git push.
php artisan db:seed --class="Database\\Seeders\\AlimentoPofSeeder" --force

php artisan config:cache
php artisan route:cache

exec "$@"
