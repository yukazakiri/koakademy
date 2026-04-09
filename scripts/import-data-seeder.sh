#!/bin/bash

# Script to import data-seeder.sql into the database
# Run with: vendor/bin/sail ./scripts/import-data-seeder.sh

# Load .env file
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Import the SQL file based on DB_CONNECTION
if [ "$DB_CONNECTION" = "pgsql" ]; then
    export PGPASSWORD="$DB_PASSWORD"

    # Set PG_RESTORE_CLEAN=true to drop and recreate objects from the dump.
    PG_RESTORE_CLEAN=${PG_RESTORE_CLEAN:-true}
    PG_RESTORE_ARGS="--no-owner --no-privileges"
    if [ "$PG_RESTORE_CLEAN" = "true" ]; then
        PG_RESTORE_ARGS="$PG_RESTORE_ARGS --clean --if-exists"
    fi

    pg_restore $PG_RESTORE_ARGS -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" database/seeders/data-seeder.sql
else
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < database/seeders/data-seeder.sql
fi

echo "Import completed."