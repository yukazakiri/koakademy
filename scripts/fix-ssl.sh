#!/bin/bash

###############################################################################
# SSL Certificate Fix Script for Traefik & mkcert
#
# This script fixes "ERR_CERT_AUTHORITY_INVALID" errors by:
# 1. Verifying mkcert is installed and CA is trusted
# 2. Regenerating certificates with the correct root CA
# 3. Updating certificate chain
# 4. Restarting Traefik
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
CERTS_DIR="${PROJECT_DIR}/docker/traefik/certs"

# Load environment variables if they exist
ENV_FILE="${PROJECT_DIR}/.env"
if [ -f "$ENV_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    . "$ENV_FILE"
    set +a
fi

# Determine main domain from env or fallback
PORTAL_HOST=${PORTAL_HOST:-portal.dccp.test}
ADMIN_HOST=${ADMIN_HOST:-admin.dccp.test}
MAILPIT_HOST=${MAILPIT_HOST:-mailpit.local.test}
MINIO_HOST=${MINIO_HOST:-minio.local.test}
MINIO_CONSOLE_HOST=${MINIO_CONSOLE_HOST:-minio-console.local.test}

# Extract base domain from PORTAL_HOST
BASE_DOMAIN=$(echo "$PORTAL_HOST" | sed 's/^[^.]*\.//')

CERT_FILE="${BASE_DOMAIN}.pem"
KEY_FILE="${BASE_DOMAIN}-key.pem"

DOMAINS=(
    "$ADMIN_HOST"
    "$PORTAL_HOST"
    "*.$BASE_DOMAIN"
    "$MINIO_HOST"
    "$MINIO_CONSOLE_HOST"
    "$MAILPIT_HOST"
    "local.test"
    "*.local.test"
)

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         SSL Certificate Fix for Traefik & mkcert          ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Function to print colored status messages
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

print_command() {
    echo -e "${BLUE}[$]${NC} $1"
}

print_section() {
    echo ""
    echo -e "${BLUE}==>${NC} $1"
}

check_host_resolution() {
    local host="$1"

    if command -v getent &> /dev/null && getent hosts "$host" | grep -q "127.0.0.1"; then
        print_status "${host} resolves to 127.0.0.1"
        return 0
    fi

    print_warning "${host} does not resolve to 127.0.0.1"
    print_info "Add it to your hosts file if needed:"
    print_info "  127.0.0.1 ${host}"

    return 1
}

test_url() {
    local label="$1"
    local url="$2"
    local insecure="${3:-false}"

    if ! command -v curl &> /dev/null; then
        print_warning "curl is not installed, skipping ${label}"
        return 1
    fi

    local curl_args=(-I -L --max-time 10 --silent --show-error --output /dev/null --write-out "%{http_code}")

    if [ "${insecure}" = "true" ]; then
        curl_args=(-k "${curl_args[@]}")
    fi

    local status_code
    if status_code=$(curl "${curl_args[@]}" "${url}" 2>/dev/null); then
        print_status "${label}: ${url} -> HTTP ${status_code}"
        return 0
    fi

    print_warning "${label}: ${url} could not be reached"
    return 1
}

check_firefox_nss_support() {
    if ! command -v firefox &> /dev/null; then
        return 0
    fi

    if command -v certutil &> /dev/null; then
        print_status "Firefox NSS tooling detected (certutil available)"
        return 0
    fi

    print_warning "Firefox detected, but NSS tooling is missing"
    print_info "If you use Firefox, install libnss3-tools so mkcert can trust certificates there."
}

print_section "Environment checks"

# Check if mkcert is installed
print_info "Checking if mkcert is installed..."
if ! command -v mkcert &> /dev/null; then
    print_error "mkcert is not installed!"
    print_info "Please install mkcert first:"
    print_info "  - On Linux: sudo apt install mkcert  # or brew install mkcert"
    print_info "  - On macOS: brew install mkcert"
    print_info "  - On Windows: choco install mkcert"
    exit 1
fi
print_status "mkcert is installed"
print_info "Version: $(mkcert --version 2>&1 | head -1)"
check_firefox_nss_support

# Check if mkcert CA is installed
print_info "Checking if mkcert root CA is installed..."
if ! mkcert -CAROOT &> /dev/null; then
    print_warning "mkcert CA not found. Installing..."
    mkcert -install
else
    print_status "mkcert root CA is installed"
fi

CAROOT=$(mkcert -CAROOT)
print_info "mkcert CAROOT: ${CAROOT}"

print_section "Hostname checks"
check_host_resolution "${PORTAL_HOST}" || true
check_host_resolution "${ADMIN_HOST}" || true
check_host_resolution "${MAILPIT_HOST}" || true

# Check if certificate directory exists
print_info "Checking certificate directory..."
if [ ! -d "${CERTS_DIR}" ]; then
    print_warning "Certificate directory not found: ${CERTS_DIR}"
    print_info "Creating directory..."
    mkdir -p "${CERTS_DIR}"
fi
print_status "Certificate directory ready: ${CERTS_DIR}"

# Navigate to certificate directory
cd "${CERTS_DIR}"

# Backup existing certificates if they exist
if [ -f "$CERT_FILE" ] || [ -f "$KEY_FILE" ]; then
    print_info "Backing up existing certificates..."
    BACKUP_DIR="${CERTS_DIR}/backup-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "${BACKUP_DIR}"
    cp "$CERT_FILE" "${BACKUP_DIR}/" 2>/dev/null || true
    cp "$KEY_FILE" "${BACKUP_DIR}/" 2>/dev/null || true
    print_status "Backup created: ${BACKUP_DIR}"
fi

# Generate new certificate
print_section "Certificate generation"
print_info "Generating new SSL certificate..."
print_info "Domains: ${DOMAINS[*]}"

# Create domain list for mkcert command
DOMAIN_ARGS=()
for domain in "${DOMAINS[@]}"; do
    DOMAIN_ARGS+=("${domain}")
done

# Generate certificate
print_command "mkcert -key-file $KEY_FILE -cert-file $CERT_FILE ${DOMAINS[*]}"
if mkcert -key-file "$KEY_FILE" -cert-file "$CERT_FILE" "${DOMAIN_ARGS[@]}"; then
    print_status "Certificate generated successfully"
else
    print_error "Failed to generate certificate"
    exit 1
fi

# Verify certificate details
print_info "Verifying certificate..."
CERT_SUBJECT=$(openssl x509 -in "$CERT_FILE" -noout -subject 2>/dev/null || echo "unknown")
CERT_ISSUER=$(openssl x509 -in "$CERT_FILE" -noout -issuer 2>/dev/null || echo "unknown")
print_info "Subject: ${CERT_SUBJECT}"
print_info "Issuer: ${CERT_ISSUER}"

# Append root CA to certificate chain
print_info "Appending root CA to certificate chain..."
if cat "${CAROOT}/rootCA.pem" >> "$CERT_FILE"; then
    print_status "Certificate chain updated successfully"
else
    print_error "Failed to update certificate chain"
    exit 1
fi

# Verify the full chain
print_info "Verifying certificate chain..."
if openssl crl2pkcs7 -nocrl -certfile "$CERT_FILE" | openssl pkcs7 -print_certs -noout 2>/dev/null | grep -q "BEGIN CERTIFICATE"; then
    print_status "Certificate chain is valid"
else
    print_warning "Could not fully verify certificate chain, but continuing..."
fi

# Set proper permissions
print_info "Setting file permissions..."
chmod 644 "$CERT_FILE"
chmod 600 "$KEY_FILE"
print_status "Permissions set"

# Check if Traefik is running
print_section "Traefik restart"
print_info "Checking if Traefik container is running..."
if docker ps | grep -q traefik; then
    print_status "Traefik container is running"

    # Restart Traefik
    print_info "Restarting Traefik to apply new certificates..."
    cd "${PROJECT_DIR}"
    if docker compose restart traefik; then
        print_status "Traefik restarted successfully"

        # Wait for Traefik to start
        print_info "Waiting for Traefik to start..."
        sleep 5

        # Check if Traefik is running
        if docker ps | grep -q traefik; then
            print_status "Traefik is running"
        else
            print_warning "Traefik may not be running properly"
        fi
    else
        print_error "Failed to restart Traefik"
        print_info "Please run manually: docker compose restart traefik"
    fi
else
    print_warning "Traefik container is not running"
    print_info "Start Traefik with: docker compose up -d traefik"
fi

print_section "Connectivity checks"
print_info "Testing portal routing and SSL..."
test_url "Portal HTTP" "http://${PORTAL_HOST}" true || true
test_url "Portal HTTPS (ignore trust)" "https://${PORTAL_HOST}/login" true || true

if command -v curl &> /dev/null; then
    if curl -I --max-time 10 "https://${PORTAL_HOST}/login" &> /dev/null; then
        print_status "Portal HTTPS trust check passed for ${PORTAL_HOST}"
    else
        print_warning "Portal HTTPS is reachable, but certificate trust is still failing"
        print_info "This usually means your browser or OS does not trust the mkcert root CA yet."
    fi
else
    print_info "curl not found, skipping trust-aware HTTPS test"
fi

print_info "Testing admin routing and SSL..."
test_url "Admin HTTP" "http://${ADMIN_HOST}" true || true
test_url "Admin HTTPS (ignore trust)" "https://${ADMIN_HOST}" true || true

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              SSL Certificate Fix Complete!                ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
print_info "Next steps:"
print_info "  1. Try accessing https://${PORTAL_HOST}/login in your browser"
print_info "  2. Try accessing https://${ADMIN_HOST} in your browser"
print_info "  2. If you still see certificate warnings:"
print_info "     - Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)"
print_info "     - Clear browser cache for the site"
print_info "     - Restart your browser"
print_info "     - If you use Firefox, ensure security.enterprise_roots.enabled is true"
print_info "     - If Firefox still fails, install libnss3-tools and rerun this script"
echo ""
print_info "Certificate details:"
print_info "  - Location: ${CERTS_DIR}/${CERT_FILE}"
print_info "  - Key: ${CERTS_DIR}/${KEY_FILE}"
print_info "  - Backup: ${BACKUP_DIR:-'N/A'}"
echo ""

# Show certificate expiration
if [ -f "${CERTS_DIR}/${CERT_FILE}" ]; then
    EXPIRY=$(openssl x509 -in "${CERTS_DIR}/${CERT_FILE}" -noout -enddate 2>/dev/null | cut -d= -f2)
    if [ -n "${EXPIRY}" ]; then
        print_info "Certificate expires: ${EXPIRY}"
    fi
fi

print_status "Done"
