FROM composer:2.9 AS composer
FROM php:8.5-alpine

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /srv/chess-api

# prevent the reinstallation of vendors at every changes in the source code
COPY . ./

RUN set -eux; \
	composer install; \
	composer clear-cache; \
    vendor/bin/phpunit

CMD ["/init"]
