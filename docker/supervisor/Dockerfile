FROM php:8.3-fpm

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

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Install Composer (copied from the Composer image)
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Install supervisor and SSH
RUN apt-get update && \
    apt-get install -y supervisor openssh-client openssh-server && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Create supervisor log directory and copy config
RUN mkdir -p /var/log/supervisor
COPY messenger-worker.conf /etc/supervisor/conf.d/

# Override the CMD to run supervisor instead of php-fpm
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]