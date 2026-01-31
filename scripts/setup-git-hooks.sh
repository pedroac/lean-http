#!/bin/bash

# Setup script to install git hooks
# This makes the pre-commit hook executable

set -e

HOOKS_DIR=".git/hooks"
PRE_COMMIT_HOOK="$HOOKS_DIR/pre-commit"

if [ ! -d "$HOOKS_DIR" ]; then
    echo "Error: .git/hooks directory not found. Are you in a git repository?"
    exit 1
fi

if [ ! -f "$PRE_COMMIT_HOOK" ]; then
    echo "Error: pre-commit hook not found at $PRE_COMMIT_HOOK"
    exit 1
fi

chmod +x "$PRE_COMMIT_HOOK"
echo "✓ Pre-commit hook is now executable"
echo ""
echo "The pre-commit hook will now run before each commit to check:"
echo "  - Code style (PHP CS Fixer)"
echo "  - Static analysis (PHPStan)"
echo "  - Tests (PHPUnit)"
echo ""
echo "To skip the hook for a single commit, use: git commit --no-verify"
