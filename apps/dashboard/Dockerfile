FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php:8.2-cli-bookworm

WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY . ./

RUN groupadd --gid 10002 dashboard \
    && useradd --uid 10002 --gid dashboard --home-dir /app --shell /usr/sbin/nologin dashboard \
    && mkdir -p /app/temp \
    && chown -R dashboard:dashboard /app/temp

USER dashboard
EXPOSE 8082

CMD ["php", "-S", "0.0.0.0:8082", "-t", "public", "public/router.php"]
