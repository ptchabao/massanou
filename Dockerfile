# Étape de construction
FROM composer:2.6 as build
WORKDIR /app

# Copier les fichiers de dépendances
COPY composer.json composer.lock ./

# Installer les dépendances PHP (sans les dépendances de développement)
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Étape d'exécution
FROM php:8.2-fpm

# Variables d'environnement
ENV APP_ENV=production
ENV APP_DEBUG=false

# Mettre à jour et installer les dépendances système
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mbstring exif pcntl bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Installer et configurer OPcache
RUN docker-php-ext-install opcache
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Installer Node.js et NPM
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

# Créer le répertoire de l'application
RUN mkdir -p /var/www/html
WORKDIR /var/www/html

# Copier les fichiers de l'application
COPY . .
COPY --from=build /app/vendor ./vendor

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Installer les dépendances frontend
RUN npm install && \
    npm run production && \
    rm -rf node_modules

# Optimiser l'application Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Exposer le port 9000 pour PHP-FPM
EXPOSE 9000

# Démarrer PHP-FPM
CMD ["php-fpm"]
