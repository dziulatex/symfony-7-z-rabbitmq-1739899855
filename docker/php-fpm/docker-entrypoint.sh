#!/bin/sh
set -e

# Check if the database schema exists
if ! php bin/console doctrine:schema:validate > /dev/null 2>&1; then
  echo "Database schema does not exist. Creating schema, running migrations, and loading fixtures..."
  php bin/console doctrine:database:create --if-not-exists
  php bin/console doctrine:migrations:migrate --no-interaction
  php bin/console doctrine:fixtures:load --no-interaction --group=dev
else
  echo "Database schema already exists. Skipping schema creation, migrations, and fixtures."
fi
composer install
# Run the main container command
exec docker-php-entrypoint "$@"