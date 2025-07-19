# Utiliser l'image officielle PHP avec Apache
FROM php:8.2-apache

# Copier les fichiers dans le dossier du serveur Apache
COPY . /var/www/html/

# Donner les bonnes permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
