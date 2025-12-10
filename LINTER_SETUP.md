# Linter Setup for PHP Syntax Checking

This document describes the linters and checks configured to catch PHP syntax errors and other issues before code is pushed.

## Available Linters

### 1. PHP Syntax Check (`php -l`)
**What it catches:** PHP parse errors, syntax errors, unexpected tokens

**When it runs:**
- **Pre-commit:** Via GrumPHP git hook (runs automatically before `git commit`)
- **CI/CD:** In GitHub Actions workflows:
  - `.github/workflows/php-lint.yml` - Dedicated PHP syntax check workflow
  - `.github/workflows/phpcs.yml` - Runs syntax check before PHPCS

**How to run manually:**
```bash
# Check a specific file
php -l path/to/file.php

# Check all PHP files in project
find . -type f -name "*.php" \
  -not -path "./vendor/*" \
  -not -path "./wordpress/*" \
  -not -path "./tests/_output/*" \
  -exec php -l {} \;
```

### 2. GrumPHP (Pre-commit Hook)
**What it runs:**
- PHP syntax check (`phplint`)
- PHPCS (WordPress coding standards)

**Configuration:** `grumphp.yml`

**Setup:**
```bash
vendor/bin/grumphp git:init
```

**How to run manually:**
```bash
vendor/bin/grumphp run
```

### 3. PHPCS (PHP CodeSniffer)
**What it catches:** Coding standards violations, some syntax issues

**Configuration:** `phpcs.xml`

**How to run manually:**
```bash
vendor/bin/phpcs --standard=phpcs.xml
```

### 4. Psalm (Static Analysis)
**What it catches:** Type errors, undefined variables, potential bugs

**Configuration:** `psalm.xml`

**How to run manually:**
```bash
vendor/bin/psalm
```

### 5. PHPMD (PHP Mess Detector)
**What it catches:** Code complexity, unused code, potential bugs

**Configuration:** `phpmd.xml`

**How to run manually:**
```bash
vendor/bin/phpmd . text phpmd.xml
```

## CI/CD Workflows

All workflows run on:
- Push to `main` branch
- Pull requests to `main` branch
- Manual trigger (`workflow_dispatch`)

### Workflows that check syntax:

1. **`.github/workflows/php-lint.yml`**
   - Dedicated PHP syntax checking
   - Fastest way to catch parse errors

2. **`.github/workflows/phpcs.yml`**
   - Runs PHP syntax check first
   - Then runs PHPCS for coding standards

3. **`.github/workflows/psalm.yml`**
   - Static analysis (may catch some syntax issues)

## Pre-commit Protection

GrumPHP is configured to run automatically before commits. If syntax errors are found, the commit will be blocked.

To bypass (not recommended):
```bash
git commit --no-verify
```

## Common Syntax Errors Caught

- Duplicate function definitions
- Missing closing braces `}`
- Missing semicolons `;`
- Unexpected tokens
- Parse errors
- Invalid PHP syntax

## Example: The Error That Was Fixed

**Error:** `PHP Parse error: syntax error, unexpected token "public"`

**Cause:** Duplicate function definition - `check_uploaded_file()` was defined twice in the same class.

**How it was caught:** PHP syntax check (`php -l`) would have caught this immediately.

**Prevention:** 
- GrumPHP now runs `php -l` before every commit
- CI/CD workflows check syntax before running other checks

## Best Practices

1. **Always run syntax check before committing:**
   ```bash
   php -l includes/class-obfuscated-malware-scanner.php
   ```

2. **Let GrumPHP catch issues automatically:**
   - Don't bypass pre-commit hooks
   - Fix issues before committing

3. **Check CI/CD results:**
   - Review GitHub Actions workflow results
   - Fix any syntax errors before merging PRs

4. **Use your IDE:**
   - Most IDEs highlight syntax errors in real-time
   - Configure your IDE to run `php -l` on save

## Troubleshooting

**GrumPHP not running:**
```bash
vendor/bin/grumphp git:init
```

**Check if git hook exists:**
```bash
ls -la .git/hooks/pre-commit
```

**Test GrumPHP manually:**
```bash
vendor/bin/grumphp run
```

**Check specific file syntax:**
```bash
php -l path/to/file.php
```
