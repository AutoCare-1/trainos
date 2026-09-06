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
php artisan config:cache
php artisan route:cache

exec "$@"
