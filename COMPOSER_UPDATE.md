# Composer Lock Update Required

## Status

The `composer.json` requires PHP >= 8.3, but `composer.lock` was generated with PHP 8.2.

## To Regenerate composer.lock

You need to run `composer update` on a machine/container with PHP 8.3 installed.

### Option 1: Using Docker (Recommended)

```bash
# Build the Docker image (includes PHP 8.3)
docker build -t oms-php83 .

# Run composer update in the container
docker run --rm -v $(pwd):/workspace -w /workspace oms-php83 composer update --no-interaction --lock

# Verify it works
docker run --rm -v $(pwd):/workspace -w /workspace oms-php83 composer install --no-interaction
```

### Option 2: Using Local PHP 8.3

If you have PHP 8.3 installed locally:

```bash
# Verify PHP version
php -v  # Should show 8.3.x

# Regenerate composer.lock
composer update --no-interaction --lock

# Verify installation
composer install --no-interaction
```

### Option 3: Using the Script

```bash
./update-composer-lock.sh
```

## After Regenerating

1. Commit the updated `composer.lock`:
   ```bash
   git add composer.lock
   git commit -m "Update composer.lock for PHP 8.3"
   ```

2. Push to trigger CI:
   ```bash
   git push
   ```

## GitHub Actions

All GitHub Actions workflows have been updated to use PHP 8.3:
- ✅ `.github/workflows/phpcs.yml`
- ✅ `.github/workflows/psalm.yml`
- ✅ `.github/workflows/phpmd.yml`
- ✅ `.github/workflows/plugin-check.yml`

CI will now use PHP 8.3 and should pass once `composer.lock` is regenerated.
