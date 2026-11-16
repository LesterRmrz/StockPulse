# Custom PHP image for the Laravel backend.
# Installs everything once at build time, so the container starts instantly.
FROM php:8.4-cli

# git + unzip are needed by Composer to download packages
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# MySQL driver for Laravel (previously installed on every container start)
RUN docker-php-ext-install pdo_mysql

# Copy the Composer binary from the official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/backend
