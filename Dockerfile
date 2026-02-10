FROM php:8.2-cli-alpine

# ---------- System packages (runtime)
RUN apk add --no-cache \
    git \
    unzip \
    libpq \
    libzip \
    freetype \
    libjpeg-turbo \
    libpng \
    libstdc++ \
    bash \
    openssl \
    tzdata \
    mysql-client

# ---------- Build deps
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libpq-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    linux-headers \
    openssl-dev \
    mariadb-dev

# ---------- PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        sockets \
        pcntl \
        zip \
        gd \
        opcache

# ---------- Swoole (PINNED)
RUN pecl install swoole-5.1.2 \
    && docker-php-ext-enable swoole

# ---------- OPcache config (مخصوص CLI + Swoole)
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=1"; \
  echo "opcache.validate_timestamps=0"; \
  echo "opcache.revalidate_freq=0"; \
  echo "opcache.max_accelerated_files=100000"; \
  echo "opcache.memory_consumption=256"; \
  echo "opcache.interned_strings_buffer=16"; \
  echo "opcache.jit=0"; \
} > /usr/local/etc/php/conf.d/99-opcache.ini

# ---------- Cleanup build deps
RUN apk del .build-deps

# ---------- Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------- Create non-root user FIRST
RUN addgroup -g 1000 app \
    && adduser -D -G app -u 1000 app

# ---------- Composer install (dependencies only; App/ not copied yet)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

# ---------- Copy project (must be before regenerating autoload)
COPY . .

# ---------- Regenerate autoload so App\* classes (e.g. PDOPool) are in classmap
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- Runtime dirs + permissions
RUN mkdir -p \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R app:app /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# ---------- Switch user
USER app

EXPOSE 9501

CMD ["php", "server.php"]
