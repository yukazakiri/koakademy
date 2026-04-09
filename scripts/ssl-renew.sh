#!/bin/bash
#
# SSL Certificate renewal script
# Regenerates certificates for local development
#

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CERTS_DIR="$PROJECT_ROOT/storage/certs"

cd "$CERTS_DIR"

# Source environment
set -a
[ -f "$PROJECT_ROOT/.env" ] && . "$PROJECT_ROOT/.env"
set +a

# Get domains
PORTAL_HOST=${PORTAL_HOST:-portal.dccp.com}
ADMIN_HOST=${ADMIN_HOST:-admin.dccp.com}
BASE_DOMAIN=$(echo "$PORTAL_HOST" | sed 's/^[^.]*\.//')

CERT_FILE="${BASE_DOMAIN}.pem"
KEY_FILE="${BASE_DOMAIN}-key.pem"

CERT_DOMAINS=(
    "$PORTAL_HOST"
    "$ADMIN_HOST"
    "*.$BASE_DOMAIN"
    "localhost"
)

echo "Regenerating SSL certificates..."

rm -f "$CERT_FILE" "$KEY_FILE"

mkcert -key-file "$KEY_FILE" -cert-file "$CERT_FILE" "${CERT_DOMAINS[@]}"

if [ -f "$CERT_FILE" ] && [ -f "$KEY_FILE" ]; then
    echo "SSL certificates regenerated successfully!"
    
    # Reload nginx
    echo "Reloading Nginx..."
    sudo systemctl reload nginx 2>/dev/null || sudo nginx -s reload 2>/dev/null || echo "Could not reload nginx"
else
    echo "Failed to regenerate SSL certificates"
    exit 1
fi
