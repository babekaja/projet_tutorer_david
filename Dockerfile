# Utiliser l'image officielle PHP avec Apache
FROM php:8.2-apache

# Installer l'extension mysqli
RUN docker-php-ext-install mysqli

# Copier tous les fichiers dans le dossier web Apache
COPY . /var/www/html/

# Fixer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
