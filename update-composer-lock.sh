#!/bin/bash
# Script to regenerate composer.lock with PHP 8.3
# This requires PHP 8.3 to be installed

echo "Checking PHP version..."
php -v

if ! php -r "if (version_compare(PHP_VERSION, '8.3.0', '<')) { exit(1); }"; then
    echo "ERROR: PHP 8.3+ is required. Current version: $(php -r 'echo PHP_VERSION;')"
    echo "Please install PHP 8.3 or use Docker:"
    echo "  docker build -t oms-php83 ."
    echo "  docker run --rm -v \$(pwd):/workspace -w /workspace oms-php83 composer update --no-interaction"
    exit 1
fi

echo "Regenerating composer.lock with PHP 8.3..."
composer update --no-interaction --lock

echo "Verifying installation..."
composer install --no-interaction

echo "✅ composer.lock regenerated successfully"
