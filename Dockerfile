FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
  && docker-php-ext-install pdo pdo_mysql zip gd mbstring intl xml \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --optimize-autoloader --no-scripts --no-interaction

EXPOSE 8080

CMD php -S 0.0.0.0:${PORT:-8080} -t public/
