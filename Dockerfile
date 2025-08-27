# Use PHP with Apache for production-ready deployment
FROM php:8.3-apache

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# Install SQLite extension (should be included by default, but ensuring it's available)
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY index.php .
COPY .htaccess .
COPY CLAUDE.md .
COPY README.md .

# Create uploads directory for future file attachments
RUN mkdir -p uploads && chown -R www-data:www-data uploads

# Create data directory for SQLite database with proper permissions
RUN mkdir -p data && chown -R www-data:www-data data

# Ensure proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Configure Apache to serve from /var/www/html
RUN sed -i 's!/var/www/html!/var/www/html!g' /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]