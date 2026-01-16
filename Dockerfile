FROM php:8.2-apache

# Installation des dépendances système pour le SSL et PDO
RUN apt-get update && apt-get install -y libssl-dev && docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
