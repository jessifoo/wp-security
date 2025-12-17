# PHP 8.3 Setup Instructions

## Current Status

The codebase requires PHP 8.3, but the system is currently running PHP 8.2.29.

## Installation Steps

### Ubuntu/Debian

```bash
# Add PHP repository (if not already added)
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update

# Install PHP 8.3 and required extensions
sudo apt-get install -y \
    php8.3 \
    php8.3-cli \
    php8.3-common \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-zip \
    php8.3-gd \
    php8.3-mysql \
    php8.3-mysqli \
    php8.3-sqlite3

# Set PHP 8.3 as default
sudo update-alternatives --set php /usr/bin/php8.3

# Verify installation
php -v  # Should show PHP 8.3.x
```

### Verify Installation

```bash
php -v
# Should output: PHP 8.3.x

composer check-platform-reqs
# Should show all requirements met
```

## Code Compatibility

The codebase has been checked for PHP 8.3 compatibility:

✅ **No dynamic properties** - All properties are properly declared
✅ **No deprecated features** - Code uses modern PHP patterns
✅ **Syntax valid** - All files pass PHP syntax check
✅ **Composer compatible** - composer.lock updated for PHP 8.3

## After Installation

1. Regenerate composer.lock (if needed):
   ```bash
   composer update --no-interaction --lock
   ```

2. Verify installation:
   ```bash
   composer install --no-interaction
   composer check-platform-reqs
   ```

3. Run tests:
   ```bash
   composer test
   ```

## GitHub Actions

All GitHub Actions workflows are configured to use PHP 8.3:
- ✅ phpcs.yml
- ✅ psalm.yml
- ✅ phpmd.yml
- ✅ plugin-check.yml

CI will use PHP 8.3 automatically.
