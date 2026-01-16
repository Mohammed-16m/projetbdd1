FROM php:8.2-apache

# Installation des extensions PHP nécessaires pour MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copie des fichiers du projet dans le serveur
COPY . /var/www/html/

# Donne les permissions
RUN chown -R www-data:www-data /var/www/html/

# Définit le port pour Render
EXPOSE 80