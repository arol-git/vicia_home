FROM php:8.4-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .

RUN mkdir -p storage/logs

CMD ["sh", "-c", "php mqtt/subscriber.php >> storage/logs/mqtt.log 2>&1 & exec php -S 0.0.0.0:${PORT} -t public public/router.php"]
