#!/usr/bin/env bash
set -e  # Exit immediately if a command exits with a non-zero status

PHP_BIN="php8.4"
PHPSTAN_LEVEL=6  # Ensure this is a valid level

# Check if PHP binary exists
if ! command -v $PHP_BIN &> /dev/null; then
    echo "Error: $PHP_BIN not found. Please install or update the script."
    exit 1
fi

# Run static analysis
$PHP_BIN vendor/bin/phpstan analyze --level=$PHPSTAN_LEVEL
$PHP_BIN vendor/bin/phpcs
