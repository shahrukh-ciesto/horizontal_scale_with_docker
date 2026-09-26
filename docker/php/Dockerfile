FROM php:8.4-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        pcntl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# OPcache
RUN docker-php-ext-install opcache

COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM configuration
COPY ./docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Make sure PHP has a writable temporary directory
RUN mkdir -p /tmp \
    && chmod 1777 /tmp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm", "-F"]
