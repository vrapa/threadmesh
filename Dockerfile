FROM php:8.2-cli-bookworm AS runtime

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    THREADMESH_DB=/app/var/threadmesh.sqlite

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    && composer clear-cache

COPY . ./

RUN mkdir -p /app/var \
    && groupadd --gid 10001 threadmesh \
    && useradd --uid 10001 --gid threadmesh --home-dir /app --shell /usr/sbin/nologin threadmesh \
    && chown -R threadmesh:threadmesh /app/var

USER threadmesh

EXPOSE 8080 8081

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
