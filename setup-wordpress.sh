#!/bin/bash
# setup-wordpress.sh - WordPress Environment Setup Script
# This script sets up a complete WordPress environment for plugin development
#
# SECURITY WARNING:
# The default database credentials (wordpress_test/wpuser/wppass) are for
# LOCAL DEVELOPMENT ONLY and must NEVER be used in production or exposed systems.
# Always override DB_NAME, DB_USER, and DB_PASS environment variables with
# secure credentials for any non-local environment.
#
# See WORDPRESS_ENVIRONMENT_SETUP.md for documentation on environment variables.

set -e

export PATH="/usr/bin:$PATH"

# Store the project root directory (where this script is located)
# This ensures we can always return to the correct directory regardless of cd operations
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# DEBUG flag: set DEBUG=1 for verbose output, unset for quiet mode
DEBUG="${DEBUG:-0}"

# Database credentials - can be overridden via environment variables
# WARNING: Defaults are for LOCAL DEVELOPMENT ONLY - never use in production!
DB_NAME="${DB_NAME:-wordpress_test}"
DB_USER="${DB_USER:-wpuser}"
DB_PASS="${DB_PASS:-wppass}"

# Log the credentials being used (with security warning)
echo "🔧 Setting up WordPress Environment..."
echo -e "${YELLOW}⚠️  SECURITY NOTICE: Using database credentials for LOCAL DEVELOPMENT ONLY${NC}"
echo -e "${YELLOW}   Database: ${DB_NAME} | User: ${DB_USER}${NC}"
if [ "$DB_PASS" = "wppass" ] && [ "$DB_USER" = "wpuser" ] && [ "$DB_NAME" = "wordpress_test" ]; then
    echo -e "${YELLOW}   ⚠️  Default credentials detected - NEVER use these in production!${NC}"
fi
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Error handler: print failing command and line number
trap 'error_handler $? $LINENO "$BASH_COMMAND"' ERR

error_handler() {
    local exit_code=$1
    local line_no=$2
    local command=$3

    if [ "$DEBUG" = "1" ]; then
        echo -e "${RED}❌ Error on line $line_no (exit code $exit_code):${NC}" >&2
        echo -e "${RED}   Command: $command${NC}" >&2
    else
        echo -e "${RED}❌ Error on line $line_no. Run with DEBUG=1 for details.${NC}" >&2
    fi

    exit $exit_code
}

# Helper function to conditionally redirect output based on DEBUG flag
# Usage: redirect_output "command args..."
redirect_output() {
    if [ "$DEBUG" = "1" ]; then
        "$@"
    else
        "$@" > /dev/null 2>&1
    fi
}

# Helper function to check if a command succeeded and exit with clear error if not
# Usage: check_command "command args..." "Error message"
# Note: For complex commands with heredocs, pass the command as a function or use a different approach
check_command() {
    local error_msg="$1"
    shift

    if [ "$DEBUG" = "1" ]; then
        if ! "$@"; then
            echo -e "${RED}❌ $error_msg${NC}" >&2
            exit 1
        fi
    else
        if ! "$@" > /dev/null 2>&1; then
            echo -e "${RED}❌ $error_msg${NC}" >&2
            echo -e "${YELLOW}   Run with DEBUG=1 for detailed output.${NC}" >&2
            exit 1
        fi
    fi
}

# Step 1: Install dependencies
echo -e "${YELLOW}📦 Installing dependencies...${NC}"
if [ "$DEBUG" = "1" ]; then
    sudo DEBIAN_FRONTEND=noninteractive apt-get update
else
    sudo DEBIAN_FRONTEND=noninteractive apt-get update -qq
fi

check_command \
    "Failed to install required packages (mariadb-server, mariadb-client, php8.4-mysql, php8.4-mysqli)" \
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client php8.4-mysql php8.4-mysqli

redirect_output sudo phpenmod mysqli pdo_mysql

# Step 2: Start MariaDB
echo -e "${YELLOW}🗄️  Starting MariaDB...${NC}"

# Function to check if MariaDB is ready
check_mariadb_ready() {
    # Try mysqladmin ping first (most reliable)
    if command -v mysqladmin > /dev/null 2>&1; then
        if sudo mysqladmin ping > /dev/null 2>&1; then
            return 0
        fi
    fi

    # Fallback: check for socket file
    local socket_file="/var/run/mysqld/mysqld.sock"
    if [ -S "$socket_file" ]; then
        # Socket exists, try a simple connection test
        if sudo mysql -e "SELECT 1" > /dev/null 2>&1; then
            return 0
        fi
    fi

    return 1
}

# Try to start MariaDB using systemctl first (preferred method)
if command -v systemctl > /dev/null 2>&1 && systemctl list-unit-files | grep -qE 'mariadb|mysqld'; then
    # Determine service name
    if systemctl list-unit-files | grep -q '^mariadb.service'; then
        SERVICE_NAME="mariadb"
    elif systemctl list-unit-files | grep -q '^mysqld.service'; then
        SERVICE_NAME="mysqld"
    else
        SERVICE_NAME="mariadb"
    fi

    if [ "$DEBUG" = "1" ]; then
        echo "Starting MariaDB via systemctl ($SERVICE_NAME)..." >&2
        sudo systemctl start "$SERVICE_NAME"
    else
        sudo systemctl start "$SERVICE_NAME" > /dev/null 2>&1
    fi

    # Wait for service to be active
    if [ "$DEBUG" = "1" ]; then
        sudo systemctl status "$SERVICE_NAME" --no-pager -l || true
    fi
else
    # Fallback: start mysqld_safe directly (without silencing output in debug mode)
    if [ "$DEBUG" = "1" ]; then
        echo "Starting MariaDB via mysqld_safe..." >&2
        sudo mysqld_safe --user=mysql --datadir=/var/lib/mysql \
          --pid-file=/var/run/mysqld/mysqld.pid \
          --socket=/var/run/mysqld/mysqld.sock --port=3306 &
    else
        sudo mysqld_safe --user=mysql --datadir=/var/lib/mysql \
          --pid-file=/var/run/mysqld/mysqld.pid \
          --socket=/var/run/mysqld/mysqld.sock --port=3306 > /dev/null 2>&1 &
    fi
fi

# Poll for MariaDB readiness with timeout
MAX_RETRIES=30
RETRY_INTERVAL=1
TIMEOUT=$((MAX_RETRIES * RETRY_INTERVAL))
ELAPSED=0

echo -e "${YELLOW}   Waiting for MariaDB to be ready (timeout: ${TIMEOUT}s)...${NC}"

while [ $ELAPSED -lt $TIMEOUT ]; do
    if check_mariadb_ready; then
        echo -e "${GREEN}   ✓ MariaDB is ready${NC}"
        break
    fi

    sleep $RETRY_INTERVAL
    ELAPSED=$((ELAPSED + RETRY_INTERVAL))

    if [ "$DEBUG" = "1" ] && [ $((ELAPSED % 5)) -eq 0 ]; then
        echo "   Still waiting... (${ELAPSED}s elapsed)" >&2
    fi
done

# Final check: fail if MariaDB is not ready
if ! check_mariadb_ready; then
    echo -e "${RED}❌ Failed to start MariaDB - service did not become ready within ${TIMEOUT}s${NC}" >&2

    # Provide diagnostic information
    echo -e "${YELLOW}   Diagnostics:${NC}" >&2

    # Check if process is running
    if pgrep -x mysqld > /dev/null 2>&1; then
        echo "   - mysqld process is running" >&2
    else
        echo "   - mysqld process is NOT running" >&2
    fi

    # Check socket file
    if [ -S "/var/run/mysqld/mysqld.sock" ]; then
        echo "   - Socket file exists: /var/run/mysqld/mysqld.sock" >&2
    else
        echo "   - Socket file NOT found: /var/run/mysqld/mysqld.sock" >&2
    fi

    # Show error logs if available
    if [ "$DEBUG" = "1" ]; then
        echo "   - Checking error logs..." >&2
        if [ -f "/var/log/mysql/error.log" ]; then
            echo "   Last 20 lines of error log:" >&2
            sudo tail -n 20 /var/log/mysql/error.log 2>/dev/null | sed 's/^/     /' >&2
        elif [ -f "/var/log/mysqld.log" ]; then
            echo "   Last 20 lines of mysqld log:" >&2
            sudo tail -n 20 /var/log/mysqld.log 2>/dev/null | sed 's/^/     /' >&2
        else
            echo "   - No error log found" >&2
        fi

        # Check systemctl status if available
        if command -v systemctl > /dev/null 2>&1; then
            if systemctl list-unit-files | grep -qE 'mariadb|mysqld'; then
                echo "   - Systemd service status:" >&2
                sudo systemctl status mariadb mysqld 2>/dev/null | head -n 10 | sed 's/^/     /' >&2 || true
            fi
        fi
    else
        echo -e "${YELLOW}   Run with DEBUG=1 for detailed diagnostics.${NC}" >&2
    fi

    exit 1
fi

# Step 3: Create database
echo -e "${YELLOW}📊 Creating database...${NC}"
echo -e "${YELLOW}   Database: ${DB_NAME} | User: ${DB_USER}${NC}"

# Use a here-document with variable substitution for database creation
if [ "$DEBUG" = "1" ]; then
    if ! sudo mysql -u root <<MYSQL_EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
MYSQL_EOF
    then
        echo -e "${RED}❌ Failed to create database or user${NC}" >&2
        exit 1
    fi
else
    if ! sudo mysql -u root <<MYSQL_EOF > /dev/null 2>&1
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
MYSQL_EOF
    then
        echo -e "${RED}❌ Failed to create database or user${NC}" >&2
        echo -e "${YELLOW}   Run with DEBUG=1 for detailed output.${NC}" >&2
        exit 1
    fi
fi

# Step 4: Download WordPress
echo -e "${YELLOW}📥 Downloading WordPress...${NC}"
if [ ! -d "wordpress" ]; then
    mkdir -p wordpress
    cd wordpress
    check_command \
        "Failed to download WordPress core" \
        wp core download --force --allow-root
else
    cd wordpress
fi

# Step 5: Configure WordPress
echo -e "${YELLOW}⚙️  Configuring WordPress...${NC}"
if [ ! -f "wp-config.php" ]; then
    cp wp-config-sample.php wp-config.php
    # Escape special characters in password for sed
    DB_PASS_ESCAPED=$(echo "$DB_PASS" | sed 's/[[\.*^$()+?{|]/\\&/g')
    sed -i "s/database_name_here/${DB_NAME}/" wp-config.php
    sed -i "s/username_here/${DB_USER}/" wp-config.php
    sed -i "s/password_here/${DB_PASS_ESCAPED}/" wp-config.php
fi

# Step 6: Install WordPress
echo -e "${YELLOW}🚀 Installing WordPress...${NC}"
if ! redirect_output wp core is-installed --allow-root; then
    check_command \
        "Failed to install WordPress core" \
        wp core install --url=http://localhost --title='Test Site' --admin_user=admin --admin_password=admin --admin_email=admin@example.com --allow-root
fi

# Step 7: Install Plugin Check
echo -e "${YELLOW}🔍 Installing Plugin Check...${NC}"
if [ ! -d "wp-content/plugins/plugin-check" ]; then
    check_command \
        "Failed to clone plugin-check repository" \
        git clone https://github.com/WordPress/plugin-check.git wp-content/plugins/plugin-check
    cd wp-content/plugins/plugin-check
    check_command \
        "Failed to install plugin-check dependencies" \
        composer install --no-interaction --no-progress
    cd ../../..
fi

# Activate plugin-check (non-critical, so we don't fail if it doesn't activate)
if ! redirect_output wp plugin activate plugin-check --allow-root; then
    if [ "$DEBUG" = "1" ]; then
        echo -e "${YELLOW}⚠️  Warning: Failed to activate plugin-check plugin${NC}" >&2
    fi
fi

# Step 8: Copy plugin
echo -e "${YELLOW}📋 Copying plugin...${NC}"
# Return to project root to ensure rsync operates from the correct directory
cd "$PROJECT_ROOT"
mkdir -p wordpress/wp-content/plugins/obfuscated-malware-scanner
check_command \
    "Failed to copy plugin files" \
    rsync -av --exclude='wordpress' --exclude='vendor' --exclude='.git' --exclude='node_modules' . wordpress/wp-content/plugins/obfuscated-malware-scanner/

echo -e "${GREEN}✅ WordPress environment setup complete!${NC}"
echo ""
# Ensure we're in project root for final output
cd "$PROJECT_ROOT"
echo "WordPress Location: $PROJECT_ROOT/wordpress"
echo "Database: ${DB_NAME} (${DB_USER}/${DB_PASS})"
echo "Admin: admin/admin"
echo ""
if [ "$DB_PASS" = "wppass" ] && [ "$DB_USER" = "wpuser" ] && [ "$DB_NAME" = "wordpress_test" ]; then
    echo -e "${YELLOW}⚠️  REMINDER: Default credentials used - for LOCAL DEVELOPMENT ONLY!${NC}"
    echo ""
fi
echo "Run plugin check with:"
echo "  wp plugin check obfuscated-malware-scanner --path=wordpress --allow-root"
echo ""
echo "To use custom credentials, set environment variables:"
echo "  DB_NAME=your_db_name DB_USER=your_user DB_PASS=your_pass ./setup-wordpress.sh"
