# =============================================================================
# FLASHCOMMERCE.ENGINE - DOCKERFILE (PHP 8.2 Alpine Production Hardened)
# =============================================================================
FROM php:8.2-cli-alpine

WORKDIR /var/www/html

# 1. Install extension installer & core extensions (pdo_mysql, redis, curl, opcache)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis curl opcache

# 2. Install Composer 2 via multi-stage copy
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Layer Caching: Copy Composer manifests first
COPY composer.json ./

# 4. Install dependencies (optimized autoloader)
RUN composer dump-autoload --optimize --no-dev

# 5. Copy application source code
COPY . .

# 6. Set proper permissions and non-root user
RUN chown -R www-data:www-data /var/www/html

USER www-data

ENV PORT=8000
EXPOSE 8000

# 7. Run lightweight HTTP server binding on public/ directory
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
