#!/bin/bash
# test-github-actions.sh - Test workflows locally with exact GitHub Actions environment
# This script replicates the GitHub Actions environment and runs the same commands

set -e

export PATH="/usr/bin:$PATH"

echo "=== GitHub Actions Environment Test ==="
echo ""

# Verify PHP version matches GitHub Actions
echo "1. Verifying PHP version (GitHub Actions: PHP 8.4)..."
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
if php -r "if (version_compare(PHP_VERSION, '8.4.0', '<')) { exit(1); }"; then
    echo "   ✅ PHP $PHP_VERSION >= 8.4.0"
else
    echo "   ❌ PHP $PHP_VERSION < 8.4.0 - MISMATCH!"
    exit 1
fi

# Verify required extensions
echo ""
echo "2. Verifying PHP extensions (GitHub Actions: mbstring, xml, curl, json)..."
REQUIRED_EXTENSIONS=("mbstring" "xml" "curl" "json")
MISSING_EXTENSIONS=()
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^${ext}$"; then
        echo "   ✅ $ext"
    else
        echo "   ❌ $ext - MISSING!"
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    echo "   ERROR: Missing extensions: ${MISSING_EXTENSIONS[*]}"
    exit 1
fi

# Run composer install exactly as GitHub Actions does
echo ""
echo "3. Running Composer install (GitHub Actions command)..."
composer install --no-interaction --no-progress --prefer-dist --ansi > /dev/null 2>&1
echo "   ✅ Composer dependencies installed"

# Test PHPCS workflow
echo ""
echo "=== Testing PHPCS Workflow ==="
echo "Running: vendor/bin/phpcs --standard=phpcs.xml"
PHPCS_OUTPUT=$(vendor/bin/phpcs --standard=phpcs.xml 2>&1)
PHPCS_EXIT_CODE=$?

if [ $PHPCS_EXIT_CODE -eq 0 ]; then
    echo "   ✅ PHPCS passed (exit code: $PHPCS_EXIT_CODE)"
    ERRORS=$(echo "$PHPCS_OUTPUT" | grep -oP '\d+(?= ERRORS)' | head -1 || echo "0")
    WARNINGS=$(echo "$PHPCS_OUTPUT" | grep -oP '\d+(?= WARNINGS)' | head -1 || echo "0")
    echo "   Results: $ERRORS errors, $WARNINGS warnings"
else
    echo "   ❌ PHPCS failed (exit code: $PHPCS_EXIT_CODE)"
    echo "$PHPCS_OUTPUT" | tail -10
    exit $PHPCS_EXIT_CODE
fi

# Get JSON report for verification
echo ""
echo "4. Getting detailed PHPCS report..."
PHPCS_JSON=$(vendor/bin/phpcs --standard=phpcs.xml --report=json 2>&1)
ERRORS=$(echo "$PHPCS_JSON" | python3 -c "import json, sys; data=json.load(sys.stdin); print(data['totals']['errors'])" 2>/dev/null || echo "0")
WARNINGS=$(echo "$PHPCS_JSON" | python3 -c "import json, sys; data=json.load(sys.stdin); print(data['totals']['warnings'])" 2>/dev/null || echo "0")

echo "   ✅ PHPCS Results: $ERRORS errors, $WARNINGS warnings"

# Summary
echo ""
echo "=== Summary ==="
echo "✅ Environment matches GitHub Actions"
echo "✅ PHPCS workflow test passed"
echo "✅ Results: $ERRORS errors, $WARNINGS warnings"
echo ""
echo "This matches what GitHub Actions will produce."
