#!/bin/bash

# Exit if any command fails
set -e

# Run DB migrations without prompt
php artisan migrate --force

# Start Laravel server
exec php artisan serve --host=0.0.0.0 --port=8181
