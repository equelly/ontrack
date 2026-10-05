FROM php:8.3-fpm-alpine

# Системные пакеты
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    $PHPIZE_DEPS \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    librdkafka-dev \
    bash \
    git \
    curl

# PHP расширения (ИСПРАВЛЕНО: добавлено корректное расширение redis через pecl)
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
&& pecl install redis \
&& docker-php-ext-enable redis

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Код приложения
WORKDIR /var/www/html
COPY . /var/www/html

# Права (ИСПРАВЛЕНО: даем права и пользователю root, так как supervisor в докере работает от root)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && mkdir -p /var/log/supervisor # Создаем директорию для логов самого супервизора

# Composer install (без dev для прода)
# ВАЖНО: запускаем от имени www-data или используем флаг --allow-plugins
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Конфиг supervisor
COPY deploy/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
COPY deploy/supervisor/laravel-reverb.conf /etc/supervisor/conf.d/laravel-reverb.conf

EXPOSE 9000 8081

CMD ["php-fpm"]
