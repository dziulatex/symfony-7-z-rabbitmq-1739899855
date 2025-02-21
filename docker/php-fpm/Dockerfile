FROM php:8.3-fpm

ARG BUILD_ENVIRONMENT=dev

# Update and install necessary packages for MySQL, AMQP, Redis, and SSH
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libssl-dev \
    zlib1g-dev \
    curl \
    git \
    unzip \
    libxml2-dev \
    libzip-dev \
    default-mysql-client \
    librabbitmq-dev \
    openssh-client \
    openssh-server \
    && \
    pecl install apcu && \
    pecl install amqp && \
    pecl install redis && \
    docker-php-ext-install -j$(nproc) zip opcache intl pdo_mysql mysqli && \
    docker-php-ext-enable apcu pdo_mysql sodium amqp redis && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
# Install Xdebug only in dev environment
RUN if [ "$BUILD_ENVIRONMENT" = "dev" ]; then \
    install-php-extensions xdebug; \
    fi



COPY php.ini /usr/local/etc/php/conf.d/custom.ini

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