# FROM php:8.3.7
# RUN apt-get update -y && apt-get install -y openssl zip unzip git libzip-dev libpq-dev libonig-dev libxml2-dev && docker-php-ext-install pdo pdo_pgsql pgsql mbstring
# RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring
# WORKDIR /app
# COPY . /app
# RUN composer install

# CMD php artisan migrate && php artisan serve --host=0.0.0.0 --port=8181
# EXPOSE 8181

FROM php:8.3.7

RUN apt-get update -y && apt-get install -y \
    unzip zip curl git libzip-dev libonig-dev libpq-dev libxml2-dev \
    libjpeg-dev libpng-dev libfreetype6-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring zip xml bcmath gd

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY . /app

# Clear cache and fix permissions
RUN composer clear-cache && chmod -R 775 /app

# Run Composer install
RUN COMPOSER_CACHE_DIR="/tmp/composer-cache" composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8181
CMD php artisan migrate && php artisan serve --host=0.0.0.0 --port=8181
