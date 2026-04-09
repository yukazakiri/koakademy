#!/usr/bin/env bash
#
# DCCP Local SSL Certificate Setup for Linux
#
# This script automates the process of:
# - Installing mkcert (via paru on Arch Linux, or other package managers)
# - Generating trusted SSL certificates
# - Adding local domains to /etc/hosts
# - Providing instructions for Firefox-based browsers
#
# Usage:
#   ./setup-ssl.sh              # Full setup
#   ./setup-ssl.sh --skip-ca    # Skip CA installation
#   ./setup-ssl.sh --skip-hosts # Skip hosts file modification
#   ./setup-ssl.sh --force      # Force regenerate certificates
#
# Requires: sudo privileges for hosts file and CA installation

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CERTS_DIR="$PROJECT_ROOT/docker/traefik/certs"
HOSTS_FILE="/etc/hosts"

# Load environment variables if they exist
ENV_FILE="$PROJECT_ROOT/.env"
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

# Extract base domain from PORTAL_HOST (e.g., dccp.test from portal.dccp.test)
# This assumes the structure [subdomain].[domain].[tld] or [subdomain].[tld]
BASE_DOMAIN=$(echo "$PORTAL_HOST" | sed 's/^[^.]*\.//')

CERT_FILE="${BASE_DOMAIN}.pem"
KEY_FILE="${BASE_DOMAIN}-key.pem"

# Domains for certificates (includes wildcards)
CERT_DOMAINS=(
    "$PORTAL_HOST"
    "$ADMIN_HOST"
    "*.$BASE_DOMAIN"
    "$MAILPIT_HOST"
    "$MINIO_HOST"
    "$MINIO_CONSOLE_HOST"
    "*.local.test"
)

# Domains for hosts file (no wildcards - must be explicit)
HOSTS_DOMAINS=(
    "$PORTAL_HOST"
    "$ADMIN_HOST"
    "$MAILPIT_HOST"
    "$MINIO_HOST"
    "$MINIO_CONSOLE_HOST"
)

# Flags
SKIP_CA=false
SKIP_HOSTS=false
FORCE=false

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BLUE='\033[0;34m'
GRAY='\033[0;90m'
NC='\033[0m' # No Color

# Output functions
success() { echo -e "${GREEN}[OK]${NC} $1"; }
info() { echo -e "${CYAN}[..]${NC} $1"; }
warn() { echo -e "${YELLOW}[!!]${NC} $1"; }
error() { echo -e "${RED}[XX]${NC} $1"; }

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-ca)
            SKIP_CA=true
            shift
            ;;
        --skip-hosts)
            SKIP_HOSTS=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-ca     Skip CA installation"
            echo "  --skip-hosts  Skip hosts file modification"
            echo "  --force       Force regenerate certificates"
            echo "  -h, --help    Show this help message"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Detect package manager and install command
detect_package_manager() {
    if command -v paru &> /dev/null; then
        echo "paru"
    elif command -v yay &> /dev/null; then
        echo "yay"
    elif command -v pacman &> /dev/null; then
        echo "pacman"
    elif command -v apt &> /dev/null; then
        echo "apt"
    elif command -v dnf &> /dev/null; then
        echo "dnf"
    elif command -v brew &> /dev/null; then
        echo "brew"
    else
        echo "unknown"
    fi
}

install_mkcert() {
    local pkg_manager
    pkg_manager=$(detect_package_manager)
    
    case $pkg_manager in
        paru)
            info "Installing mkcert via paru..."
            paru -S --noconfirm mkcert nss
            ;;
        yay)
            info "Installing mkcert via yay..."
            yay -S --noconfirm mkcert nss
            ;;
        pacman)
            info "Installing mkcert via pacman..."
            sudo pacman -S --noconfirm mkcert nss
            ;;
        apt)
            info "Installing mkcert via apt..."
            sudo apt update
            sudo apt install -y mkcert libnss3-tools
            ;;
        dnf)
            info "Installing mkcert via dnf..."
            sudo dnf install -y mkcert nss-tools
            ;;
        brew)
            info "Installing mkcert via brew..."
            brew install mkcert nss
            ;;
        *)
            error "Unknown package manager. Please install mkcert manually:"
            echo "  https://github.com/FiloSottile/mkcert#installation"
            exit 1
            ;;
    esac
}

# Banner
echo ""
echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}           DCCP Local Development Setup (Linux)               ${NC}"
echo -e "${BLUE}================================================================${NC}"
echo ""

# Step 1: Check/Install mkcert
info "Checking if mkcert is installed..."
if ! command -v mkcert &> /dev/null; then
    warn "mkcert is not installed!"
    echo ""
    read -p "Would you like to install it now? (Y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        error "mkcert is required. Please install it manually."
        exit 1
    fi
    install_mkcert
fi
MKCERT_PATH=$(command -v mkcert)
success "mkcert found at: $MKCERT_PATH"

# Step 2: Install the local CA
if [[ "$SKIP_CA" == false ]]; then
    info "Installing local Certificate Authority..."
    echo -e "${GRAY}  (This may require sudo privileges)${NC}"
    
    if mkcert -install 2>&1; then
        success "Local CA installed successfully"
    else
        warn "CA installation may have failed or already exists"
    fi
else
    info "Skipping CA installation (--skip-ca flag set)"
fi

# Step 3: Create certs directory
info "Ensuring certificates directory exists..."
if [[ ! -d "$CERTS_DIR" ]]; then
    mkdir -p "$CERTS_DIR"
    success "Created directory: $CERTS_DIR"
else
    success "Directory exists: $CERTS_DIR"
fi

# Step 4: Check for existing certificates
CERT_PATH="$CERTS_DIR/$CERT_FILE"
KEY_PATH="$CERTS_DIR/$KEY_FILE"

if [[ -f "$CERT_PATH" && -f "$KEY_PATH" && "$FORCE" == false ]]; then
    warn "Certificates already exist!"
    echo -e "${GRAY}  Certificate: $CERT_PATH${NC}"
    echo -e "${GRAY}  Key:         $KEY_PATH${NC}"
    echo ""
    read -p "Do you want to regenerate them? (y/N) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        info "Keeping existing certificates"
    else
        FORCE=true
    fi
fi

# Step 5: Generate certificates
if [[ "$FORCE" == true || ! -f "$CERT_PATH" || ! -f "$KEY_PATH" ]]; then
    info "Generating SSL certificates for domains:"
    for domain in "${CERT_DOMAINS[@]}"; do
        echo -e "${GRAY}  - $domain${NC}"
    done

    pushd "$CERTS_DIR" > /dev/null
    mkcert -key-file "$KEY_FILE" -cert-file "$CERT_FILE" "${CERT_DOMAINS[@]}"
    popd > /dev/null
    
    success "Certificates generated successfully!"
fi

# Step 6: Verify certificates
info "Verifying generated certificates..."
if [[ -f "$CERT_PATH" && -f "$KEY_PATH" ]]; then
    CERT_SIZE=$(stat -c%s "$CERT_PATH" 2>/dev/null || stat -f%z "$CERT_PATH" 2>/dev/null)
    KEY_SIZE=$(stat -c%s "$KEY_PATH" 2>/dev/null || stat -f%z "$KEY_PATH" 2>/dev/null)
    success "Certificate: $CERT_PATH ($CERT_SIZE bytes)"
    success "Key:         $KEY_PATH ($KEY_SIZE bytes)"
else
    error "Certificate files not found after generation!"
    exit 1
fi

# Step 7: Update hosts file
if [[ "$SKIP_HOSTS" == false ]]; then
    echo ""
    info "Updating /etc/hosts file..."
    
    ADDED_DOMAINS=()
    EXISTING_DOMAINS=()
    
    for domain in "${HOSTS_DOMAINS[@]}"; do
        if grep -qE "^\s*127\.0\.0\.1\s+$domain\s*$" "$HOSTS_FILE" 2>/dev/null; then
            EXISTING_DOMAINS+=("$domain")
        else
            ADDED_DOMAINS+=("$domain")
        fi
    done
    
    if [[ ${#ADDED_DOMAINS[@]} -gt 0 ]]; then
        echo ""
        info "Adding to hosts file (requires sudo):"
        for domain in "${ADDED_DOMAINS[@]}"; do
            echo -e "${GREEN}  + 127.0.0.1 $domain${NC}"
        done
        
        # Add entries to hosts file
        for domain in "${ADDED_DOMAINS[@]}"; do
            echo "127.0.0.1 $domain" | sudo tee -a "$HOSTS_FILE" > /dev/null
        done
        success "Hosts file updated"
    fi
    
    if [[ ${#EXISTING_DOMAINS[@]} -gt 0 ]]; then
        info "Already in hosts file:"
        for domain in "${EXISTING_DOMAINS[@]}"; do
            echo -e "${GRAY}  = 127.0.0.1 $domain${NC}"
        done
    fi
    
    if [[ ${#ADDED_DOMAINS[@]} -eq 0 ]]; then
        success "Hosts file already configured correctly"
    fi
else
    info "Skipping hosts file modification (--skip-hosts flag set)"
fi

# Step 8: Get CA root location
CA_ROOT=$(mkcert -CAROOT)
CA_ROOT_PEM="$CA_ROOT/rootCA.pem"

# Step 9: Print success message
echo ""
echo -e "${GREEN}================================================================${NC}"
echo -e "${GREEN}                    Setup Complete!                             ${NC}"
echo -e "${GREEN}================================================================${NC}"
echo ""

echo -e "${YELLOW}Next Steps:${NC}"
echo ""
echo -e "1. Restart Docker containers:"
echo -e "${GRAY}   docker compose down${NC}"
echo -e "${GRAY}   docker compose up -d${NC}"
echo ""

if [[ "$SKIP_HOSTS" == true ]]; then
    echo -e "2. Add entries to hosts file (/etc/hosts):"
    for domain in "${HOSTS_DOMAINS[@]}"; do
        echo -e "${GRAY}   127.0.0.1 $domain${NC}"
    done
    echo ""
    echo -e "3. For Firefox-based browsers:"
else
    echo -e "2. For Firefox-based browsers:"
fi

echo -e "${GRAY}   The local CA should be automatically trusted.${NC}"
echo -e "${GRAY}   If not, import manually in Firefox:${NC}"
echo -e "${GRAY}   - Settings > Privacy & Security > Certificates > View Certificates${NC}"
echo -e "${GRAY}   - Authorities tab > Import${NC}"
echo -e "${CYAN}   - Select: $CA_ROOT_PEM${NC}"
echo -e "${GRAY}   - Check: Trust this CA to identify websites${NC}"
echo -e "${GRAY}   - Restart the browser${NC}"
echo ""

echo -e "${YELLOW}Your local development URLs:${NC}"
echo -e "${CYAN}   https://${PORTAL_HOST}${NC}"
echo -e "${CYAN}   https://${ADMIN_HOST}${NC}"
echo -e "${CYAN}   http://${MAILPIT_HOST}${NC}"
echo -e "${CYAN}   http://${MINIO_HOST}${NC}"
echo -e "${CYAN}   http://${MINIO_CONSOLE_HOST}${NC}"
echo ""
