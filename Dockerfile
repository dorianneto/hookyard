FROM node:24 AS hookyard_node

FROM dunglas/frankenphp:php8.4-alpine AS hookyard_runner

COPY --from=hookyard_node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=hookyard_node /usr/local/bin/node /usr/local/bin/node

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

RUN apk update && apk add --no-cache supervisor bash

RUN install-php-extensions pdo_pgsql intl zip pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -sS https://get.symfony.com/cli/installer | bash \
  && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

RUN mkdir -p /var/log/supervisor

COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/queue.conf /etc/supervisor/conf.d/queue.conf
COPY docker/supervisor/scheduler.conf /etc/supervisor/conf.d/scheduler.conf

COPY docker/php/opcache.dev.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app
COPY . .

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "php-server", "--root=/app/public", "--listen=:80"]
