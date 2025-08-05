# Dockerfile para Microservicio de Ventas y Clientes
FROM php:8.2-fpm

# Información del mantenedor
LABEL maintainer="ivan@miramar.com"
LABEL description="Microservicio de Ventas y Clientes - Miramar Turismo"

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP necesarias
RUN docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www

# Copiar archivos de composer primero (para cache de Docker)
COPY composer.json composer.lock ./

# Instalar dependencias de PHP
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --optimize-autoloader

# Copiar el resto de la aplicación
COPY . .

# Usar .env.docker como configuración principal
RUN cp .env.docker .env

# Completar instalación de composer
RUN composer dump-autoload --optimize

# Generar clave de aplicación si no existe
RUN php artisan key:generate --no-interaction

# Crear directorios necesarios y asignar permisos
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Optimizaciones para producción
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Configurar PHP-FPM
RUN echo "pm.max_children = 20" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.start_servers = 3" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.min_spare_servers = 2" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_spare_servers = 4" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_requests = 1000" >> /usr/local/etc/php-fpm.d/www.conf

# Crear script de inicio
RUN echo '#!/bin/bash\n\
echo "🚀 Iniciando Microservicio de Ventas y Clientes..."\n\
echo "⏳ Esperando base de datos..."\n\
while ! nc -z db-ventas 3306; do sleep 1; done\n\
echo "✅ Base de datos disponible"\n\
echo "🔧 Asegurando permisos..."\n\
chown -R www-data:www-data /var/www/storage\n\
chmod -R 666 /var/www/storage/logs\n\
echo "🔄 Ejecutando migraciones..."\n\
php artisan migrate --force\n\
echo "🌱 Ejecutando seeders..."\n\
php artisan db:seed --force\n\
echo "🎯 Iniciando PHP-FPM..."\n\
php-fpm\n\
' > /start.sh && chmod +x /start.sh

# Instalar netcat para health checks
RUN apt-get update && apt-get install -y netcat-traditional && rm -rf /var/lib/apt/lists/*

# Exponer puerto PHP-FPM
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php artisan route:list > /dev/null || exit 1

# Usar script de inicio
CMD ["/start.sh"]