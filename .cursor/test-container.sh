#!/bin/bash
# Container diagnostic script for Cursor IDE
# Tests container build, workspace mounting, and file access

set -e

LOG_FILE="/tmp/cursor-container-test.log"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CURSOR_DIR="$PROJECT_ROOT/.cursor"

echo "=== Cursor Container Diagnostic Test ===" | tee "$LOG_FILE"
echo "Timestamp: $(date)" | tee -a "$LOG_FILE"
echo "Project Root: $PROJECT_ROOT" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Test 1: Check Docker availability
echo "Test 1: Docker Availability" | tee -a "$LOG_FILE"
if command -v docker &> /dev/null; then
    DOCKER_VERSION=$(docker --version)
    echo "✓ Docker found: $DOCKER_VERSION" | tee -a "$LOG_FILE"
else
    echo "✗ Docker not found in PATH" | tee -a "$LOG_FILE"
    exit 1
fi

# Test 2: Check Docker daemon
echo "" | tee -a "$LOG_FILE"
echo "Test 2: Docker Daemon Status" | tee -a "$LOG_FILE"
if docker info &> /dev/null; then
    echo "✓ Docker daemon is running" | tee -a "$LOG_FILE"
else
    echo "✗ Docker daemon is not running or not accessible" | tee -a "$LOG_FILE"
    exit 1
fi

# Test 3: Check Dockerfile exists
echo "" | tee -a "$LOG_FILE"
echo "Test 3: Dockerfile Check" | tee -a "$LOG_FILE"
if [ -f "$CURSOR_DIR/Dockerfile" ]; then
    echo "✓ Dockerfile found at: $CURSOR_DIR/Dockerfile" | tee -a "$LOG_FILE"
    DOCKERFILE_LINES=$(wc -l < "$CURSOR_DIR/Dockerfile")
    echo "  Lines: $DOCKERFILE_LINES" | tee -a "$LOG_FILE"
else
    echo "✗ Dockerfile not found at: $CURSOR_DIR/Dockerfile" | tee -a "$LOG_FILE"
    exit 1
fi

# Test 4: Check environment.json
echo "" | tee -a "$LOG_FILE"
echo "Test 4: environment.json Check" | tee -a "$LOG_FILE"
if [ -f "$CURSOR_DIR/environment.json" ]; then
    echo "✓ environment.json found" | tee -a "$LOG_FILE"
    if command -v jq &> /dev/null; then
        WORKSPACE_FOLDER=$(jq -r '.workspaceFolder // "not set"' "$CURSOR_DIR/environment.json")
        echo "  workspaceFolder: $WORKSPACE_FOLDER" | tee -a "$LOG_FILE"
    else
        echo "  (jq not available, cannot parse JSON)" | tee -a "$LOG_FILE"
    fi
else
    echo "✗ environment.json not found" | tee -a "$LOG_FILE"
fi

# Test 5: Check hooks.json
echo "" | tee -a "$LOG_FILE"
echo "Test 5: hooks.json Check" | tee -a "$LOG_FILE"
if [ -f "$CURSOR_DIR/hooks.json" ]; then
    echo "✓ hooks.json found" | tee -a "$LOG_FILE"
    if command -v jq &> /dev/null; then
        HOOK_COMMAND=$(jq -r '.hooks.SessionStart[0].hooks[0].command // "not set"' "$CURSOR_DIR/hooks.json")
        echo "  SessionStart command: $HOOK_COMMAND" | tee -a "$LOG_FILE"
    fi
else
    echo "✗ hooks.json not found" | tee -a "$LOG_FILE"
fi

# Test 6: Check session-start.sh
echo "" | tee -a "$LOG_FILE"
echo "Test 6: session-start.sh Check" | tee -a "$LOG_FILE"
if [ -f "$CURSOR_DIR/session-start.sh" ]; then
    echo "✓ session-start.sh found" | tee -a "$LOG_FILE"
    if [ -x "$CURSOR_DIR/session-start.sh" ]; then
        echo "✓ session-start.sh is executable" | tee -a "$LOG_FILE"
    else
        echo "⚠ session-start.sh is not executable" | tee -a "$LOG_FILE"
    fi
else
    echo "✗ session-start.sh not found" | tee -a "$LOG_FILE"
fi

# Test 7: Try building the container
echo "" | tee -a "$LOG_FILE"
echo "Test 7: Container Build Test" | tee -a "$LOG_FILE"
cd "$CURSOR_DIR"
if docker build -t cursor-test-wp-security:test . 2>&1 | tee -a "$LOG_FILE"; then
    echo "✓ Container built successfully" | tee -a "$LOG_FILE"
    BUILD_SUCCESS=true
else
    echo "✗ Container build failed" | tee -a "$LOG_FILE"
    BUILD_SUCCESS=false
fi

# Test 8: Test container run and workspace access
if [ "$BUILD_SUCCESS" = true ]; then
    echo "" | tee -a "$LOG_FILE"
    echo "Test 8: Container Run Test" | tee -a "$LOG_FILE"
    
    # Test if container can access workspace
    CONTAINER_OUTPUT=$(docker run --rm -v "$PROJECT_ROOT:/workspace" cursor-test-wp-security:test bash -c "
        echo 'Container workspace test:'
        echo '  PWD: '\$(pwd)
        echo '  Workspace exists: '\$([ -d /workspace ] && echo 'yes' || echo 'no')
        echo '  .cursor dir exists: '\$([ -d /workspace/.cursor ] && echo 'yes' || echo 'no')
        echo '  hooks.json exists: '\$([ -f /workspace/.cursor/hooks.json ] && echo 'yes' || echo 'no')
        echo '  session-start.sh exists: '\$([ -f /workspace/.cursor/session-start.sh ] && echo 'yes' || echo 'no')
        ls -la /workspace/.cursor/ 2>&1 | head -10
    " 2>&1)
    
    echo "$CONTAINER_OUTPUT" | tee -a "$LOG_FILE"
    
    if echo "$CONTAINER_OUTPUT" | grep -q "hooks.json exists: yes"; then
        echo "✓ Container can access hooks.json" | tee -a "$LOG_FILE"
    else
        echo "✗ Container cannot access hooks.json" | tee -a "$LOG_FILE"
    fi
fi

# Test 9: Check for existing Cursor containers
echo "" | tee -a "$LOG_FILE"
echo "Test 9: Existing Cursor Containers" | tee -a "$LOG_FILE"
CURSOR_CONTAINERS=$(docker ps -a --filter "name=cursor" --format "{{.Names}}" 2>/dev/null || echo "")
if [ -n "$CURSOR_CONTAINERS" ]; then
    echo "Found Cursor-related containers:" | tee -a "$LOG_FILE"
    echo "$CURSOR_CONTAINERS" | tee -a "$LOG_FILE"
else
    echo "No Cursor-related containers found" | tee -a "$LOG_FILE"
fi

# Summary
echo "" | tee -a "$LOG_FILE"
echo "=== Test Summary ===" | tee -a "$LOG_FILE"
echo "Full log saved to: $LOG_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Cleanup test image
if [ "$BUILD_SUCCESS" = true ]; then
    docker rmi cursor-test-wp-security:test &> /dev/null || true
fi

echo "Diagnostic complete. Check $LOG_FILE for details."

