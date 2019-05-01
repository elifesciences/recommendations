ARG image_tag=latest
ARG php_version
FROM elifesciences/recommendations_composer:${image_tag} AS composer
FROM elifesciences/php_7.3_fpm:${php_version}

ENV PROJECT_FOLDER=/srv/recommendations
ENV PHP_ENTRYPOINT=web/app.php
WORKDIR ${PROJECT_FOLDER}

USER root
RUN mkdir -p build var && \
    chown --recursive elife:elife . && \
    chown --recursive www-data:www-data var

#COPY --chown=elife:elife .docker/smoke_tests.sh ./
COPY --chown=elife:elife web/ web/
COPY --from=composer --chown=elife:elife /app/vendor/ vendor/
COPY --chown=elife:elife src/ src/

USER www-data
