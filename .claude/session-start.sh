#!/bin/bash
# SessionStart hook for Cloud Agent web sessions
# This runs automatically when a new web session starts

set -e

echo "🔧 Initializing WordPress Plugin Environment..."

# Check if running in Cloud Agent web environment
if [ -n "$CLAUDE_CODE_REMOTE" ]; then
    echo "📡 Detected Cloud Agent web session"
fi

# Verify Composer is available
if ! command -v composer &> /dev/null; then
    echo "❌ ERROR: Composer not found in PATH"
    echo "   Cloud Agent environment should have Composer pre-installed"
    exit 1
fi

echo "✅ Composer found: $(composer --version | head -1)"

# Install/update Composer dependencies if needed
if [ -f "composer.json" ]; then
    echo "📦 Installing Composer dependencies..."

    needs_update=false

    # Determine if we need to install/update dependencies
    if [ ! -d "vendor" ]; then
        # No vendor directory - fresh install needed
        needs_update=true
        echo "   No vendor directory found, performing fresh install..."
    elif [ -f "composer.lock" ]; then
        # Check if composer.json is newer than lock file
        if [ "composer.json" -nt "composer.lock" ]; then
            needs_update=true
            echo "   composer.json newer than lock file, updating..."
        # Check if lock file is newer than vendor directory
        elif [ "composer.lock" -nt "vendor" ]; then
            needs_update=true
            echo "   composer.lock newer than vendor, updating..."
        else
            echo "   Dependencies already up-to-date, skipping..."
        fi
    else
        # No lock file - need to install
        needs_update=true
        echo "   No composer.lock found, installing..."
    fi

    if [ "$needs_update" = true ]; then
        composer install --no-interaction --no-progress --prefer-dist --quiet
    fi

    echo "✅ Composer dependencies ready"
else
    echo "⚠️  No composer.json found, skipping dependency installation"
fi

# Persist environment variables for this session
if [ -n "$CLAUDE_ENV_FILE" ]; then
    echo "📝 Setting up environment variables..."
    cat >> "$CLAUDE_ENV_FILE" <<EOF
# WordPress Plugin Development Environment
export PATH="$PWD/vendor/bin:\$PATH"
export WP_TESTS_DIR="$PWD/tests"
export WP_PLUGIN_DIR="$PWD"
EOF
    echo "✅ Environment variables configured"
fi

echo ""
echo "✅ Environment initialized successfully!"
echo ""
echo "Available tools:"
echo "  - PHP: $(php --version 2>/dev/null | head -1 || echo 'Not available')"
echo "  - Composer: $(composer --version 2>/dev/null | head -1 || echo 'Not available')"
echo "  - PHPCS: $(vendor/bin/phpcs --version 2>/dev/null || echo 'Not installed')"
echo "  - PHPUnit: $(vendor/bin/phpunit --version 2>/dev/null || echo 'Not installed')"
echo ""
