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

# Instalar fuentes TrueType de Microsoft (Arial, Times New Roman, etc.)
RUN apk add --no-cache fontconfig ttf-dejavu cabextract wget && \
  mkdir -p /usr/share/fonts/truetype/msttcorefonts && \
  cd /usr/share/fonts/truetype/msttcorefonts && \
  wget https://downloads.sourceforge.net/corefonts/arial32.exe && \
  cabextract arial32.exe && \
  rm arial32.exe && \
  fc-cache -f -v

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

# Copy application code from the 'app' directory into the web root
COPY app/ .
# Copy start script to a standard binary location and make it executable
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories including Nginx temp directories
RUN mkdir -p /var/www/html/logs \
  && mkdir -p /var/www/html/tmp \
  && mkdir -p /var/www/html/tmp/nginx \
  && mkdir -p /var/log/supervisor \
  && mkdir -p /run/nginx \
  && mkdir -p /run/php \
  && mkdir -p /tmp/nginx_client_body \
  && mkdir -p /tmp/nginx_proxy \
  && mkdir -p /tmp/nginx_fastcgi \
  && mkdir -p /tmp/nginx_uwsgi \
  && mkdir -p /tmp/nginx_scgi

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
  && chown -R www-data:www-data /var/log/supervisor \
  && chown -R www-data:www-data /run/nginx \
  && chown -R www-data:www-data /run/php \
  && chown -R www-data:www-data /tmp/nginx_* \
  && chmod -R 777 /tmp/nginx_*

# Don't switch to non-root user - supervisor needs root privileges
# USER www-data

# Expose port
EXPOSE 3000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:3000/health || exit 1

# Start supervisor as root
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]