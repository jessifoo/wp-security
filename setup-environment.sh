#!/bin/bash
# setup-environment.sh - Environment setup script for WordPress Plugin CI/CD
# This script ensures all required tools are installed

set -e

echo "🔧 Setting up WordPress Plugin Development Environment..."

# Update package lists
sudo apt-get update -qq

# Install PHP repository
if ! grep -q "ondrej/php" /etc/apt/sources.list.d/* 2>/dev/null; then
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get update -qq
fi

# Install PHP 8.2 and extensions
if ! command -v php8.2 &> /dev/null; then
    echo "📦 Installing PHP 8.2..."
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
        php8.2 \
        php8.2-cli \
        php8.2-common \
        php8.2-mbstring \
        php8.2-xml \
        php8.2-curl \
        php8.2-zip \
        php8.2-gd \
        php8.2-mysql
fi

# Set PHP in PATH
export PATH="/usr/bin:$PATH"
if ! command -v php &> /dev/null; then
    sudo ln -sf /usr/bin/php8.2 /usr/bin/php || true
fi

# Verify PHP installation
echo "✅ PHP Version:"
php --version

# Verify PHP extensions
echo "✅ PHP Extensions:"
php -m | grep -E "json|mbstring|xml|curl|pcre" || echo "⚠️  Some extensions missing"

# Install Composer
if ! command -v composer &> /dev/null; then
    echo "📦 Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

# Verify Composer
echo "✅ Composer Version:"
composer --version

# Install project dependencies
if [ -f "composer.json" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --no-progress --prefer-dist
fi

echo "✅ Environment setup complete!"
echo ""
echo "Available tools:"
echo "  - PHP: $(php --version | head -1)"
echo "  - Composer: $(composer --version | head -1)"
echo "  - PHPCS: $(vendor/bin/phpcs --version 2>/dev/null || echo 'Not installed')"
