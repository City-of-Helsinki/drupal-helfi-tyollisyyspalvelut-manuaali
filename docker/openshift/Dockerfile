FROM ghcr.io/city-of-helsinki/drupal-docker-base:8.0

COPY / /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --prefer-dist --no-interaction --no-dev --optimize-autoloader
RUN composer dump-autoload --optimize

# Copy deploy script
COPY docker/openshift/entrypoints/20-deploy.sh /entrypoints
RUN chmod +x /entrypoints/20-deploy.sh

COPY docker/openshift/entrypoints/10-es-cert.sh /entrypoints
RUN chmod +x /entrypoints/10-es-cert.sh
RUN mkdir /usr/local/share/ca-certificates/extra
RUN chmod 0777 /usr/local/share/ca-certificates/extra
RUN chmod -R 0777 /etc/ssl/certs
# Necessary for the ES cert adding.
RUN apk add --no-cache su-exec

# Copy cron scripts
RUN mkdir /crons
COPY docker/openshift/crons/ /crons
RUN chmod +x /crons/*
