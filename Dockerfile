FROM php:8.2-cli
# force-rebuild-2

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

RUN useradd -m appuser

WORKDIR /app
COPY . .
RUN chown -R appuser:appuser /app

USER appuser

ENV COMPOSER_ALLOW_SUPERUSER=0
ENV APP_ENV=prod

RUN MERCURE_URL=http://localhost/.well-known/mercure \
    MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure \
    MERCURE_JWT_SECRET=dummy \
    composer install --optimize-autoloader --no-interaction

EXPOSE 8080

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
USER root
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
USER appuser

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
