# Usar una imagen base oficial de PHP 8.3 con Apache sobre Alpine Linux
FROM php:8.3-apache-alpine

# Definir el directorio de trabajo y la raíz de documentos de Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html
WORKDIR ${APACHE_DOCUMENT_ROOT}

# Habilitar mod_rewrite de Apache para URLs amigables si se necesita en el futuro
RUN a2enmod rewrite

# Instalar dependencias del sistema operativo
# - build-base, autoconf, etc., son necesarios para compilar extensiones de PHP.
# - icu-dev, libzip-dev, etc., son librerías para las extensiones.
# - libreoffice-writer nos provee el binario 'soffice' para conversiones.
# - nano y zip son las utilidades que solicitaste.
# - Se añade el repositorio 'community' de Alpine para poder instalar LibreOffice.
RUN apk update && \
    apk add --no-cache \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libxml2-dev \
        libxslt-dev \
        oniguruma-dev \
        # Dependencias para GD
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        # Utilidades solicitadas
        nano \
        zip \
        # LibreOffice (requiere el repositorio community)
        libreoffice-writer --repository=http://dl-cdn.alpinelinux.org/alpine/edge/community

# Instalar las extensiones de PHP requeridas por tus dependencias (PHPOffice, DomPDF)
# - Se determinaron a partir de tu composer.lock
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        bcmath \
        ctype \
        curl \
        dom \
        gd \
        iconv \
        intl \
        mbstring \
        pdo_mysql \
        simplexml \
        xsl \
        zip

# Instalar Composer (manejador de dependencias de PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar los archivos de dependencias y luego instalar para aprovechar el caché de Docker
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-plugins --no-scripts --no-dev --optimize-autoloader

# Copiar el resto del código de la aplicación
COPY . .

# Copiar y dar permisos de ejecución al script de inicio
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Exponer el puerto 80 del contenedor (Apache)
EXPOSE 80

# Comando que se ejecutará al iniciar el contenedor
CMD ["/usr/local/bin/start.sh"]