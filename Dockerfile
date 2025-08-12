# PHP-Apache image with required extensions
FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Copy source (in dev, docker-compose will bind-mount instead)
COPY . /var/www/html

# PHP settings (override via your own php.ini if needed)
RUN { \
    echo "display_errors=On"; \
    echo "memory_limit=256M"; \
    echo "upload_max_filesize=32M"; \
    echo "post_max_size=32M"; \
  } > /usr/local/etc/php/conf.d/app.ini

EXPOSE 80
