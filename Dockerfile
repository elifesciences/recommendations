# --- composer
FROM composer:2.8 AS composer

COPY composer.json \
    composer.lock \
    ./
RUN composer --no-interaction install --no-dev --ignore-platform-reqs --no-autoloader --no-suggest --prefer-dist
COPY src/ src/
COPY test/ test/
RUN composer --no-interaction dump-autoload --classmap-authoritative


# --- composer_dev
FROM composer AS composer_dev

RUN composer --no-interaction install --ignore-platform-reqs --no-autoloader --no-suggest --prefer-dist
RUN composer --no-interaction dump-autoload --classmap-authoritative


# --- app
FROM dunglas/frankenphp:1.6.0-php8.3.21-bookworm AS app

RUN mkdir -p build var && \
    chown --recursive www-data:www-data var

COPY web/ web/
COPY --from=composer /app/vendor/ vendor/
COPY src/ src/

COPY Caddyfile /etc/frankenphp/Caddyfile
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

HEALTHCHECK --interval=10s --timeout=10s --retries=3 CMD bash -c '[[ "$(curl -k -s http://localhost/ping)" == "pong" ]]'

# --- dev
FROM app AS dev

COPY --from=composer_dev /app/vendor/ vendor/


# --- ci
FROM app AS ci

RUN mkdir -p build/ && \
    touch .php_cs.cache && \
    chown --recursive www-data:www-data build/ .php_cs.cache


COPY --from=composer_dev /usr/bin/composer /usr/bin/composer

COPY \
    phpunit.xml.dist \
    phpcs.xml.dist \
    project_tests.sh \
    ./
COPY composer.json composer.lock ./
COPY --from=composer_dev /app/vendor/ vendor/
COPY test/ test/

USER www-data
CMD ["./project_tests.sh"]


# --- default build target

FROM app
