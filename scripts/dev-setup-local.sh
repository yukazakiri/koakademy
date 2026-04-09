#!/bin/bash
#
# DCCP Admin V3 - Local Development Setup (Non-Docker)
#
# This script sets up a local development environment on Linux:
# - Auto-detects Linux distribution
# - Installs PHP, Composer, npm, Laravel Installer (via php.new)
# - Installs Bun with Fish shell support
# - Installs and configures PostgreSQL
# - Installs mkcert for SSL certificates
# - Configures Nginx as reverse proxy with HTTPS
# - Sets up the Laravel application
#
# Usage:
#   ./scripts/dev-setup-local.sh              # Full setup
#   ./scripts/dev-setup-local.sh --skip-ssl   # Skip SSL setup
#   ./scripts/dev-setup-local.sh --skip-hosts # Skip hosts file setup
#   ./scripts/dev-setup-local.sh --skip-db    # Skip database setup
#   ./scripts/dev-setup-local.sh --help       # Show help

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CERTS_DIR="$PROJECT_ROOT/storage/certs"
HOSTS_FILE="/etc/hosts"
HOSTS_BACKUP_PREFIX="/etc/hosts.dccp-admin-v3"
HOSTS_MANAGED_START="# >>> DCCP Admin V3 local domains >>>"
HOSTS_MANAGED_END="# <<< DCCP Admin V3 local domains <<<"
NGINX_SITES_AVAILABLE="/etc/nginx/sites-available"
NGINX_SITES_ENABLED="/etc/nginx/sites-enabled"

# Load environment variables
ENV_FILE="$PROJECT_ROOT/.env"
PROD_ENV_FILE="$PROJECT_ROOT/.env.production.example"

if [ -f "$ENV_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    . "$ENV_FILE"
    set +a
fi

# Default domains from .env.production.example if not set in .env
PORTAL_HOST=${PORTAL_HOST:-portal.dccp.com}
ADMIN_HOST=${ADMIN_HOST:-admin.dccp.com}
MAILPIT_HOST=${MAILPIT_HOST:-mailpit.local.com}
MINIO_HOST=${MINIO_HOST:-minio.local.com}
MINIO_CONSOLE_HOST=${MINIO_CONSOLE_HOST:-minio-console.local.com}

# Extract base domain
BASE_DOMAIN=$(echo "$PORTAL_HOST" | sed 's/^[^.]*\.//')

CERT_FILE="${BASE_DOMAIN}.pem"
KEY_FILE="${BASE_DOMAIN}-key.pem"
CERT_PATH="$CERTS_DIR/$CERT_FILE"
KEY_PATH="$CERTS_DIR/$KEY_FILE"

# Domains array
DOMAINS=(
    "$PORTAL_HOST"
    "$ADMIN_HOST"
    "$MAILPIT_HOST"
    "$MINIO_HOST"
    "$MINIO_CONSOLE_HOST"
    "localhost"
)

# Server choice
SERVER_TYPE=${1:-octane}  # Default to octane, can be "artisan"

# Database configuration
DB_NAME=${DB_DATABASE:-dccp_admin}
DB_USER=${DB_USERNAME:-dccp_user}
DB_PASSWORD=${DB_PASSWORD:-secure_password_here}
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-5432}

# Flags
SKIP_SSL=false
SKIP_HOSTS=false
SKIP_DB=false
SKIP_DB_NETWORK=false
FORCE_DB_RECONFIG=false
ALLOW_EXTERNAL_HOSTS=false
REPAIR_HOSTS=false
RESTORE_HOSTS_BACKUP=false

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BLUE='\033[0;34m'
NC='\033[0m'

# Output functions
success() { echo -e "${GREEN}[OK]${NC} $1"; }
info() { echo -e "${CYAN}[..]${NC} $1"; }
warn() { echo -e "${YELLOW}[!!]${NC} $1"; }
error() { echo -e "${RED}[XX]${NC} $1"; }
section() { echo -e "\n${BLUE}═══════════════════════════════════════${NC}"; echo -e "${BLUE}$1${NC}"; echo -e "${BLUE}═══════════════════════════════════════${NC}\n"; }

# Detect Linux distribution
detect_distro() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        DISTRO="${ID:-unknown}"
        DISTRO_VERSION="${VERSION_ID:-unknown}"
        DISTRO_NAME="${NAME:-Unknown Linux}"
    elif [ -f /etc/lsb-release ]; then
        . /etc/lsb-release
        DISTRO="${DISTRIB_ID:-unknown}"
        DISTRO_VERSION="${DISTRIB_RELEASE:-unknown}"
        DISTRO_NAME="${DISTRIB_DESCRIPTION:-Unknown Linux}"
    else
        error "Unable to detect Linux distribution"
        exit 1
    fi
    
    # Normalize distro names
    case "$DISTRO" in
        ubuntu|debian|linuxmint|pop)
            FAMILY="debian"
            ;;
        fedora|rhel|centos|rocky|alma|ol)
            FAMILY="rhel"
            ;;
        arch|manjaro|endeavouros)
            FAMILY="arch"
            ;;
        opensuse|sles)
            FAMILY="suse"
            ;;
        *)
            FAMILY="$DISTRO"
            ;;
    esac
    
    info "Detected: $DISTRO_NAME ($DISTRO $DISTRO_VERSION, family: $FAMILY)"
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-ssl)
            SKIP_SSL=true
            shift
            ;;
        --skip-hosts)
            SKIP_HOSTS=true
            shift
            ;;
        --skip-db)
            SKIP_DB=true
            shift
            ;;
        --skip-db-network)
            SKIP_DB_NETWORK=true
            shift
            ;;
        --force-db-reconfig)
            FORCE_DB_RECONFIG=true
            shift
            ;;
        --allow-external-hosts)
            ALLOW_EXTERNAL_HOSTS=true
            shift
            ;;
        --repair-hosts)
            REPAIR_HOSTS=true
            shift
            ;;
        --restore-hosts-backup)
            RESTORE_HOSTS_BACKUP=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-ssl           Skip SSL certificate setup"
            echo "  --skip-hosts         Skip hosts file setup"
            echo "  --skip-db            Skip database setup"
            echo "  --skip-db-network    Skip PostgreSQL network access configuration"
            echo "  --force-db-reconfig  Force database reconfiguration"
            echo "  --allow-external-hosts  Allow mapping non-local domains in /etc/hosts"
            echo "  --repair-hosts          Remove DCCP host overrides from /etc/hosts"
            echo "  --restore-hosts-backup  Restore latest /etc/hosts backup made by this script"
            echo "  -h, --help           Show this help message"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Check if running as root and prompt for password
check_root() {
    if [ "$EUID" -ne 0 ]; then
        warn "This script requires root privileges for: nginx, mkcert, hosts file"
        echo ""
        echo -e "${CYAN}When prompted for sudo, enter your password${NC}"
        echo ""
        
        # Prompt for sudo password upfront to avoid multiple prompts
        echo -n "Enter your sudo password: "
        read -s SUDO_PASSWORD
        echo ""
        
        # Verify password is correct
        if ! echo "$SUDO_PASSWORD" | sudo -S true 2>/dev/null; then
            error "Invalid sudo password"
            exit 1
        fi
        success "Sudo authentication successful"
        echo ""
    else
        # Running as root, no password needed
        SUDO_PASSWORD=""
    fi
}

# Export sudo password for use in functions
export SUDO_PASSWORD=""

# Helper function to run sudo commands
run_sudo() {
    if [ -n "$SUDO_PASSWORD" ]; then
        echo "$SUDO_PASSWORD" | sudo -S "$@"
    else
        sudo "$@"
    fi
}

# Run sudo command and suppress output
run_sudo_quiet() {
    if [ -n "$SUDO_PASSWORD" ]; then
        echo "$SUDO_PASSWORD" | sudo -S "$@" 2>/dev/null
    else
        sudo "$@" 2>/dev/null
    fi
}

# Fix PostgreSQL data directory initialization
fix_postgres_data_dir() {
    local DATA_DIR=""
    local LOG_FILE=""
    
    # Default to /var/lib/postgres/data (Arch Linux default)
    # This will be overridden by distribution-specific detection below
    DATA_DIR="/var/lib/postgres/data"
    LOG_FILE="/var/lib/postgres/logfile"
    
    # Detect PostgreSQL data directory location based on distribution
    case "$FAMILY" in
        debian)
            DATA_DIR="/var/lib/postgresql/16/main"
            LOG_FILE="/var/log/postgresql/postgresql-16-main.log"
            ;;
        rhel)
            DATA_DIR="/var/lib/pgsql/data"
            LOG_FILE="/var/lib/pgsql/data/log"
            ;;
        arch)
            if [ -d "/var/lib/postgres/data" ]; then
                DATA_DIR="/var/lib/postgres/data"
                LOG_FILE="/var/lib/postgres/logfile"
            elif [ -d "/var/lib/postgresql/data" ]; then
                DATA_DIR="/var/lib/postgresql/data"
                LOG_FILE="/var/lib/postgresql/logfile"
            else
                DATA_DIR="/var/lib/postgres/data"
                LOG_FILE="/var/lib/postgres/logfile"
            fi
            ;;
        suse)
            if [ -d "/var/lib/postgres/data" ]; then
                DATA_DIR="/var/lib/postgres/data"
                LOG_FILE="/var/lib/postgres/logfile"
            elif [ -d "/var/lib/postgresql/data" ]; then
                DATA_DIR="/var/lib/postgresql/data"
                LOG_FILE="/var/lib/postgresql/logfile"
            else
                DATA_DIR="/var/lib/postgres/data"
                LOG_FILE="/var/lib/postgres/logfile"
            fi
            ;;
        *)
            # Try common locations - prioritize /var/lib/postgres (Arch default)
            if [ -d "/var/lib/postgres/data" ]; then
                DATA_DIR="/var/lib/postgres/data"
                LOG_FILE="/var/lib/postgres/logfile"
            elif [ -d "/var/lib/postgresql/data" ]; then
                DATA_DIR="/var/lib/postgresql/data"
                LOG_FILE="/var/lib/postgresql/logfile"
            else
                DATA_DIR="/var/lib/postgres/data"
                LOG_FILE="/var/lib/postgres/logfile"
            fi
            ;;
    esac
    
    info "Checking PostgreSQL data directory: $DATA_DIR"
    
    # Check if data directory exists and is initialized
    if [ -d "$DATA_DIR" ] && [ -f "$DATA_DIR/PG_VERSION" ]; then
        info "PostgreSQL data directory already initialized"
        return 0
    fi
    
    warn "PostgreSQL data directory not initialized"
    info "Initializing PostgreSQL data directory..."
    
    # Create directory if it doesn't exist
    echo "$SUDO_PASSWORD" | sudo -S mkdir -p "$DATA_DIR" 2>/dev/null || true
    echo "$SUDO_PASSWORD" | sudo -S chown -R postgres:postgres "$(dirname "$DATA_DIR")" 2>/dev/null || true
    
    # Initialize the database cluster
    echo "$SUDO_PASSWORD" | sudo -S -u postgres initdb -D "$DATA_DIR" 2>/dev/null || true
    
    if [ -f "$DATA_DIR/PG_VERSION" ]; then
        success "PostgreSQL data directory initialized at $DATA_DIR"
        
        # Create log file
        echo "$SUDO_PASSWORD" | sudo -S touch "$LOG_FILE" 2>/dev/null || true
        echo "$SUDO_PASSWORD" | sudo -S chown postgres:postgres "$LOG_FILE" 2>/dev/null || true
        
        return 0
    else
        error "Failed to initialize PostgreSQL data directory"
        return 1
    fi
}

# Install PostgreSQL based on distribution
install_postgres() {
    section "Installing PostgreSQL"
    
    # Check if PostgreSQL is already installed
    if command -v pg_isready &> /dev/null; then
        info "PostgreSQL already installed"
    else
        # Install common build dependencies and PostgreSQL
        info "Installing dependencies..."
        case "$FAMILY" in
            debian)
                echo "$SUDO_PASSWORD" | sudo -S apt-get update
                echo "$SUDO_PASSWORD" | sudo -S apt-get install -y curl wget git unzip zip build-essential \
                    ca-certificates gnupg lsb-release \
                    postgresql postgresql-contrib libpq-dev
                ;;
            rhel)
                echo "$SUDO_PASSWORD" | sudo -S dnf install -y curl wget git unzip zip gcc gcc-c++ \
                    postgresql-server postgresql-contrib
                ;;
            arch)
                echo "$SUDO_PASSWORD" | sudo -S pacman -S --noconfirm curl wget git unzip zip base-devel \
                    postgresql
                ;;
            suse)
                echo "$SUDO_PASSWORD" | sudo -S zypper install -y curl wget git unzip zip gcc gcc-c++ \
                    postgresql-server postgresql-contrib
                ;;
        esac
        success "Dependencies installed"
    fi
    
    # Fix PostgreSQL data directory if needed
    fix_postgres_data_dir || true
    
    # Start PostgreSQL (skip if already running)
    if command -v pg_isready &> /dev/null && pg_isready -q; then
        info "PostgreSQL already running"
    else
        case "$FAMILY" in
            debian)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || echo "$SUDO_PASSWORD" | sudo -S service postgresql start 2>/dev/null || true
                echo "$SUDO_PASSWORD" | sudo -S systemctl enable postgresql 2>/dev/null || true
                ;;
            rhel)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || true
                echo "$SUDO_PASSWORD" | sudo -S systemctl enable postgresql 2>/dev/null || true
                ;;
            arch)
                # Try systemctl first, then pg_ctl
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || {
                    if [ -d "/var/lib/postgres/data" ]; then
                        local DATA_DIR="/var/lib/postgres/data"
                        local LOG_FILE="/var/lib/postgres/logfile"
                    elif [ -d "/var/lib/postgresql/data" ]; then
                        local DATA_DIR="/var/lib/postgresql/data"
                        local LOG_FILE="/var/lib/postgresql/logfile"
                    else
                        local DATA_DIR="/var/lib/postgres/data"
                        local LOG_FILE="/var/lib/postgres/logfile"
                    fi
                    echo "$SUDO_PASSWORD" | sudo -S -u postgres pg_ctl -D "$DATA_DIR" -l "$LOG_FILE" start 2>/dev/null || true
                }
                echo "$SUDO_PASSWORD" | sudo -S systemctl enable postgresql 2>/dev/null || true
                ;;
            suse)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || true
                echo "$SUDO_PASSWORD" | sudo -S systemctl enable postgresql 2>/dev/null || true
                ;;
            *)
                warn "Unsupported distribution: $DISTRO"
                return 1
                ;;
        esac
    fi
    
    sleep 3
    
    if command -v pg_isready &> /dev/null; then
        if pg_isready -q; then
            success "PostgreSQL is running"
        else
            warn "PostgreSQL may not be running properly"
        fi
    fi
}

# Configure PostgreSQL for network access
configure_postgres_network() {
    section "Configuring PostgreSQL Network Access"

    # Detect PostgreSQL data directory
    local PG_DATA_DIR=""
    case "$FAMILY" in
        debian)
            PG_DATA_DIR="/var/lib/postgresql/16/main"
            ;;
        rhel)
            PG_DATA_DIR="/var/lib/pgsql/data"
            ;;
        arch|suse)
            if [ -d "/var/lib/postgres/data" ]; then
                PG_DATA_DIR="/var/lib/postgres/data"
            elif [ -d "/var/lib/postgresql/data" ]; then
                PG_DATA_DIR="/var/lib/postgresql/data"
            else
                PG_DATA_DIR="/var/lib/postgres/data"
            fi
            ;;
        *)
            # Try common locations - prioritize /var/lib/postgres
            if [ -d "/var/lib/postgres/data" ]; then
                PG_DATA_DIR="/var/lib/postgres/data"
            elif [ -d "/var/lib/postgresql/data" ]; then
                PG_DATA_DIR="/var/lib/postgresql/data"
            else
                PG_DATA_DIR="/var/lib/postgres/data"
            fi
            ;;
    esac

    info "Using PostgreSQL data directory: $PG_DATA_DIR"

    # Ensure PostgreSQL is running before modifying config
    if ! command -v pg_isready &> /dev/null || ! pg_isready -q; then
        warn "PostgreSQL not running, attempting to start..."
        case "$FAMILY" in
            debian)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || true
                ;;
            rhel)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || true
                ;;
            arch|suse)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || {
                    local LOG_FILE="/var/lib/postgresql/logfile"
                    echo "$SUDO_PASSWORD" | sudo -S -u postgres pg_ctl -D "$PG_DATA_DIR" -l "$LOG_FILE" start 2>/dev/null || true
                }
                ;;
        esac
        sleep 2
    fi

    # Update postgresql.conf to listen on all addresses
    local POSTGRES_CONF="$PG_DATA_DIR/postgresql.conf"
    if [ -f "$POSTGRES_CONF" ]; then
        info "Configuring postgresql.conf to allow network connections..."

        # Check if listen_addresses is already set to '*'
        if grep -qE "^listen_addresses\s*=\s*'\*'" "$POSTGRES_CONF" 2>/dev/null; then
            info "listen_addresses already set to '*'"
        else
            # Replace existing listen_addresses line or add new one
            if grep -qE "^[#]?listen_addresses" "$POSTGRES_CONF" 2>/dev/null; then
                echo "$SUDO_PASSWORD" | sudo -S sed -i "s/^[#]\?listen_addresses.*/listen_addresses = '*'/" "$POSTGRES_CONF" 2>/dev/null || true
            else
                echo "$SUDO_PASSWORD" | sudo -S bash -c "echo \"listen_addresses = '*'\" >> '$POSTGRES_CONF'" 2>/dev/null || true
            fi
            success "Updated postgresql.conf to listen on all interfaces"
        fi
    else
        warn "postgresql.conf not found at $POSTGRES_CONF"
    fi

    # Update pg_hba.conf to allow network connections
    local PG_HBA_CONF="$PG_DATA_DIR/pg_hba.conf"
    if [ -f "$PG_HBA_CONF" ]; then
        info "Configuring pg_hba.conf to allow network connections..."

        # Check if the rule already exists
        if grep -q "host.*all.*all.*0.0.0.0/0.*scram-sha-256" "$PG_HBA_CONF" 2>/dev/null; then
            info "Network access rule already exists in pg_hba.conf"
        else
            # Add network access rules before any existing rules (insert after comments)
            local TEMP_HBA=$(mktemp)

            # Create the new rules
            cat > "$TEMP_HBA" <<'HBAEOF'
# Allow network connections from any IP (development only)
host    all             all             0.0.0.0/0               scram-sha-256
host    all             all             ::0/0                   scram-sha-256
HBAEOF

            # Append existing config after our rules
            cat "$PG_HBA_CONF" >> "$TEMP_HBA"

            # Replace the original file
            echo "$SUDO_PASSWORD" | sudo -S cp "$TEMP_HBA" "$PG_HBA_CONF" 2>/dev/null || true
            echo "$SUDO_PASSWORD" | sudo -S chown postgres:postgres "$PG_HBA_CONF" 2>/dev/null || true
            rm -f "$TEMP_HBA"

            success "Updated pg_hba.conf to allow network connections"
        fi
    else
        warn "pg_hba.conf not found at $PG_HBA_CONF"
    fi

    # Restart PostgreSQL to apply changes
    info "Restarting PostgreSQL to apply network configuration..."
    case "$FAMILY" in
        debian)
            echo "$SUDO_PASSWORD" | sudo -S systemctl restart postgresql 2>/dev/null || true
            ;;
        rhel)
            echo "$SUDO_PASSWORD" | sudo -S systemctl restart postgresql 2>/dev/null || true
            ;;
        arch|suse)
            echo "$SUDO_PASSWORD" | sudo -S systemctl restart postgresql 2>/dev/null || {
                local LOG_FILE="/var/lib/postgresql/logfile"
                echo "$SUDO_PASSWORD" | sudo -S -u postgres pg_ctl -D "$PG_DATA_DIR" -l "$LOG_FILE" restart 2>/dev/null || true
            }
            ;;
    esac

    sleep 2

    # Verify PostgreSQL is running and listening on network
    if pg_isready -q 2>/dev/null; then
        # Check if it's listening on all interfaces
        if command -v ss &> /dev/null && ss -tlnp 2>/dev/null | grep -q ":5432"; then
            local LISTEN_ADDR=$(ss -tlnp 2>/dev/null | grep ":5432" | head -1 | awk '{print $4}')
            if echo "$LISTEN_ADDR" | grep -qE "\*:5432|0\.0\.0\.0:5432|:::5432"; then
                success "PostgreSQL is now configured for network access on port 5432"
                info "Listening on: $LISTEN_ADDR"
            else
                info "PostgreSQL is running (check 'ss -tlnp | grep 5432' to verify network access)"
            fi
        else
            success "PostgreSQL restarted successfully"
        fi
    else
        warn "PostgreSQL may not have restarted properly"
    fi
}

# Configure PostgreSQL database and user
configure_postgres() {
    section "Configuring PostgreSQL"
    
    # Try to start PostgreSQL if not running
    if ! command -v pg_isready &> /dev/null || ! pg_isready -q; then
        warn "PostgreSQL not running, attempting to start..."
        # Try to fix data directory first
        fix_postgres_data_dir || true
        
        case "$FAMILY" in
            debian)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || echo "$SUDO_PASSWORD" | sudo -S service postgresql start 2>/dev/null || true
                ;;
            rhel)
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || true
                ;;
            arch)
                if [ -d "/var/lib/postgres/data" ]; then
                    local DATA_DIR="/var/lib/postgres/data"
                    local LOG_FILE="/var/lib/postgres/logfile"
                elif [ -d "/var/lib/postgresql/data" ]; then
                    local DATA_DIR="/var/lib/postgresql/data"
                    local LOG_FILE="/var/lib/postgresql/logfile"
                else
                    local DATA_DIR="/var/lib/postgres/data"
                    local LOG_FILE="/var/lib/postgres/logfile"
                fi
                echo "$SUDO_PASSWORD" | sudo -S -u postgres pg_ctl -D "$DATA_DIR" -l "$LOG_FILE" start 2>/dev/null || true
                ;;
            suse)
                if [ -d "/var/lib/postgres/data" ]; then
                    local DATA_DIR="/var/lib/postgres/data"
                    local LOG_FILE="/var/lib/postgres/logfile"
                elif [ -d "/var/lib/postgresql/data" ]; then
                    local DATA_DIR="/var/lib/postgresql/data"
                    local LOG_FILE="/var/lib/postgresql/logfile"
                else
                    local DATA_DIR="/var/lib/postgres/data"
                    local LOG_FILE="/var/lib/postgres/logfile"
                fi
                echo "$SUDO_PASSWORD" | sudo -S systemctl start postgresql 2>/dev/null || true
                ;;
        esac
        sleep 3
    fi
    
    if ! command -v pg_isready &> /dev/null || ! pg_isready -q; then
        error "PostgreSQL is not running. Please start it manually and try again."
        exit 1
    fi
    
    info "Creating database '$DB_NAME' and user '$DB_USER'..."
    
    if echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" 2>/dev/null | grep -q 1; then
        info "User '$DB_USER' already exists"
    else
        echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASSWORD';" 2>/dev/null || true
        success "User '$DB_USER' created"
    fi
    
    if echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -lqt 2>/dev/null | cut -d \| -f 1 | grep -qw "$DB_NAME"; then
        info "Database '$DB_NAME' already exists"
        if [[ "$FORCE_DB_RECONFIG" == true ]]; then
            warn "Dropping and recreating database (--force-db-reconfig set)..."
            echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -c "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null || true
            echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;" 2>/dev/null || true
            success "Database '$DB_NAME' recreated"
        fi
    else
        echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;" 2>/dev/null || true
        success "Database '$DB_NAME' created"
    fi
    
    echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;" 2>/dev/null || true
    echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -c "ALTER USER $DB_USER CREATEDB;" 2>/dev/null || true
    echo "$SUDO_PASSWORD" | sudo -S -u postgres psql -d "$DB_NAME" -c "GRANT ALL ON SCHEMA public TO $DB_USER;" 2>/dev/null || true
    
    success "PostgreSQL configured successfully"
}

# Install PHP, Composer, npm, Laravel Installer via php.new
install_php_stack() {
    section "Installing PHP Development Stack"
    
    HERD_BIN="$HOME/.config/herd-lite/bin"
    PHP_NEW_PROFILE="$HOME/.phpbdist/etc/profile"
    
    # Check if PHP is already available (from Herd or other sources)
    if [ -d "$HERD_BIN" ] && [ -x "$HERD_BIN/php" ]; then
        info "Found PHP in Laravel Herd: $HERD_BIN"
        
        # Source Herd profile
        BASHRC="$HOME/.bashrc"
        HERD_PATH='export PATH="$HOME/.config/herd-lite/bin:$PATH"'
        if [ -f "$BASHRC" ] && ! grep -q "herd-lite/bin" "$BASHRC"; then
            echo "$HERD_PATH" >> "$BASHRC"
            success "Added Herd PHP to PATH in ~/.bashrc"
        fi
        
        export PATH="$HERD_BIN:$PATH"
        
        if command -v php &> /dev/null; then
            PHP_VERSION=$(php -r "echo PHP_VERSION;")
            success "PHP $PHP_VERSION available"
        else
            error "PHP not available after PATH update"
            exit 1
        fi
    elif [ -f "$PHP_NEW_PROFILE" ]; then
        info "Found php.new profile, sourcing it..."
        # shellcheck disable=SC1090
        . "$PHP_NEW_PROFILE"
        
        if command -v php &> /dev/null; then
            PHP_VERSION=$(php -r "echo PHP_VERSION;")
            success "PHP $PHP_VERSION available from php.new"
        else
            error "PHP from php.new not available"
            exit 1
        fi
    else
        info "Running php.new installer..."
        info "This will install PHP, Composer, npm, and Laravel Installer"
        
        /bin/bash -c "$(curl -fsSL https://php.new/install/linux)"
        
        if [ -f "$PHP_NEW_PROFILE" ]; then
            # shellcheck disable=SC1090
            . "$PHP_NEW_PROFILE"
        fi
        
        if command -v php &> /dev/null; then
            PHP_VERSION=$(php -r "echo PHP_VERSION;")
            success "PHP $PHP_VERSION installed"
        else
            error "PHP installation failed"
            exit 1
        fi
        
        BASHRC="$HOME/.bashrc"
        PHP_NEW_PATH='[ -f "$HOME/.phpbdist/etc/profile" ] && . "$HOME/.phpbdist/etc/profile"'
        if [ -f "$BASHRC" ] && ! grep -q ".phpbdist/etc/profile" "$BASHRC"; then
            echo "$PHP_NEW_PATH" >> "$BASHRC"
            success "Added php.new to ~/.bashrc"
        fi
    fi
    
    if command -v composer &> /dev/null; then
        COMPOSER_VERSION=$(composer --version | head -n1)
        success "$COMPOSER_VERSION available"
    else
        error "Composer not available"
        exit 1
    fi
    
    if command -v npm &> /dev/null; then
        NPM_VERSION=$(npm --version)
        success "npm $NPM_VERSION available"
    else
        warn "npm not found - you may need to install it separately"
    fi
    
    if command -v laravel &> /dev/null; then
        LARAVEL_VERSION=$(laravel --version)
        success "$LARAVEL_VERSION available"
    else
        warn "Laravel installer not found"
    fi
}

# Install Bun
install_bun() {
    section "Installing Bun"
    
    # Check if bun is in PATH or in common install location
    if command -v bun &> /dev/null || [ -f "$HOME/.bun/bin/bun" ]; then
        if command -v bun &> /dev/null; then
            BUN_VERSION=$(bun --version)
        else
            BUN_VERSION=$("$HOME/.bun/bin/bun" --version)
        fi
        success "Bun $BUN_VERSION already installed"
        return 0
    fi
    
    info "Installing Bun..."
    
    curl -fsSL https://bun.sh/install | bash
    
    # Source the new Bun installation (uses BUN_INSTALL env)
    if [ -f "$HOME/.bash_profile" ]; then
        # shellcheck disable=SC1090
        . "$HOME/.bash_profile"
    fi
    
    # Check if bun is now available
    if command -v bun &> /dev/null; then
        BUN_VERSION=$(bun --version)
        success "Bun $BUN_VERSION installed and configured"
    else
        error "Bun installation failed"
        exit 1
    fi
    
    # Add to Fish shell (if using conf.d)
    FISH_HERD_CONF="$HOME/.config/fish/conf.d/bun.fish"
    if [ -d "$HOME/.config/fish/conf.d" ] && [ ! -f "$FISH_HERD_CONF" ]; then
        cat > "$FISH_HERD_CONF" <<'FISHEOF'
# Bun - add to PATH
if test -d "$HOME/.bun/bin"
    set -gx PATH "$HOME/.bun/bin" $PATH
end
FISHEOF
        success "Added Bun to Fish shell config"
    fi
}

# Install Nginx
install_nginx() {
    section "Installing Nginx"
    
    if command -v nginx &> /dev/null; then
        NGINX_VERSION=$(nginx -v 2>&1 | cut -d' ' -f2 | tr -d 'v')
        success "Nginx $NGINX_VERSION already installed"
        return 0
    fi
    
    info "Installing Nginx..."
    
    case "$FAMILY" in
        debian)
            sudo apt-get update
            sudo apt-get install -y nginx
            ;;
        rhel)
            sudo dnf install -y nginx
            ;;
        arch)
            sudo pacman -S --noconfirm nginx
            ;;
        suse)
            sudo zypper install -y nginx
            ;;
    esac
    
    sudo systemctl enable nginx 2>/dev/null || true
    sudo systemctl start nginx 2>/dev/null || true
    
    if command -v nginx &> /dev/null; then
        success "Nginx installed and started"
    else
        error "Nginx installation failed"
        return 1
    fi
}

# Install mkcert
install_mkcert() {
    section "Installing mkcert"
    
    if command -v mkcert &> /dev/null; then
        MKCERT_VERSION=$(mkcert --version 2>/dev/null || echo "installed")
        success "mkcert $MKCERT_VERSION already installed"
        return 0
    fi
    
    info "Installing mkcert..."
    
    case "$FAMILY" in
        debian)
            sudo apt-get update
            sudo apt-get install -y libnss3-tools libnss3 libssl3
            ;;
        rhel)
            sudo dnf install -y nss-tools
            ;;
        arch)
            sudo pacman -S --noconfirm nss
            ;;
        suse)
            sudo zypper install -y nss-tools
            ;;
    esac
    
    OS=$(uname -s)
    ARCH=$(uname -m)
    
    case "$OS" in
        Linux)
            case "$ARCH" in
                x86_64)
                    MKCERT_URL="https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-amd64"
                    ;;
                aarch64|arm64)
                    MKCERT_URL="https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-arm64"
                    ;;
                *)
                    error "Unsupported architecture: $ARCH"
                    return 1
                    ;;
            esac
            ;;
        *)
            error "Unsupported OS: $OS"
            return 1
            ;;
    esac
    
    sudo curl -fsSL "$MKCERT_URL" -o /usr/local/bin/mkcert
    sudo chmod +x /usr/local/bin/mkcert
    
    if command -v mkcert &> /dev/null; then
        success "mkcert installed"
    else
        error "mkcert installation failed"
        return 1
    fi
}

# Setup environment file
setup_env_file() {
    section "Environment Configuration"
    
    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        if [ -f "$PROD_ENV_FILE" ]; then
            info "Creating .env from .env.production.example..."
            cp "$PROD_ENV_FILE" "$PROJECT_ROOT/.env"
            success ".env file created from .env.production.example"
        else
            info "Creating .env from .env.example..."
            cp "$PROJECT_ROOT/.env.example" "$PROJECT_ROOT/.env"
            success ".env file created from .env.example"
        fi
    else
        success ".env file already exists"
    fi
    
    if [ -f "$PROJECT_ROOT/.env" ]; then
        info "Updating database configuration in .env..."
        
        sed -i "s|DB_CONNECTION=pgsql|DB_CONNECTION=pgsql|" "$PROJECT_ROOT/.env"
        sed -i "s|DB_HOST=.*|DB_HOST=${DB_HOST}|" "$PROJECT_ROOT/.env"
        sed -i "s|DB_PORT=.*|DB_PORT=${DB_PORT}|" "$PROJECT_ROOT/.env"
        sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" "$PROJECT_ROOT/.env"
        sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "$PROJECT_ROOT/.env"
        sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" "$PROJECT_ROOT/.env"
        
        sed -i "s|REDIS_HOST=.*|REDIS_HOST=127.0.0.1|" "$PROJECT_ROOT/.env"
        sed -i "s|MAIL_HOST=.*|MAIL_HOST=127.0.0.1|" "$PROJECT_ROOT/.env"
        sed -i "s|MAIL_PORT=.*|MAIL_PORT=1025|" "$PROJECT_ROOT/.env"
        sed -i "s|AWS_ENDPOINT=.*|AWS_ENDPOINT=http://127.0.0.1:9000|" "$PROJECT_ROOT/.env"
        
        success "Database configuration updated"
    fi
}

# Setup SSL certificates
setup_ssl() {
    if [[ "$SKIP_SSL" == true ]]; then
        info "Skipping SSL setup (--skip-ssl flag set)"
        return 0
    fi
    
    section "SSL Certificate Setup"
    
    if ! command -v mkcert &> /dev/null; then
        warn "mkcert not installed - installing..."
        install_mkcert
    fi
    
    info "Installing local CA..."
    mkcert -install
    success "Local CA installed"
    
    mkdir -p "$CERTS_DIR"
    
    info "Generating SSL certificates..."
    
    CERT_DOMAINS=(
        "$PORTAL_HOST"
        "$ADMIN_HOST"
        "*.$BASE_DOMAIN"
        "$MAILPIT_HOST"
        "$MINIO_HOST"
        "$MINIO_CONSOLE_HOST"
        "*.local.test"
        "localhost"
    )
    
    cd "$CERTS_DIR"
    
    mkcert -key-file "$KEY_FILE" -cert-file "$CERT_FILE" "${CERT_DOMAINS[@]}"
    
    if [ -f "$CERT_FILE" ] && [ -f "$KEY_FILE" ]; then
        success "SSL certificates generated"
    else
        warn "SSL certificate generation may have failed"
    fi
    
    if [ -d "$(mkcert -CAROOT 2>/dev/null)" ]; then
        cp "$(mkcert -CAROOT 2>/dev/null)/rootCA.pem" "$CERTS_DIR/rootCA.pem" 2>/dev/null || true
        success "Root CA certificate available at: $CERTS_DIR/rootCA.pem"
    fi
}

# Setup hosts file
restore_hosts_backup() {
    section "Restoring Hosts Backup"

    local latest_backup
    latest_backup=$(ls -1t "${HOSTS_BACKUP_PREFIX}".*.bak 2>/dev/null | head -n1 || true)

    if [ -z "$latest_backup" ]; then
        error "No hosts backup found with prefix ${HOSTS_BACKUP_PREFIX}"
        return 1
    fi

    run_sudo cp "$latest_backup" "$HOSTS_FILE"
    success "Restored hosts file from backup: $latest_backup"
}

repair_hosts_file() {
    section "Repairing Hosts File"

    local backup_file
    backup_file="${HOSTS_BACKUP_PREFIX}.repair.$(date +%Y%m%d-%H%M%S).bak"
    run_sudo cp "$HOSTS_FILE" "$backup_file"
    success "Backed up current hosts file to $backup_file"

    local domains_csv
    domains_csv=$(IFS=,; echo "${DOMAINS[*]}")

    local tmp_hosts_file
    tmp_hosts_file=$(mktemp)

    awk -v domains_csv="$domains_csv" -v managed_start="$HOSTS_MANAGED_START" -v managed_end="$HOSTS_MANAGED_END" '
        BEGIN {
            split(domains_csv, domains, ",")

            for (domain_index in domains) {
                domain = tolower(domains[domain_index])

                if (domain != "") {
                    managed_domains[domain] = 1
                }
            }

            in_managed_block = 0
        }

        {
            line = $0
            trimmed = line
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", trimmed)

            if (trimmed == managed_start) {
                in_managed_block = 1
                next
            }

            if (trimmed == managed_end) {
                in_managed_block = 0
                next
            }

            if (in_managed_block == 1) {
                next
            }

            if (trimmed == "" || trimmed ~ /^#/) {
                print line
                next
            }

            split(line, hash_parts, "#")
            content = hash_parts[1]
            raw_field_count = split(content, fields, /[[:space:]]+/)

            field_count = 0
            for (field_index in compact_fields) {
                delete compact_fields[field_index]
            }

            for (field_index = 1; field_index <= raw_field_count; field_index++) {
                if (fields[field_index] != "") {
                    field_count++
                    compact_fields[field_count] = fields[field_index]
                }
            }

            if (field_count == 0) {
                print line
                next
            }

            ip = tolower(compact_fields[1])
            is_loopback_mapping = (ip == "127.0.0.1" || ip == "::1" || ip == "localhost")

            if (is_loopback_mapping == 0) {
                print line
                next
            }

            output_line = compact_fields[1]
            alias_count = 0

            for (alias_index = 2; alias_index <= field_count; alias_index++) {
                alias = compact_fields[alias_index]
                alias_lower = tolower(alias)

                if (!(alias_lower in managed_domains)) {
                    output_line = output_line " " alias
                    alias_count++
                }
            }

            if (alias_count > 0) {
                print output_line
            }
        }
    ' "$HOSTS_FILE" > "$tmp_hosts_file"

    run_sudo cp "$tmp_hosts_file" "$HOSTS_FILE"
    rm -f "$tmp_hosts_file"

    success "Removed DCCP host overrides from $HOSTS_FILE"
    info "If DNS is still cached, restart NetworkManager: sudo systemctl restart NetworkManager"
}

setup_hosts() {
    if [[ "$SKIP_HOSTS" == true ]]; then
        info "Skipping hosts file setup (--skip-hosts flag set)"
        return 0
    fi
    
    section "Hosts File Setup"

    is_local_dev_domain() {
        local domain="$1"

        if [ "$domain" = "localhost" ]; then
            return 0
        fi

        case "$domain" in
            *.test|*.localhost|*.local|*.localdomain|*.local.com|*.dccp.com)
                return 0
                ;;
            *)
                return 1
                ;;
        esac
    }

    SAFE_DOMAINS=()
    SKIPPED_DOMAINS=()

    for domain in "${DOMAINS[@]}"; do
        if is_local_dev_domain "$domain" || [ "$ALLOW_EXTERNAL_HOSTS" = true ]; then
            SAFE_DOMAINS+=("$domain")
        else
            SKIPPED_DOMAINS+=("$domain")
        fi
    done

    if [ "${#SKIPPED_DOMAINS[@]}" -gt 0 ]; then
        warn "Skipping non-local domains to avoid breaking internet access: ${SKIPPED_DOMAINS[*]}"
        warn "Use --allow-external-hosts to explicitly map these domains to 127.0.0.1"
    fi

    if [ "${#SAFE_DOMAINS[@]}" -eq 0 ]; then
        warn "No safe domains to add to hosts file"
        return 0
    fi

    BACKUP_FILE="${HOSTS_BACKUP_PREFIX}.$(date +%Y%m%d-%H%M%S).bak"
    run_sudo cp "$HOSTS_FILE" "$BACKUP_FILE"
    success "Backed up hosts file to $BACKUP_FILE"

    TMP_HOSTS_FILE=$(mktemp)
    awk -v start="$HOSTS_MANAGED_START" -v end="$HOSTS_MANAGED_END" '
        $0 == start { in_block = 1; next }
        $0 == end { in_block = 0; next }
        !in_block { print }
    ' "$HOSTS_FILE" > "$TMP_HOSTS_FILE"

    {
        echo ""
        echo "$HOSTS_MANAGED_START"
        for domain in "${SAFE_DOMAINS[@]}"; do
            echo "127.0.0.1 $domain"
        done
        echo "$HOSTS_MANAGED_END"
    } >> "$TMP_HOSTS_FILE"

    run_sudo cp "$TMP_HOSTS_FILE" "$HOSTS_FILE"
    rm -f "$TMP_HOSTS_FILE"

    success "Updated hosts file with ${#SAFE_DOMAINS[@]} managed domain(s)"
}

# Configure Nginx with SSL
configure_nginx() {
    section "Configuring Nginx"
    
    if ! command -v nginx &> /dev/null; then
        error "Nginx is not installed"
        return 1
    fi
    
    info "Creating Nginx configuration for $PORTAL_HOST and $ADMIN_HOST..."
    
    # Ensure nginx directories exist and are writable
    info "Creating Nginx directories..."
    run_sudo mkdir -p "$NGINX_SITES_AVAILABLE"
    run_sudo mkdir -p "$NGINX_SITES_ENABLED"
    # Allow current user to write to nginx directories
    run_sudo chown "$USER:$USER" "$NGINX_SITES_AVAILABLE" 2>/dev/null || true
    run_sudo chown "$USER:$USER" "$NGINX_SITES_ENABLED" 2>/dev/null || true
    success "Nginx directories ready"
    
    # Check if SSL certificates exist
    if [[ "$SKIP_SSL" == false ]] && [ -f "$CERT_PATH" ] && [ -f "$KEY_PATH" ]; then
        USE_HTTPS=true
        info "Using HTTPS with SSL certificates"
    else
        USE_HTTPS=false
        warn "SSL certificates not found - using HTTP"
    fi
    
    # Create Nginx config for Portal
    PORTAL_NGINX_CONF="$NGINX_SITES_AVAILABLE/$PORTAL_HOST.conf"
    
    if [ "$USE_HTTPS" == true ]; then
        cat > "$PORTAL_NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $PORTAL_HOST;

    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $PORTAL_HOST;

    ssl_certificate $CERT_PATH;
    ssl_certificate_key $KEY_PATH;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    root $PROJECT_ROOT/public;
    index index.php index.html;

    charset utf-8;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log $PROJECT_ROOT/storage/logs/nginx-portal-access.log;
    error_log $PROJECT_ROOT/storage/logs/nginx-portal-error.log;
}
EOF
    else
        cat > "$PORTAL_NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $PORTAL_HOST;

    root $PROJECT_ROOT/public;
    index index.php index.html;

    charset utf-8;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log $PROJECT_ROOT/storage/logs/nginx-portal-access.log;
    error_log $PROJECT_ROOT/storage/logs/nginx-portal-error.log;
}
EOF
    fi
    
    # Create Nginx config for Admin
    ADMIN_NGINX_CONF="$NGINX_SITES_AVAILABLE/$ADMIN_HOST.conf"
    
    if [ "$USE_HTTPS" == true ]; then
        cat > "$ADMIN_NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $ADMIN_HOST;

    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $ADMIN_HOST;

    ssl_certificate $CERT_PATH;
    ssl_certificate_key $KEY_PATH;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    root $PROJECT_ROOT/public;
    index index.php index.html;

    charset utf-8;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log $PROJECT_ROOT/storage/logs/nginx-admin-access.log;
    error_log $PROJECT_ROOT/storage/logs/nginx-admin-error.log;
}
EOF
    else
        cat > "$ADMIN_NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $ADMIN_HOST;

    root $PROJECT_ROOT/public;
    index index.php index.html;

    charset utf-8;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log $PROJECT_ROOT/storage/logs/nginx-admin-access.log;
    error_log $PROJECT_ROOT/storage/logs/nginx-admin-error.log;
}
EOF
    fi
    
    # Create default localhost config
    LOCALHOST_NGINX_CONF="$NGINX_SITES_AVAILABLE/localhost.conf"
    
    if [ "$USE_HTTPS" == true ]; then
        cat > "$LOCALHOST_NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name localhost;

    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name localhost;

    ssl_certificate $CERT_PATH;
    ssl_certificate_key $KEY_PATH;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    root $PROJECT_ROOT/public;
    index index.php index.html;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF
    else
        cat > "$LOCALHOST_NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name localhost;

    root $PROJECT_ROOT/public;
    index index.php index.html;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    }
}
EOF
    fi
    
    # Enable sites
    run_sudo ln -sf "$PORTAL_NGINX_CONF" "$NGINX_SITES_ENABLED/" 2>/dev/null || true
    run_sudo ln -sf "$ADMIN_NGINX_CONF" "$NGINX_SITES_ENABLED/" 2>/dev/null || true
    run_sudo ln -sf "$LOCALHOST_NGINX_CONF" "$NGINX_SITES_ENABLED/" 2>/dev/null || true
    
    # Disable default site if exists
    if [ -f "$NGINX_SITES_ENABLED/default" ]; then
        run_sudo rm -f "$NGINX_SITES_ENABLED/default" 2>/dev/null || true
    fi
    
    # Test nginx config
    if run_sudo nginx -t 2>/dev/null; then
        success "Nginx configuration valid"
        
        # Reload nginx
        run_sudo systemctl reload nginx 2>/dev/null || run_sudo nginx -s reload 2>/dev/null || true
        success "Nginx reloaded with new configuration"
    else
        warn "Nginx configuration may have issues"
    fi
}

# Setup Laravel application
setup_laravel() {
    section "Laravel Application Setup"
    cd "$PROJECT_ROOT"

    if [ -d "$HOME/.config/herd-lite/bin" ]; then
        export PATH="$HOME/.config/herd-lite/bin:$PATH"
    fi

    if [ -f "$HOME/.phpbdist/etc/profile" ]; then
        # shellcheck disable=SC1090
        . "$HOME/.phpbdist/etc/profile"
    fi

    if [ -f "$HOME/.bash_profile" ]; then
        # shellcheck disable=SC1090
        . "$HOME/.bash_profile"
    elif [ -s "$HOME/.bun/_env" ]; then
        # shellcheck disable=SC1090
        . "$HOME/.bun/_env"
    fi

    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        warn ".env file not found; skipping Laravel app bootstrap"
        return 0
    fi

    if [ ! -f "$PROJECT_ROOT/vendor/autoload.php" ]; then
        info "Installing Composer dependencies..."
        composer install --no-interaction
    else
        info "Composer dependencies already installed"
    fi

    if [ ! -f "$PROJECT_ROOT/node_modules/.package-lock.json" ] && [ ! -d "$PROJECT_ROOT/node_modules" ]; then
        if command -v bun &> /dev/null; then
            info "Installing frontend dependencies with Bun..."
            bun install
        elif command -v npm &> /dev/null; then
            info "Installing frontend dependencies with npm..."
            npm install
        else
            warn "No frontend package manager found (bun/npm)"
        fi
    else
        info "Frontend dependencies already installed"
    fi

    if ! grep -qE '^APP_KEY=base64:' "$PROJECT_ROOT/.env"; then
        info "Generating APP_KEY..."
        php artisan key:generate --force
    else
        info "APP_KEY already configured"
    fi

    success "Laravel application setup complete"
}

create_scripts() {
    section "Creating Convenience Scripts"

    local SERVE_SCRIPT
    SERVE_SCRIPT="$PROJECT_ROOT/scripts/serve.sh"

    cat > "$SERVE_SCRIPT" <<'EOF'
#!/bin/bash
#
# Start Laravel development server (Octane or Artisan)
#

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

if [ -d "$HOME/.config/herd-lite/bin" ]; then
    export PATH="$HOME/.config/herd-lite/bin:$PATH"
fi

if [ -f "$HOME/.phpbdist/etc/profile" ]; then
    . "$HOME/.phpbdist/etc/profile"
fi

if [ -f "$HOME/.bash_profile" ]; then
    . "$HOME/.bash_profile"
elif [ -s "$HOME/.bun/_env" ]; then
    . "$HOME/.bun/_env"
fi

SERVER_TYPE="octane"
PORT=8000

while [[ $# -gt 0 ]]; do
    case $1 in
        --artisan)
            SERVER_TYPE="artisan"
            shift
            ;;
        --octane)
            SERVER_TYPE="octane"
            shift
            ;;
        --port)
            PORT="$2"
            shift 2
            ;;
        *)
            shift
            ;;
    esac
done

echo "Starting Laravel development server..."
echo "Server type: $SERVER_TYPE"
echo "Port: $PORT"
echo ""

if [ "$SERVER_TYPE" = "octane" ]; then
    echo "Starting Laravel Octane on port $PORT..."
    php artisan octane:start --port="$PORT"
else
    echo "Starting Laravel Artisan Serve on port $PORT..."
    php artisan serve --host=0.0.0.0 --port="$PORT"
fi
EOF

    chmod +x "$SERVE_SCRIPT"
    success "Created serve script: scripts/serve.sh"

    local NGINX_START_SCRIPT
    NGINX_START_SCRIPT="$PROJECT_ROOT/scripts/nginx-start.sh"

    cat > "$NGINX_START_SCRIPT" <<'EOF'
#!/bin/bash
#
# Start Nginx and Laravel development server
#

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

# Source Herd if available
if [ -d "$HOME/.config/herd-lite/bin" ]; then
    export PATH="$HOME/.config/herd-lite/bin:$PATH"
fi

# Source php.new if available
if [ -f "$HOME/.phpbdist/etc/profile" ]; then
    . "$HOME/.phpbdist/etc/profile"
fi

# Source bun if available (newer Bun uses ~/.bash_profile)
if [ -f "$HOME/.bash_profile" ]; then
    . "$HOME/.bash_profile"
elif [ -s "$HOME/.bun/_env" ]; then
    . "$HOME/.bun/_env"
fi

echo "Starting Nginx..."
sudo systemctl start nginx 2>/dev/null || sudo nginx 2>/dev/null || echo "Nginx may already be running"

echo "Starting Laravel Octane on port 8000..."
php artisan octane:start --port=8000
EOF

    chmod +x "$NGINX_START_SCRIPT"
    success "Created nginx-start script: scripts/nginx-start.sh"

    local SSL_SCRIPT
    SSL_SCRIPT="$PROJECT_ROOT/scripts/ssl-renew.sh"

    cat > "$SSL_SCRIPT" <<'EOF'
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
EOF

    chmod +x "$SSL_SCRIPT"
    success "Created SSL renewal script: scripts/ssl-renew.sh"
}

# Verify virtual hosts setup
verify_setup() {
    section "Verifying Setup"
    
    # Source environment
    if [ -f "$PROJECT_ROOT/.env" ]; then
        set -a
        # shellcheck disable=SC1090
        . "$PROJECT_ROOT/.env"
        set +a
    fi
    
    # Check PHP
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r "echo PHP_VERSION;")
        success "PHP $PHP_VERSION"
    else
        warn "PHP not in PATH"
    fi
    
    # Check Composer
    if command -v composer &> /dev/null; then
        success "Composer available"
    else
        warn "Composer not in PATH"
    fi
    
    # Check Bun
    if command -v bun &> /dev/null; then
        BUN_VERSION=$(bun --version)
        success "Bun $BUN_VERSION"
    else
        warn "Bun not in PATH"
    fi
    
    # Check Node
    if command -v node &> /dev/null; then
        NODE_VERSION=$(node --version)
        success "Node $NODE_VERSION"
    else
        warn "Node not in PATH"
    fi
    
    # Check Nginx
    if command -v nginx &> /dev/null; then
        if pgrep -x nginx > /dev/null; then
            success "Nginx running"
        else
            warn "Nginx installed but not running"
        fi
    else
        warn "Nginx not installed"
    fi
    
    # Check PostgreSQL
    if command -v pg_isready &> /dev/null && pg_isready -q; then
        success "PostgreSQL running"
    else
        warn "PostgreSQL not running"
    fi
    
    # Check mkcert
    if command -v mkcert &> /dev/null; then
        success "mkcert installed"
    else
        warn "mkcert not installed"
    fi
    
    # Check SSL certificates
    if [ -f "$CERTS_DIR/$CERT_FILE" ] && [ -f "$CERTS_DIR/$KEY_FILE" ]; then
        success "SSL certificates present"
    else
        warn "SSL certificates not found"
    fi
    
    # Test virtual hosts configuration
    info "Testing virtual hosts configuration..."
    
    for domain in "$PORTAL_HOST" "$ADMIN_HOST"; do
        if grep -qE "127\.0\.0\.1\s+$domain" "$HOSTS_FILE" 2>/dev/null; then
            success "Virtual host configured: $domain"
        else
            warn "Virtual host NOT configured: $domain"
        fi
    done
    
    # Check Nginx config
    if [ -f "$NGINX_SITES_AVAILABLE/$PORTAL_HOST.conf" ]; then
        success "Nginx config exists for $PORTAL_HOST"
    fi
    
    if [ -f "$NGINX_SITES_AVAILABLE/$ADMIN_HOST.conf" ]; then
        success "Nginx config exists for $ADMIN_HOST"
    fi
}

# Main execution
main() {
    # Banner
    echo ""
    echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║   DCCP Admin V3 - Local Development Setup            ║${NC}"
    echo -e "${BLUE}║   (Non-Docker - Nginx + HTTPS)                      ║${NC}"
    echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    # Detect distribution
    detect_distro
    
    # Check root
    check_root

    if [[ "$RESTORE_HOSTS_BACKUP" == true ]]; then
        restore_hosts_backup
        return 0
    fi

    if [[ "$REPAIR_HOSTS" == true ]]; then
        repair_hosts_file
        return 0
    fi

    # Install PHP stack
    install_php_stack
    
    # Install Bun
    install_bun
    
    # Install Nginx
    install_nginx
    
    # Install PostgreSQL
    if [[ "$SKIP_DB" == false ]]; then
        install_postgres
        configure_postgres
        if [[ "$SKIP_DB_NETWORK" == false ]]; then
            configure_postgres_network
        else
            info "Skipping PostgreSQL network configuration (--skip-db-network flag set)"
        fi
    else
        info "Skipping database setup (--skip-db flag set)"
    fi
    
    # Install mkcert
    install_mkcert
    
    # Setup environment file
    setup_env_file
    
    # Setup SSL certificates
    setup_ssl
    
    # Setup hosts file
    setup_hosts
    
    # Configure Nginx with SSL
    configure_nginx
    
    # Setup Laravel application
    setup_laravel
    
    # Create convenience scripts
    create_scripts
    
    # Verify setup
    verify_setup
    
    # Summary
    section "Setup Complete!"
    
    echo -e "${GREEN}Your local development environment is ready!${NC}\n"
    
    echo -e "${CYAN}Quick Start:${NC}"
    echo "  1. Source your shell profile:"
    echo "       source ~/.bashrc"
    echo "  2. Start the development server:"
    echo "       # Option A: Nginx + Octane (Recommended)"
    echo "       ./scripts/nginx-start.sh"
    echo "       # Option B: Artisan serve only"
    echo "       ./scripts/serve.sh --artisan"
    echo "       # Option C: Octane only"
    echo "       ./scripts/serve.sh --octane"
    echo ""
    
    echo -e "${CYAN}Application URLs (HTTPS):${NC}"
    echo "  https://localhost"
    echo "  https://${PORTAL_HOST}"
    echo "  https://${ADMIN_HOST}"
    echo ""
    
    echo -e "${CYAN}Application URLs (HTTP - redirects to HTTPS):${NC}"
    echo "  http://localhost"
    echo "  http://${PORTAL_HOST}"
    echo "  http://${ADMIN_HOST}"
    echo ""
    
    echo -e "${CYAN}Database:${NC}"
    echo "  Host:     $DB_HOST"
    echo "  Port:     $DB_PORT"
    echo "  Database: $DB_NAME"
    echo "  User:     $DB_USER"
    echo "  Password: $DB_PASSWORD"
    echo ""
    
    echo -e "${CYAN}SSL Certificates:${NC}"
    echo "  Location: $CERTS_DIR"
    echo "  To add to browser, import: $CERTS_DIR/rootCA.pem"
    echo "  To regenerate: ./scripts/ssl-renew.sh"
    echo ""
    
    echo -e "${CYAN}Next Steps:${NC}"
    echo "  1. Run migrations: php artisan migrate"
    echo "  2. Seed database:   php artisan db:seed"
    echo "  3. Start dev server: ./scripts/nginx-start.sh"
    echo "  4. Import SSL cert to browser: $CERTS_DIR/rootCA.pem"
    echo ""
    
    echo -e "${GREEN}Happy coding!${NC}"
    echo ""
}

# Run main
main
