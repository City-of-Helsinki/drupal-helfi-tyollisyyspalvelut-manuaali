FROM drupal:9.1-php7.4-apache-buster

# Setup phase required for Composer.
RUN rm -rf /var/www/html
RUN ln -s /var/www/web /var/www/html
RUN rm -rf /opt/drupal
RUN apt-get update; apt-get install git-core -y
RUN curl -o /usr/local/bin/composer https://getcomposer.org/download/latest-1.x/composer.phar

# Per-build things. First any necessary for Composer, then rest (better cacheability)
COPY web/ /var/www/web/
COPY patches/ /var/www/patches/
COPY scripts/ /var/www/scripts/
COPY composer.json /var/www/composer.json
COPY composer.lock /var/www/composer.lock
RUN cd /var/www; composer install

# Then do the rest.
RUN echo "display_errors=Off\nmemory_limit=256M\nlog_errors = On" > /usr/local/etc/php/conf.d/docker-image.ini

COPY config/ /var/www/config/
COPY package-lock.json /var/www/package-lock.json
COPY phpcs.xml /var/www/phpcs.xml
