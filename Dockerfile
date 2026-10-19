# Render official PHP Laravel Docker deployment
# Reference: https://render.com/docs/deploy-php-laravel-docker
FROM richarvey/nginx-php-fpm:3.1.6

# Set working directory
WORKDIR /var/www/html

# Environment configuration
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

# Production Laravel defaults
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr
ENV COMPOSER_ALLOW_SUPERUSER 1

# Copy project files
COPY . .

# Copy custom Nginx configuration
COPY conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf

# Make startup script executable and fix line endings
RUN chmod +x /var/www/html/scripts/00-laravel-deploy.sh \
    && sed -i 's/\r$//' /var/www/html/scripts/00-laravel-deploy.sh

# Install production dependencies with optimized autoloader
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set correct storage, database, and cache permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/uploads /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/uploads /var/www/html/database

# Container entrypoint
CMD ["/start.sh"]
