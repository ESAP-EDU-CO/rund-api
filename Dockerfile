# Use official PHP 8.3 FPM Alpine image (latest stable)
FROM php:8.3-fpm-alpine3.19

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and LibreOffice
RUN apk update && apk add --no-cache \
  # Basic utilities
  nano \
  zip \
  unzip \
  curl \
  wget \
  # LibreOffice and dependencies
  libreoffice \
  libreoffice-writer \
  libreoffice-calc \
  # Image processing libraries for PHPOffice
  libpng-dev \
  libjpeg-turbo-dev \
  freetype-dev \
  libzip-dev \
  icu-dev \
  # XML libraries (already included but needed for compilation)
  libxml2-dev \
  # Process management
  supervisor \
  # Web server
  nginx \
  # Clean up
  && rm -rf /var/cache/apk/*

# Configure and install only the PHP extensions that need compilation
# Note: xml, dom, xmlreader, xmlwriter, simplexml, fileinfo, mbstring are already built-in
RUN docker-php-ext-configure gd \
  --with-freetype \
  --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
  gd \
  zip \
  intl \
  opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first for better layer caching
COPY composer.json composer.lock* ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application code
COPY app/ ./app/
COPY start.sh ./
RUN chmod +x start.sh

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/www/html/logs \
  && mkdir -p /var/www/html/tmp \
  && mkdir -p /var/log/supervisor \
  && mkdir -p /run/nginx \
  && mkdir -p /run/php

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
  && chown -R www-data:www-data /var/log/supervisor \
  && chown -R www-data:www-data /run/nginx \
  && chown -R www-data:www-data /run/php

# Don't switch to non-root user - supervisor needs root privileges
# USER www-data

# Expose port
EXPOSE 3000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:3000/app/ || exit 1

# Start supervisor as root
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]