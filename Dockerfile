FROM php:8.2-apache

# Installer les dépendances GD
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli

# Copier les fichiers du site
COPY . /var/www/html/

# Donner les bons droits
RUN chown -R www-data:www-data /var/www/html
