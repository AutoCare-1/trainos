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

# O Railway injeta a porta em $PORT e faz o healthcheck nela; a imagem
# php:8.3-apache escuta na 80 fixa. Sem casar os dois o deploy sobe, responde
# na porta errada, o healthcheck de /up falha e o Railway derruba o container —
# com log de "healthcheck failed" e nenhuma pista do motivo. Fora do Railway
# (build local, docker run) o :-80 mantém o comportamento de sempre.
PORTA="${PORT:-80}"
sed -ri "s/^Listen 80$/Listen ${PORTA}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORTA}>/" /etc/apache2/sites-available/*.conf

php artisan storage:link --force || true

# Espera o banco aceitar conexão antes de migrar.
#
# No primeiro deploy o plugin de MySQL do Railway costuma ainda estar subindo
# quando o backend sobe. Sem esta espera, o `migrate` falha, o `set -e` mata o
# entrypoint e o container entra em crash-loop — e como a política de restart
# é ON_FAILURE com 3 tentativas, um banco lento gasta as três e o deploy morre
# de vez. O sintoma no painel é "healthcheck failed", que não aponta pra causa.
TENTATIVA=0
until php -r '
    $dsn = sprintf("mysql:host=%s;port=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 3306);
    new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
' 2>/dev/null; do
    TENTATIVA=$((TENTATIVA + 1))
    if [ "$TENTATIVA" -ge 30 ]; then
        echo "Banco não respondeu depois de 30 tentativas (60s). Abortando." >&2
        exit 1
    fi
    echo "Banco ainda não aceita conexão; tentativa ${TENTATIVA}/30..."
    sleep 2
done

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

# Tipos de notificação. Faltava aqui: sem eles a tela de notificações do
# personal sobe vazia e nenhum aviso é disparado, porque tudo é resolvido por
# esse catálogo. É updateOrCreate pela chave, então rodar sempre é seguro.
php artisan db:seed --class="Database\\Seeders\\NotificationTypesSeeder" --force

# Catálogo de alimentos (POF/IBGE) e as medidas caseiras. Mesma lógica dos
# seeders acima: a chave é (codigo_pof, codigo_preparo), então rodar de novo
# atualiza em vez de duplicar, e o id do alimento sobrevive — que é o que liga
# o alimento à refeição que o aluno já registrou. É isso que faz uma correção
# na tabela (nome torto, medida errada) chegar em produção só com git push.
php artisan db:seed --class="Database\\Seeders\\AlimentoPofSeeder" --force

php artisan config:cache
php artisan route:cache

exec "$@"
