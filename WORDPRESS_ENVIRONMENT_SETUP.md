# WordPress Environment Setup Guide

## Overview
This document describes how to set up a local WordPress environment for plugin development and testing.

## Environment Status
✅ **WordPress Environment Successfully Configured**

- **WordPress Version**: 6.8.3
- **Database**: MariaDB 10.11.13
- **PHP Version**: 8.1.33
- **WP-CLI**: Installed and configured
- **Plugin Check**: Installed and active

## Setup Instructions

### 1. Install Dependencies

```bash
# Install MariaDB
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client

# Install PHP MySQL extensions
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y php8.1-mysql php8.1-mysqli

# Enable PHP extensions
sudo phpenmod mysqli pdo_mysql
```

### 2. Start Database

```bash
# Start MariaDB
sudo mysqld_safe --user=mysql --datadir=/var/lib/mysql \
  --pid-file=/var/run/mysqld/mysqld.pid \
  --socket=/var/run/mysqld/mysqld.sock --port=3306 > /dev/null 2>&1 &

# Create database and user
sudo mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS wordpress_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'wpuser'@'localhost' IDENTIFIED BY 'wppass';
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wpuser'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 3. Install WordPress

```bash
# Download WordPress
mkdir -p wordpress
cd wordpress
wp core download --force --allow-root

# Configure WordPress
cp wp-config-sample.php wp-config.php
sed -i "s/database_name_here/wordpress_test/" wp-config.php
sed -i "s/username_here/wpuser/" wp-config.php
sed -i "s/password_here/wppass/" wp-config.php

# Install WordPress
wp core install --url=http://localhost --title="Test Site" \
  --admin_user=admin --admin_password=admin \
  --admin_email=admin@example.com --allow-root
```

### 4. Install Plugin Check

```bash
# Clone plugin-check
git clone https://github.com/WordPress/plugin-check.git wordpress/wp-content/plugins/plugin-check

# Install dependencies
cd wordpress/wp-content/plugins/plugin-check
composer install --no-interaction --no-progress

# Activate plugin-check
cd ../../..
wp plugin activate plugin-check --path=wordpress --allow-root
```

### 5. Copy Your Plugin

```bash
# Copy plugin to WordPress plugins directory
mkdir -p wordpress/wp-content/plugins/obfuscated-malware-scanner
rsync -av --exclude='wordpress' --exclude='vendor' \
  --exclude='.git' --exclude='node_modules' \
  . wordpress/wp-content/plugins/obfuscated-malware-scanner/
```

## Running Plugin Check

```bash
# Run plugin check
wp plugin check obfuscated-malware-scanner --path=wordpress --allow-root

# View only plugin-specific issues
wp plugin check obfuscated-malware-scanner --path=wordpress --allow-root 2>&1 | \
  grep -E "FILE:.*obfuscated-malware-scanner" -A 200
```

## Current Plugin Check Results

### Main Plugin File (`obfuscated-malware-scanner.php`)

**Errors:**
1. **Plugin Headers**:
   - Invalid Plugin URI domain (example.com)
   - Invalid Author URI domain (example.com)
   - Invalid Network header (should be removed if not needed)
   - Domain Path points to non-existent folder

2. **Naming Conventions**:
   - Constants should be prefixed: `OMS_VERSION`, `OMS_PLUGIN_DIR`, `OMS_PLUGIN_URL`, `OMS_PLUGIN_BASENAME`, `OMS_NOTIFY_ADMIN`
   - Function `run_obfuscated_malware_scanner` should be prefixed

### Scanner Class (`includes/class-obfuscated-malware-scanner.php`)

**Errors:**
1. **Naming Conventions**:
   - Class `Obfuscated_Malware_Scanner` should be prefixed
   - Constant `OMS_RATE_LIMIT_ENABLED` should be prefixed

2. **File Operations**:
   - Multiple uses of direct PHP file functions instead of WP_Filesystem:
     - `fopen()`, `fread()`, `fclose()`
     - `mkdir()`, `is_writable()`, `rename()`, `unlink()`, `chmod()`

**Warnings:**
1. **Debug Functions**:
   - `error_log()` usage (lines 104, 1200)
   - `debug_backtrace()` usage (line 1189)

2. **PHP Functions**:
   - `ini_set()` usage (line 575)

## Next Steps

1. **Fix Plugin Headers**: Update Plugin URI and Author URI to valid domains
2. **Remove Network Header**: Remove if not needed for multisite
3. **Create Languages Directory**: Create `languages/` folder or remove Domain Path header
4. **Consider WP_Filesystem**: Evaluate if file operations should use WP_Filesystem API
5. **Review Debug Code**: Remove or conditionally enable debug functions

## Notes

- The WordPress environment is located in `/workspace/wordpress/`
- Database credentials: `wpuser` / `wppass` / `wordpress_test`
- WordPress admin: `admin` / `admin`
- Plugin Check is checking all plugins, filter output to see only your plugin's issues
