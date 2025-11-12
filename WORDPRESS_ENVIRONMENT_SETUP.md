# WordPress Environment Setup Guide

This guide explains how to set up a local WordPress environment for plugin development using the `setup-wordpress.sh` script.

## Important: WordPress Core Not Included

**WordPress core files are NOT included in this repository.** They are downloaded dynamically:
- **Local development**: The `setup-wordpress.sh` script downloads WordPress via WP-CLI (`wp core download`)
- **CI/CD**: GitHub Actions workflows use `WordPress/plugin-check-action` which handles WordPress installation automatically
- **Never commit**: The `/wordpress/` directory is in `.gitignore` and should never be committed

This ensures the repository stays lightweight and WordPress core is always up-to-date.

## Quick Start

```bash
./setup-wordpress.sh
```

## Environment Variables

The script supports the following environment variables to customize database credentials:

### Database Configuration

- **`DB_NAME`** (default: `wordpress_test`)
  - Database name for WordPress installation
  - Example: `DB_NAME=my_wp_db ./setup-wordpress.sh`

- **`DB_USER`** (default: `wpuser`)
  - Database username
  - Example: `DB_USER=my_wp_user ./setup-wordpress.sh`

- **`DB_PASS`** (default: `wppass`)
  - Database password
  - Example: `DB_PASS=secure_password123 ./setup-wordpress.sh`

### Debug Mode

- **`DEBUG`** (default: `0`)
  - Set to `1` for verbose output and detailed error messages
  - Example: `DEBUG=1 ./setup-wordpress.sh`

## Usage Examples

### Basic Setup (Default Credentials)
```bash
./setup-wordpress.sh
```

### Custom Database Credentials
```bash
DB_NAME=my_plugin_dev DB_USER=dev_user DB_PASS=secure_pass123 ./setup-wordpress.sh
```

### Debug Mode with Custom Credentials
```bash
DEBUG=1 DB_NAME=test_db DB_USER=test_user DB_PASS=test_pass ./setup-wordpress.sh
```

## ⚠️ SECURITY WARNING

**CRITICAL: The default database credentials (`wordpress_test`/`wpuser`/`wppass`) are for LOCAL DEVELOPMENT ONLY.**

### Never Use Default Credentials In:
- ❌ Production environments
- ❌ Publicly accessible systems
- ❌ Shared development servers
- ❌ Docker containers exposed to the internet
- ❌ CI/CD pipelines with public artifacts
- ❌ Any system accessible from outside your local network

### Security Best Practices:

1. **Always override credentials** for non-local environments:
   ```bash
   DB_NAME=secure_db_name DB_USER=secure_user DB_PASS=$(openssl rand -base64 32) ./setup-wordpress.sh
   ```

2. **Use strong passwords** (minimum 16 characters, mix of letters, numbers, symbols)

3. **Never commit credentials** to version control:
   - Use `.env` files (add to `.gitignore`)
   - Use environment variables in CI/CD
   - Use secret management tools for production

4. **Restrict database access**:
   - Use `localhost` only (default)
   - Limit user privileges to only what's needed
   - Regularly rotate credentials

5. **Monitor database access**:
   - Review MySQL logs regularly
   - Set up alerts for suspicious activity
   - Use firewall rules to restrict access

## What the Script Does

1. **Installs Dependencies**
   - MariaDB server and client
   - PHP 8.1 MySQL extensions
   - Enables required PHP modules

2. **Starts MariaDB**
   - Prefers systemd (`systemctl`) if available
   - Falls back to `mysqld_safe` if needed
   - Waits for database to be ready (30s timeout)
   - Verifies startup with health checks

3. **Creates Database**
   - Creates database with UTF8MB4 charset
   - Creates database user with specified credentials
   - Grants necessary privileges

4. **Downloads WordPress**
   - Downloads latest WordPress core
   - Uses WP-CLI for installation

5. **Configures WordPress**
   - Creates `wp-config.php` with database credentials
   - Sets up WordPress configuration

6. **Installs WordPress**
   - Runs WordPress installation
   - Creates admin user (admin/admin)

7. **Installs Plugin Check**
   - Clones WordPress Plugin Check tool
   - Installs Composer dependencies

8. **Copies Plugin**
   - Copies plugin files to WordPress plugins directory
   - Excludes unnecessary files (vendor, .git, etc.)

## Troubleshooting

### Database Connection Issues

If you encounter database connection errors:

1. **Check MariaDB is running:**
   ```bash
   sudo systemctl status mariadb
   # or
   pgrep -x mysqld
   ```

2. **Verify credentials:**
   ```bash
   mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME}
   ```

3. **Check socket file:**
   ```bash
   ls -la /var/run/mysqld/mysqld.sock
   ```

4. **Run with DEBUG=1 for detailed output:**
   ```bash
   DEBUG=1 ./setup-wordpress.sh
   ```

### Permission Issues

If you encounter permission errors:

1. **Ensure script is executable:**
   ```bash
   chmod +x setup-wordpress.sh
   ```

2. **Check sudo access:**
   ```bash
   sudo -v
   ```

### WordPress Installation Issues

If WordPress installation fails:

1. **Check WP-CLI is installed:**
   ```bash
   wp --version
   ```

2. **Verify database credentials in wp-config.php:**
   ```bash
   grep -E "DB_NAME|DB_USER|DB_PASS" wordpress/wp-config.php
   ```

3. **Check WordPress directory permissions:**
   ```bash
   ls -la wordpress/
   ```

## Post-Setup

After successful setup:

1. **Access WordPress:**
   - URL: `http://localhost`
   - Admin: `admin` / `admin`

2. **Run Plugin Check:**
   ```bash
   wp plugin check obfuscated-malware-scanner --path=wordpress --allow-root
   ```

3. **Access Database:**
   ```bash
   mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME}
   ```

## Additional Resources

- [WordPress Plugin Check Documentation](https://github.com/WordPress/plugin-check)
- [WP-CLI Documentation](https://wp-cli.org/)
- [MariaDB Documentation](https://mariadb.com/kb/en/documentation/)

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Run with `DEBUG=1` for detailed error output
3. Review script error messages and diagnostics
