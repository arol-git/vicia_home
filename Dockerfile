FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

WORKDIR /app/vicia-bot
COPY vicia-bot/composer.json vicia-bot/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

FROM php:8.4-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .
COPY --from=dependencies /app/vendor ./vendor
COPY --from=dependencies /app/vicia-bot/vendor ./vicia-bot/vendor

RUN mkdir -p storage/logs

CMD ["sh", "-c", "(while true; do php mqtt/subscriber.php; code=$?; echo \"[MQTT supervisor] subscriber arrêté (code=$code), redémarrage dans 2 secondes\"; sleep 2; done) & PHP_CLI_SERVER_WORKERS=4 exec php -S 0.0.0.0:${PORT} -t public public/router.php"]
