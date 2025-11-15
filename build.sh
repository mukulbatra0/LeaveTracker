#!/bin/bash

# Render.com build script for ELMS

echo "Starting build process..."

# Install Composer dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Create necessary directories
echo "Creating directories..."
mkdir -p uploads
mkdir -p uploads/documents
mkdir -p uploads/profiles

# Set permissions
echo "Setting permissions..."
chmod -R 755 .
chmod -R 777 uploads

# Run database setup if this is the first deployment
echo "Setting up database..."
php setup-render-db.php

echo "Build completed successfully!"