# Use an official PHP image with Apache
FROM php:8.2-apache

# Install necessary extensions (e.g., MySQL, PDO)
RUN docker-php-ext-install

# Copy project files into the container
COPY . /var/www/html

# Set permissions (optional)
RUN chown -R www-data:www-data /var/www/html
