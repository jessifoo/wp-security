#!/bin/bash
# setup-wordpress.sh - WordPress Environment Setup Script
# This script sets up a complete WordPress environment for plugin development

set -e

export PATH="/usr/bin:$PATH"

echo "🔧 Setting up WordPress Environment..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Install dependencies
echo -e "${YELLOW}📦 Installing dependencies...${NC}"
sudo DEBIAN_FRONTEND=noninteractive apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client php8.1-mysql php8.1-mysqli > /dev/null 2>&1
sudo phpenmod mysqli pdo_mysql > /dev/null 2>&1

# Step 2: Start MariaDB
echo -e "${YELLOW}🗄️  Starting MariaDB...${NC}"
sudo mysqld_safe --user=mysql --datadir=/var/lib/mysql \
  --pid-file=/var/run/mysqld/mysqld.pid \
  --socket=/var/run/mysqld/mysqld.sock --port=3306 > /dev/null 2>&1 &
sleep 3

# Step 3: Create database
echo -e "${YELLOW}📊 Creating database...${NC}"
sudo mysql -u root <<EOF > /dev/null 2>&1
CREATE DATABASE IF NOT EXISTS wordpress_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'wpuser'@'localhost' IDENTIFIED BY 'wppass';
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wpuser'@'localhost';
FLUSH PRIVILEGES;
EOF

# Step 4: Download WordPress
echo -e "${YELLOW}📥 Downloading WordPress...${NC}"
if [ ! -d "wordpress" ]; then
    mkdir -p wordpress
    cd wordpress
    wp core download --force --allow-root > /dev/null 2>&1
else
    cd wordpress
fi

# Step 5: Configure WordPress
echo -e "${YELLOW}⚙️  Configuring WordPress...${NC}"
if [ ! -f "wp-config.php" ]; then
    cp wp-config-sample.php wp-config.php
    sed -i "s/database_name_here/wordpress_test/" wp-config.php
    sed -i "s/username_here/wpuser/" wp-config.php
    sed -i "s/password_here/wppass/" wp-config.php
fi

# Step 6: Install WordPress
echo -e "${YELLOW}🚀 Installing WordPress...${NC}"
if ! wp core is-installed --allow-root > /dev/null 2>&1; then
    wp core install --url=http://localhost --title="Test Site" \
      --admin_user=admin --admin_password=admin \
      --admin_email=admin@example.com --allow-root > /dev/null 2>&1
fi

# Step 7: Install Plugin Check
echo -e "${YELLOW}🔍 Installing Plugin Check...${NC}"
if [ ! -d "wp-content/plugins/plugin-check" ]; then
    git clone https://github.com/WordPress/plugin-check.git wp-content/plugins/plugin-check > /dev/null 2>&1
    cd wp-content/plugins/plugin-check
    composer install --no-interaction --no-progress > /dev/null 2>&1
    cd ../../..
fi

# Activate plugin-check
wp plugin activate plugin-check --allow-root > /dev/null 2>&1 || true

# Step 8: Copy plugin
echo -e "${YELLOW}📋 Copying plugin...${NC}"
cd ../..
mkdir -p wordpress/wp-content/plugins/obfuscated-malware-scanner
rsync -av --exclude='wordpress' --exclude='vendor' \
  --exclude='.git' --exclude='node_modules' \
  . wordpress/wp-content/plugins/obfuscated-malware-scanner/ > /dev/null 2>&1

echo -e "${GREEN}✅ WordPress environment setup complete!${NC}"
echo ""
echo "WordPress Location: $(pwd)/wordpress"
echo "Database: wordpress_test (wpuser/wppass)"
echo "Admin: admin/admin"
echo ""
echo "Run plugin check with:"
echo "  wp plugin check obfuscated-malware-scanner --path=wordpress --allow-root"
