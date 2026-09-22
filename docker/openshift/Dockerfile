FROM ghcr.io/city-of-helsinki/drupal-docker-base:8.4

COPY / /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --prefer-dist --no-interaction --no-dev --optimize-autoloader
RUN composer dump-autoload --optimize

RUN mkdir -p /crons /entrypoints

COPY docker/openshift/entrypoints /entrypoints
COPY docker/openshift/crons/ /crons

RUN chmod -R +x /entrypoints/* /crons/*

COPY docker/openshift/init.sh /