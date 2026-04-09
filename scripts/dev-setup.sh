#!/bin/bash
#
# DCCP Admin V3 - Local Development Setup
#
# This script sets up the local development environment including:
# - Docker Compose services
# - Docker group access for supported Linux distros
# - SSL certificates
# - Hosts file entries
# - Environment configuration
#
# Usage:
#   ./scripts/dev-setup.sh              # Full setup
#   ./scripts/dev-setup.sh --skip-ssl   # Skip SSL setup
#   ./scripts/dev-setup.sh --skip-hosts # Skip hosts file setup
#   ./scripts/dev-setup.sh --skip-docker # Skip Docker setup

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CERTS_DIR="$PROJECT_ROOT/docker/traefik/certs"
HOSTS_FILE="/etc/hosts"
CURRENT_USER="${SUDO_USER:-$USER}"
DISTRO="unknown"
DISTRO_VERSION="unknown"
DISTRO_NAME="Unknown Linux"
FAMILY="unknown"
DOCKER_GROUP_UPDATED=false
DOCKER_USE_SUDO=false

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

# Extract base domain from PORTAL_HOST
BASE_DOMAIN=$(echo "$PORTAL_HOST" | sed 's/^[^.]*\.//')

CERT_FILE="${BASE_DOMAIN}.pem"
KEY_FILE="${BASE_DOMAIN}-key.pem"

# Domains
DOMAINS=(
    "$PORTAL_HOST"
    "$ADMIN_HOST"
    "$MAILPIT_HOST"
    "$MINIO_HOST"
    "$MINIO_CONSOLE_HOST"
)

# Flags
SKIP_SSL=false
SKIP_HOSTS=false
SKIP_DOCKER=false

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
section() { echo -e "\n${BLUE}═══════════════════════════════════════${NC}"; echo -e "${BLUE}$1${NC}"; echo -e "${BLUE}═══════════════════════════════════════${NC}\n"; }

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
    fi

    case "$DISTRO" in
        ubuntu|debian|linuxmint|pop)
            FAMILY="debian"
            ;;
        arch|manjaro|endeavouros|cachyos|garuda|arcolinux)
            FAMILY="arch"
            ;;
        *)
            FAMILY="$DISTRO"
            ;;
    esac
}

run_sudo() {
    sudo "$@"
}

is_user_in_docker_group() {
    getent group docker | grep -Eq "(^|:|,)$CURRENT_USER(,|$)"
}

is_docker_group_active_in_session() {
    id -nG | tr ' ' '\n' | grep -Fxq docker
}

arch_package_manager() {
    if command -v paru > /dev/null 2>&1; then
        echo "paru"
        return 0
    fi

    if command -v yay > /dev/null 2>&1; then
        echo "yay"
        return 0
    fi

    return 1
}

install_docker_dependencies() {
    case "$FAMILY" in
        arch)
            local helper

            if ! helper=$(arch_package_manager); then
                error "Arch-based setup requires an AUR helper. Install 'paru' or 'yay' first."
                exit 1
            fi

            info "Installing Docker packages with $helper..."
            "$helper" -S --needed --noconfirm docker docker-compose
            success "Docker packages installed"
            ;;
        debian)
            info "Updating apt package index..."
            run_sudo apt-get update

            info "Installing Docker packages with apt..."
            if run_sudo apt-get install -y docker.io docker-compose-v2; then
                success "Docker packages installed"
            elif run_sudo apt-get install -y docker.io docker-compose-plugin; then
                success "Docker packages installed"
            else
                error "Failed to install Docker packages with apt"
                exit 1
            fi
            ;;
        *)
            error "Automatic Docker installation is only supported for Arch-based and Debian-based distros"
            exit 1
            ;;
    esac
}

docker_cmd() {
    if [[ "$DOCKER_USE_SUDO" == true ]]; then
        run_sudo docker "$@"
    else
        docker "$@"
    fi
}

docker_compose_cmd() {
    if command -v docker-compose > /dev/null 2>&1; then
        if [[ "$DOCKER_USE_SUDO" == true ]]; then
            run_sudo docker-compose "$@"
        else
            docker-compose "$@"
        fi
    else
        if [[ "$DOCKER_USE_SUDO" == true ]]; then
            run_sudo docker compose "$@"
        else
            docker compose "$@"
        fi
    fi
}

docker_compose_example() {
    if command -v docker-compose > /dev/null 2>&1; then
        if [[ "$DOCKER_USE_SUDO" == true ]]; then
            echo "sudo docker-compose"
        else
            echo "docker-compose"
        fi
    else
        if [[ "$DOCKER_USE_SUDO" == true ]]; then
            echo "sudo docker compose"
        else
            echo "docker compose"
        fi
    fi
}

start_docker_compose_services() {
    local compose_output=""

    if compose_output=$(docker_compose_cmd up -d 2>&1); then
        echo "$compose_output" | grep -v "is already in progress" || true
        return 0
    fi

    echo "$compose_output"

    if echo "$compose_output" | grep -qi "permission denied while trying to connect to the docker API"; then
        if [[ "$DOCKER_USE_SUDO" == false ]]; then
            warn "Retrying Docker Compose with sudo..."
            DOCKER_USE_SUDO=true

            if compose_output=$(docker_compose_cmd up -d 2>&1); then
                echo "$compose_output" | grep -v "is already in progress" || true
                return 0
            fi

            echo "$compose_output"
        fi
    fi

    return 1
}

ensure_docker_group_access() {
    local docker_group_exists=false

    if getent group docker > /dev/null 2>&1; then
        docker_group_exists=true
    fi

    if [[ "$docker_group_exists" == false ]]; then
        case "$FAMILY" in
            arch|debian)
                info "Creating docker group for $DISTRO_NAME..."
                run_sudo groupadd docker
                success "Docker group created"
                ;;
            *)
                warn "Docker group does not exist and automatic setup is only enabled for Arch-based and Debian-based distros"
                return 0
                ;;
        esac
    fi

    if is_user_in_docker_group; then
        success "User '$CURRENT_USER' is already in the docker group"
        return 0
    fi

    info "Adding '$CURRENT_USER' to the docker group..."
    run_sudo usermod -aG docker "$CURRENT_USER"
    DOCKER_GROUP_UPDATED=true
    DOCKER_USE_SUDO=true
    success "User '$CURRENT_USER' added to the docker group"
    warn "Group membership changes require a new login. This run will use sudo for Docker commands."
    info "After this script finishes, restart your session or run: newgrp docker"
}

configure_docker_access() {
    if is_docker_group_active_in_session; then
        DOCKER_USE_SUDO=false
        return 0
    fi

    DOCKER_USE_SUDO=true
    if is_user_in_docker_group; then
        warn "Docker will use sudo in this session until your new group membership takes effect"
    fi
    return 0
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
        --skip-docker)
            SKIP_DOCKER=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-ssl     Skip SSL certificate setup"
            echo "  --skip-hosts   Skip hosts file setup"
            echo "  --skip-docker  Skip Docker Compose setup"
            echo "  -h, --help     Show this help message"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Banner
echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       DCCP Admin V3 - Local Development Setup          ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Step 1: Check prerequisites
section "Checking Prerequisites"

detect_distro
info "Detected OS: $DISTRO_NAME ($DISTRO $DISTRO_VERSION)"

if ! command -v docker &> /dev/null; then
    warn "Docker is not installed or not in PATH"
    install_docker_dependencies
fi
success "Docker is installed"

if command -v docker-compose > /dev/null 2>&1; then
    success "Docker Compose is installed"
elif docker compose version > /dev/null 2>&1; then
    success "Docker Compose plugin is installed"
else
    warn "Docker Compose is not installed"
    install_docker_dependencies

    if command -v docker-compose > /dev/null 2>&1; then
        success "Docker Compose is installed"
    elif docker compose version > /dev/null 2>&1; then
        success "Docker Compose plugin is installed"
    else
        error "Docker Compose is still unavailable after installation"
        exit 1
    fi
fi

section "Docker Permissions"

ensure_docker_group_access

if ! configure_docker_access; then
    error "Unable to configure Docker CLI access"
    exit 1
fi
success "Docker CLI access is configured"

# Step 2: Setup .env file
section "Environment Configuration"

if [ ! -f "$PROJECT_ROOT/.env" ]; then
    info "Creating .env from .env.example..."
    cp "$PROJECT_ROOT/.env.example" "$PROJECT_ROOT/.env"
    success ".env file created"
else
    success ".env file already exists"
fi

# Step 3: Setup Docker Compose services
if [[ "$SKIP_DOCKER" == false ]]; then
    section "Docker Compose Setup"

    cd "$PROJECT_ROOT"

    info "Checking Docker daemon..."
    if ! docker_cmd info > /dev/null 2>&1; then
        case "$FAMILY" in
            arch|debian)
                warn "Docker daemon is not running"
                info "Starting Docker service..."
                run_sudo systemctl enable --now docker
                ;;
            *)
                error "Docker daemon is not running"
                echo "Please start Docker and try again"
                exit 1
                ;;
        esac

        if ! docker_cmd info > /dev/null 2>&1; then
            if [[ "$DOCKER_USE_SUDO" == false ]] && sudo docker info > /dev/null 2>&1; then
                DOCKER_USE_SUDO=true
                warn "Docker requires sudo in the current session until group membership reloads"
            else
                error "Docker daemon is still not accessible after startup"
                echo "Check the Docker service status and try again"
                exit 1
            fi
        fi

        if ! docker_cmd info > /dev/null 2>&1; then
            error "Docker daemon is still not accessible after startup"
            echo "Check the Docker service status and try again"
            exit 1
        fi
    fi
    success "Docker daemon is running"

    info "Building and starting Docker Compose services..."
    info "This may take a few minutes on first run..."

    if start_docker_compose_services; then
        success "Docker Compose services started"

        # Wait for services to be ready
        info "Waiting for services to be healthy..."
        sleep 5

        # Check if services are running
        RUNNING=$(docker_compose_cmd ps -q | wc -l)
        if [ "$RUNNING" -gt 0 ]; then
            success "Services are running"
        else
            warn "Services may still be starting up"
        fi
    else
        error "Failed to start Docker Compose services"
        echo "Run: $(docker_compose_example) up -d"
        exit 1
    fi
else
    info "Skipping Docker Compose setup (--skip-docker flag set)"
fi

# Step 4: Setup hosts file
if [[ "$SKIP_HOSTS" == false ]]; then
    section "Hosts File Setup"

    ADDED=0
    EXISTING=0

    for domain in "${DOMAINS[@]}"; do
        if grep -qE "^\s*127\.0\.0\.1\s+$domain\s*$|^\s*localhost\s+$domain\s*$" "$HOSTS_FILE" 2>/dev/null; then
            EXISTING=$((EXISTING + 1))
        else
            echo "127.0.0.1 $domain" | sudo tee -a "$HOSTS_FILE" > /dev/null
            ADDED=$((ADDED + 1))
        fi
    done

    if [ "$ADDED" -gt 0 ]; then
        success "Added $ADDED domain(s) to hosts file"
    fi
    if [ "$EXISTING" -gt 0 ]; then
        success "Found $EXISTING domain(s) already in hosts file"
    fi
else
    info "Skipping hosts file setup (--skip-hosts flag set)"
fi

# Step 5: Setup SSL certificates
if [[ "$SKIP_SSL" == false ]]; then
    section "SSL Certificate Setup"

    if command -v mkcert &> /dev/null; then
        info "mkcert found"

        if [ ! -f "$CERTS_DIR/$CERT_FILE" ] || [ ! -f "$CERTS_DIR/$KEY_FILE" ]; then
            info "Generating SSL certificates..."

            mkdir -p "$CERTS_DIR"

            CERT_DOMAINS=(
                "$PORTAL_HOST"
                "$ADMIN_HOST"
                "*.$BASE_DOMAIN"
                "$MAILPIT_HOST"
                "$MINIO_HOST"
                "$MINIO_CONSOLE_HOST"
                "*.local.test"
            )

            # Install local CA
            if mkcert -install 2>/dev/null; then
                success "Local CA installed/verified"
            fi

            # Generate certificates
            pushd "$CERTS_DIR" > /dev/null
            if mkcert -key-file "$KEY_FILE" -cert-file "$CERT_FILE" "${CERT_DOMAINS[@]}" 2>/dev/null; then
                success "SSL certificates generated"
            else
                warn "Failed to generate certificates, but continuing..."
            fi
            popd > /dev/null
        else
            success "SSL certificates already exist"
        fi
    else
        warn "mkcert not found - SSL setup skipped"
        # ... mkcert install instructions ...
    fi

    # Update Traefik dynamic configuration
    TRAEFIK_CONF="$PROJECT_ROOT/docker/traefik/dynamic/tls.yml"
    if [ -f "$TRAEFIK_CONF" ]; then
        info "Updating Traefik dynamic configuration..."

        cat > "$TRAEFIK_CONF" <<EOF
http:
  routers:
    # Portal (Laravel app)
    portal-secure:
      rule: "Host(\`${PORTAL_HOST}\`)"
      entrypoints:
        - websecure
      service: app
      tls: true
    portal-http:
      rule: "Host(\`${PORTAL_HOST}\`)"
      entrypoints:
        - web
      service: app
      middlewares:
        - redirect-websecure

    # Admin (Laravel app)
    admin-secure:
      rule: "Host(\`${ADMIN_HOST}\`)"
      entrypoints:
        - websecure
      service: app
      tls: true
    admin-http:
      rule: "Host(\`${ADMIN_HOST}\`)"
      entrypoints:
        - web
      service: app
      middlewares:
        - redirect-websecure

    # MinIO
    minio-secure:
      rule: "Host(\`${MINIO_HOST}\`)"
      entrypoints:
        - websecure
      service: minio
      tls: true
    minio-http:
      rule: "Host(\`${MINIO_HOST}\`)"
      entrypoints:
        - web
      service: minio

    # MinIO Console
    minio-console-secure:
      rule: "Host(\`${MINIO_CONSOLE_HOST}\`)"
      entrypoints:
        - websecure
      service: minio-console
      tls: true
    minio-console-http:
      rule: "Host(\`${MINIO_CONSOLE_HOST}\`)"
      entrypoints:
        - web
      service: minio-console

    # Mailpit
    mailpit-secure:
      rule: "Host(\`${MAILPIT_HOST}\`)"
      entrypoints:
        - websecure
      service: mailpit
      tls: true
    mailpit-http:
      rule: "Host(\`${MAILPIT_HOST}\`)"
      entrypoints:
        - web
      service: mailpit

  services:
    app:
      loadBalancer:
        servers:
          - url: "http://laravel:80"
    minio:
      loadBalancer:
        servers:
          - url: "http://minio:9000"
    minio-console:
      loadBalancer:
        servers:
          - url: "http://minio:8900"
    mailpit:
      loadBalancer:
        servers:
          - url: "http://mailpit:8025"

  middlewares:
    redirect-websecure:
      redirectScheme:
        scheme: https
        permanent: true

tls:
  certificates:
    - certFile: /etc/traefik/certs/${CERT_FILE}
      keyFile: /etc/traefik/certs/${KEY_FILE}
  stores:
    default:
      defaultCertificate:
        certFile: /etc/traefik/certs/${CERT_FILE}
        keyFile: /etc/traefik/certs/${KEY_FILE}
EOF
        success "Traefik configuration updated with .env domains"
    else
        warn "Traefik configuration file not found at $TRAEFIK_CONF"
    fi

else
    info "Skipping SSL certificate setup (--skip-ssl flag set)"
fi

# Step 6: Setup Sail alias
section "Sail Command Setup"

SAIL_PATH="$PROJECT_ROOT/vendor/bin/sail"
BASHRC="$HOME/.bashrc"
ZSHRC="$HOME/.zshrc"

setup_sail_alias() {
    local shell_config="$1"
    local sail_export="export PATH=\"$PROJECT_ROOT/vendor/bin:\$PATH\""
    local sail_alias="alias sail=\"'$SAIL_PATH'\""

    if [ -f "$shell_config" ]; then
        if grep -q "vendor/bin/sail" "$shell_config" 2>/dev/null; then
            info "Sail already configured in $(basename "$shell_config")"
        else
            echo "" >> "$shell_config"
            echo "# Laravel Sail" >> "$shell_config"
            echo "$sail_export" >> "$shell_config"
            echo "$sail_alias" >> "$shell_config"
            success "Sail configured in $(basename "$shell_config")"
        fi
    else
        info "Creating $shell_config with Sail configuration..."
        echo "# Laravel Sail" > "$shell_config"
        echo "$sail_export" >> "$shell_config"
        echo "$sail_alias" >> "$shell_config"
        success "Sail configured in $(basename "$shell_config")"
    fi
}

if [ -f "$SAIL_PATH" ]; then
    setup_sail_alias "$BASHRC"

    if [ -f "$ZSHRC" ]; then
        setup_sail_alias "$ZSHRC"
    fi

    success "Sail command is now available in new terminal sessions"
    info "To use Sail in current terminal, run: source ~/.bashrc"
else
    warn "Sail script not found at $SAIL_PATH"
fi

# Step 7: Summary
section "Setup Complete!"

echo -e "${GREEN}Your development environment is ready!${NC}\n"

echo -e "${CYAN}Access your application at:${NC}"
echo -e "  ${CYAN}Portal:${NC}              https://${PORTAL_HOST}"
echo -e "  ${CYAN}Admin:${NC}               https://${ADMIN_HOST}"
echo -e "  ${CYAN}Mailpit (Email):${NC}     http://${MAILPIT_HOST}:8025"
echo -e "  ${CYAN}MinIO (File Storage):${NC} http://${MINIO_HOST}:9000"
echo -e "  ${CYAN}MinIO Console:${NC}       http://${MINIO_CONSOLE_HOST}:8900"
echo -e "  ${CYAN}Traefik Dashboard:${NC}   http://localhost:8080"
echo ""

echo -e "${CYAN}Docker Compose Services:${NC}"
docker_compose_cmd ps || true
echo ""

echo -e "${CYAN}Next Steps:${NC}"
echo "  1. Review and configure .env file as needed"
echo "  2. Run migrations: php artisan migrate"
echo "  3. Seed the database: php artisan seed"
echo "  4. Start Vite dev server: npm run dev"
echo "  5. Start Laravel: php artisan octane:start (in another terminal)"
echo ""

echo -e "${YELLOW}Default Credentials:${NC}"
echo "  MinIO:"
echo "    Access Key: sail"
echo "    Secret Key: password"
echo ""

echo -e "${CYAN}Useful Commands:${NC}"
if command -v docker-compose > /dev/null 2>&1; then
    echo "  docker-compose logs -f [service]  - View service logs"
    echo "  docker-compose ps                 - List services status"
    echo "  docker-compose down               - Stop all services"
else
    echo "  docker compose logs -f [service]  - View service logs"
    echo "  docker compose ps                 - List services status"
    echo "  docker compose down               - Stop all services"
fi
echo ""

if [[ "$DOCKER_GROUP_UPDATED" == true ]]; then
    echo -e "${YELLOW}Important:${NC}"
    echo "  Docker group membership was updated for $CURRENT_USER."
    echo "  Run 'newgrp docker' or sign out and back in before using Docker without sudo."
    echo ""
fi

echo -e "${GREEN}Happy coding!${NC}"
echo ""
