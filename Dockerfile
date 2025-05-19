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
FROM ghcr.io/elifesciences/php:8.4-fpm AS app

ENV PROJECT_FOLDER=/srv/recommendations
ENV PHP_ENTRYPOINT=web/app.php
WORKDIR ${PROJECT_FOLDER}

USER root
RUN mkdir -p build var && \
    chown --recursive elife:elife . && \
    chown --recursive www-data:www-data var

COPY --chown=elife:elife web/ web/
COPY --from=composer --chown=elife:elife /app/vendor/ vendor/
COPY --chown=elife:elife src/ src/

USER www-data
HEALTHCHECK --interval=10s --timeout=10s --retries=3 CMD assert_fpm /ping "pong"

# --- dev
FROM app AS dev

COPY --from=composer_dev --chown=elife:elife /app/vendor/ vendor/


# --- ci
FROM app AS ci

USER root
RUN mkdir -p build/ && \
    touch .php_cs.cache && \
    chown --recursive www-data:www-data build/ .php_cs.cache


COPY --from=composer_dev /usr/bin/composer /usr/bin/composer

COPY --chown=elife:elife \
    phpunit.xml.dist \
    phpcs.xml.dist \
    project_tests.sh \
    ./
COPY --chown=elife:elife composer.json composer.lock ./
COPY --from=composer_dev --chown=elife:elife /app/vendor/ vendor/
COPY --chown=elife:elife test/ test/

USER www-data
CMD ["./project_tests.sh"]


# --- default build target

FROM app
