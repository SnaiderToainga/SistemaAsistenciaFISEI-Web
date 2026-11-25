FROM php:8.2-apache

# Instalar librerías del sistema y extensiones necesarias para Supabase (PostgreSQL)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Habilitar mod_rewrite de Apache para el .htaccess
RUN a2enmod rewrite

# Copiar todo el proyecto al directorio web de Apache
COPY . /var/www/html/

# Asignar permisos correctos
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80