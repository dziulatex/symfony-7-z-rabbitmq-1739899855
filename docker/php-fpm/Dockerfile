FROM php:8.3-fpm

# Update and install necessary packages for MySQL
RUN apt-get update && \
    apt-get install -y --no-install-recommends libssl-dev zlib1g-dev curl git unzip libxml2-dev libzip-dev default-mysql-client && \
    pecl install apcu && \
    docker-php-ext-install -j$(nproc) zip opcache intl pdo_mysql mysqli && \
    docker-php-ext-enable apcu pdo_mysql sodium && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer (copied from the Composer image)
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# Set the entrypoint
ENTRYPOINT ["docker-entrypoint"]

# Expose port 9000 for php-fpm
EXPOSE 9000

CMD ["php-fpm"]