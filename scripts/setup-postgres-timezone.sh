#!/bin/bash
#
# PostgreSQL Timezone Setup Script for DCCP Admin
# 
# This script sets the PostgreSQL timezone to Asia/Manila to match the Laravel app timezone.
# Run this on your production server to ensure consistent timestamp handling.
#
# Usage:
#   ./scripts/setup-postgres-timezone.sh
#
# For Docker/Sail:
#   vendor/bin/sail exec pgsql bash -c "psql -U \$POSTGRES_USER -d \$POSTGRES_DB -c \"ALTER DATABASE \$POSTGRES_DB SET timezone TO 'Asia/Manila';\""
#
# Requirements:
#   - PostgreSQL superuser access or database owner privileges
#   - PGPASSWORD environment variable or .pgpass file for authentication
#

set -e

# Configuration - Override these with environment variables if needed
TIMEZONE="${DB_TIMEZONE:-Asia/Manila}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-dccp_admin2}"
DB_USER="${DB_USERNAME:-postgres}"

echo "=================================================="
echo "PostgreSQL Timezone Setup Script"
echo "=================================================="
echo ""
echo "Configuration:"
echo "  Timezone: $TIMEZONE"
echo "  Host:     $DB_HOST"
echo "  Port:     $DB_PORT"
echo "  Database: $DB_NAME"
echo "  User:     $DB_USER"
echo ""

# Check if psql is available
if ! command -v psql &> /dev/null; then
    echo "ERROR: psql command not found. Please install PostgreSQL client tools."
    exit 1
fi

# Check current timezone
echo "Checking current PostgreSQL timezone..."
CURRENT_TZ=$(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SHOW timezone;" 2>/dev/null | tr -d ' ')

if [ -z "$CURRENT_TZ" ]; then
    echo "ERROR: Could not connect to PostgreSQL. Check your credentials and connection."
    echo ""
    echo "Make sure PGPASSWORD is set or you have a .pgpass file configured."
    echo "Example: export PGPASSWORD='your_password'"
    exit 1
fi

echo "Current timezone: $CURRENT_TZ"

if [ "$CURRENT_TZ" = "$TIMEZONE" ]; then
    echo ""
    echo "✓ PostgreSQL timezone is already set to $TIMEZONE"
    exit 0
fi

echo ""
echo "Setting PostgreSQL timezone to $TIMEZONE..."

# Set timezone for the database (persistent across restarts)
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "ALTER DATABASE \"$DB_NAME\" SET timezone TO '$TIMEZONE';"

# Set timezone for the current session (immediate effect)
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SET timezone TO '$TIMEZONE';"

# Verify the change
echo ""
echo "Verifying timezone change..."
NEW_TZ=$(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SHOW timezone;" | tr -d ' ')
echo "New timezone: $NEW_TZ"

if [ "$NEW_TZ" = "$TIMEZONE" ]; then
    echo ""
    echo "=================================================="
    echo "✓ SUCCESS: PostgreSQL timezone set to $TIMEZONE"
    echo "=================================================="
    echo ""
    echo "IMPORTANT: The database-level timezone change requires"
    echo "new connections to take effect. Please:"
    echo ""
    echo "  1. Restart your application/web server"
    echo "  2. Restart queue workers: php artisan queue:restart"
    echo "  3. Clear config cache: php artisan config:clear"
    echo ""
else
    echo ""
    echo "WARNING: Timezone verification failed."
    echo "Expected: $TIMEZONE"
    echo "Got:      $NEW_TZ"
    exit 1
fi
