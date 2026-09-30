FROM php:8.3-fpm-alpine

# Системные пакеты
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    redis \
    $PHPIZE_DEPS \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    librdkafka-dev \
    bash \
    git \
    curl

# PHP расширения
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    mbstring \
    xml \
    bcmath \
    pcntl \
    opcache \
    redis

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Код приложения
WORKDIR /var/www/html
COPY . /var/www/html

# Права
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Composer install (без dev для прода)
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Конфиг supervisor
COPY deploy/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
COPY deploy/supervisor/laravel-reverb.conf /etc/supervisor/conf.d/laravel-reverb.conf

EXPOSE 9000 8081

CMD ["php-fpm"]
