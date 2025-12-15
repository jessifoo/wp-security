#!/bin/bash
# SessionStart hook for Cursor
# This runs automatically when a new Cursor session starts

set -e

echo "🔧 Initializing WordPress Plugin Environment..."

# Get project root directory
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

# Run setup-environment.sh to ensure dependencies are installed
if [ -f "setup-environment.sh" ]; then
    echo "📦 Running environment setup..."
    bash setup-environment.sh
else
    echo "⚠️  setup-environment.sh not found, skipping environment setup"
fi

echo ""
echo "✅ Cursor session initialized!"
echo ""
echo "Available tools:"
echo "  - PHP: $(php --version 2>/dev/null | head -1 || echo 'Not available')"
echo "  - Composer: $(composer --version 2>/dev/null | head -1 || echo 'Not available')"
if [ -d "vendor/bin" ]; then
    echo "  - PHPCS: $(vendor/bin/phpcs --version 2>/dev/null || echo 'Not installed')"
    echo "  - PHPUnit: $(vendor/bin/phpunit --version 2>/dev/null || echo 'Not installed')"
fi
echo ""

