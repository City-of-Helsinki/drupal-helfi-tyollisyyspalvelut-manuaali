FROM ghcr.io/city-of-helsinki/drupal-docker-base:8.3

COPY / /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --prefer-dist --no-interaction --no-dev --optimize-autoloader
RUN composer dump-autoload --optimize

# Copy deploy script
COPY docker/openshift/entrypoints/20-deploy.sh /entrypoints
RUN chmod +x /entrypoints/20-deploy.sh

# Copy cron scripts
RUN mkdir /crons
COPY docker/openshift/crons/ /crons
RUN chmod +x /crons/*
