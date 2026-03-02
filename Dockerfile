# Use PHP 8.4 with Apache (required by Composer deps)
FROM php:8.4-apache

# Install required packages and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    gnupg \
    zlib1g-dev \
    pkg-config \
    libonig-dev \
    locales-all \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libgettextpo-dev \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath gd gettext mysqli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy ChurchCRM source code
COPY . /var/www/html

# Build frontend assets in production mode (required for setup UI)
RUN cd /var/www/html \
    && npm install --no-audit --no-fund --ignore-scripts \
    && NODE_ENV=production npm run build:js

# Generate file signatures for integrity check
RUN node /var/www/html/scripts/generate-signatures-node.js

# Apache: serve from src/ (ChurchCRM entry point) and allow .htaccess
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/src\n\
    <Directory /var/www/html/src>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf \
    && a2enmod rewrite headers \
    && chown -R www-data:www-data /var/www/html

# Entrypoint to ensure Config.php exists and Propel models are generated
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80
EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
