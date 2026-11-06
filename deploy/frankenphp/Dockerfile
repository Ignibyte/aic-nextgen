# AIC on FrankenPHP: PHP 8.5 in classic mode, with the app's committed build/ output.
# Build from a clean checkout's root: docker build -f deploy/frankenphp/Dockerfile -t aic .
FROM dunglas/frankenphp:php8.5

RUN install-php-extensions pdo_pgsql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json ./
COPY framework framework
COPY gates gates
COPY scaffold scaffold
COPY workflow workflow
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY bin/aic bin/aic
COPY bootstrap bootstrap
COPY build build
COPY config config
COPY modules modules
COPY public public
COPY themes themes
RUN composer dump-autoload --no-dev --optimize --no-scripts

COPY deploy/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
