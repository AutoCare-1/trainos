# Imagem de produção do backend Laravel, pensada pro Railway (auto-detecta
# este Dockerfile pelo nome). PHP 8.3 + Apache, mesmas extensões do CI
# (mbstring, pdo_mysql, bcmath, exif, gd, zip) — ver .github/workflows/ci.yml.
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath exif gd zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Deixa UM módulo de multiprocessamento carregado.
#
# O apt acima puxa o pacote do Apache junto com as libs, e o postinst do Debian
# habilita o mpm_event por cima do mpm_prefork que a imagem já traz. Com dois
# MPMs carregados o Apache recusa a subir ("More than one MPM loaded") e o
# container morre sem servir nada — foi assim que o primeiro deploy falhou.
# mod_php exige prefork, então é ele que fica.
#
# Em RUN separado de propósito: comentário dentro de um RUN com continuação de
# linha depende de detalhe do parser, e `|| true` no meio de uma cadeia `&&`
# engole a falha dos comandos anteriores.
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Aponta o Apache pra public/, como todo projeto Laravel exige.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Dependências primeiro (cache de camada) — o código muda bem mais que o
# composer.json.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Valida a config do Apache no BUILD. Aqui embaixo, e não junto do a2enmod,
# porque o -t confere se o DocumentRoot existe — e public/ só existe depois do
# COPY. Falhar aqui custa 2 minutos; falhar no deploy custa um container que
# sobe, não serve nada e só diz "healthcheck failed".
RUN apache2ctl -t

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
