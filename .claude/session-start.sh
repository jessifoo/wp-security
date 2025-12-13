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

    # Check if vendor directory exists and is recent
    if [ -d "vendor" ] && [ -f "composer.lock" ]; then
        # Only update if composer.json is newer than vendor
        if [ "composer.json" -nt "vendor" ]; then
            echo "   composer.json updated, running composer install..."
            composer install --no-interaction --no-progress --prefer-dist --quiet
        else
            echo "   Dependencies already installed, skipping..."
        fi
    else
        # Fresh install
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
