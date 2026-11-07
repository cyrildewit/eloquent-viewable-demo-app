FROM php:8.5-fpm-alpine

ARG BUILD_GID=1000
ARG BUILD_UID=1000

RUN apk add --no-cache \
    bash \
    curl \
    git \
    nodejs \
    npm \
    shadow \
    sqlite \
    supervisor \
    unzip

# Map www-data to the host user so bind-mounted files keep sane ownership
RUN groupdel dialout || true \
    && groupmod -g ${BUILD_GID} www-data \
    && usermod -s /bin/sh -g ${BUILD_GID} -u ${BUILD_UID} www-data

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pcntl pdo_mysql pdo_sqlite redis zip gd intl opcache xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY ./docker/php/php.ini /usr/local/etc/php/php.ini

# node_modules lives in an anonymous volume, which copies this ownership.
RUN mkdir -p /var/www/html/node_modules && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
