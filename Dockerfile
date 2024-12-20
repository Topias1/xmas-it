# Use an official PHP image with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Copy project files to the container
COPY . /var/www/html

# Expose port 80 to access the app
EXPOSE 80
