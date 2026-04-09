#!/usr/bin/env bash
#
# Setup Sail Alias Script for Linux/macOS
# This script adds a 'sail' alias to ALL shell configurations (bash, zsh, fish)
#
# Usage:
#   chmod +x setup-sail-alias.sh
#   ./setup-sail-alias.sh
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored messages
info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Detect the current shell
detect_shell() {
    if [ -n "$BASH_VERSION" ]; then
        echo "bash"
    elif [ -n "$ZSH_VERSION" ]; then
        echo "zsh"
    elif [ -n "$FISH_VERSION" ]; then
        echo "fish"
    else
        # Fallback detection methods
        if [ -n "$SHELL" ]; then
            case "$SHELL" in
                *bash*) echo "bash" ;;
                *zsh*) echo "zsh" ;;
                *fish*) echo "fish" ;;
                *) echo "unknown" ;;
            esac
        else
            echo "unknown"
        fi
    fi
}

# Get shell configuration file path
get_config_file() {
    local shell_type="$1"

    case "$shell_type" in
        bash)
            # Check for .bashrc first (Ubuntu, Debian), then .bash_profile (macOS, RedHat)
            if [ -f "$HOME/.bashrc" ]; then
                echo "$HOME/.bashrc"
            elif [ -f "$HOME/.bash_profile" ]; then
                echo "$HOME/.bash_profile"
            elif [ -f "$HOME/.profile" ]; then
                echo "$HOME/.profile"
            else
                echo "$HOME/.bashrc"
            fi
            ;;
        zsh)
            echo "$HOME/.zshrc"
            ;;
        fish)
            echo "$HOME/.config/fish/config.fish"
            ;;
        *)
            echo ""
            ;;
    esac
}

# Check if alias already exists
alias_exists() {
    local config_file="$1"
    local shell_type="$2"

    if [ ! -f "$config_file" ]; then
        return 1
    fi

    if grep -q "alias sail=" "$config_file" 2>/dev/null; then
        return 0
    else
        return 1
    fi
}

# Add alias to bash/zsh shell
add_alias_to_shell() {
    local config_file="$1"
    local shell_name="$2"

    # Create directory if it doesn't exist
    local config_dir
    config_dir=$(dirname "$config_file")
    if [ ! -d "$config_dir" ]; then
        mkdir -p "$config_dir"
    fi

    # Create backup if file exists
    if [ -f "$config_file" ]; then
        cp "$config_file" "${config_file}.backup.$(date +%Y%m%d_%H%M%S)"
        info "Created backup: ${config_file}.backup.$(date +%Y%m%d_%H%M%S)"
    fi

    # Add the alias
    echo "" >> "$config_file"
    echo "# Laravel Sail alias - added by setup-sail-alias.sh" >> "$config_file"
    echo "alias sail='[ -f sail ] && ./sail || vendor/bin/sail'" >> "$config_file"

    success "Sail alias added to $shell_name ($config_file)"
}

# Setup for Fish shell
setup_fish() {
    local config_file="$1"
    local fish_config_dir=$(dirname "$config_file")

    # Create fish config directory if it doesn't exist
    if [ ! -d "$fish_config_dir" ]; then
        mkdir -p "$fish_config_dir"
    fi

    # Create backup
    if [ -f "$config_file" ]; then
        cp "$config_file" "${config_file}.backup.$(date +%Y%m%d_%H%M%S)"
        info "Created backup: ${config_file}.backup.$(date +%Y%m%d_%H%M%S)"
    fi

    # Add the function and alias for Fish
    echo "" >> "$config_file"
    echo "# Laravel Sail alias - added by setup-sail-alias.sh" >> "$config_file"
    echo "function sail" >> "$config_file"
    echo "    if test -f sail" >> "$config_file"
    echo "        ./sail \$argv" >> "$config_file"
    echo "    else" >> "$config_file"
    echo "        vendor/bin/sail \$argv" >> "$config_file"
    echo "    end" >> "$config_file"
    echo "end" >> "$config_file"

    success "Sail function added to Fish ($config_file)"
}

# Print usage instructions
print_instructions() {
    local shell_type="$1"

    echo ""
    success "Sail alias setup complete!"
    echo ""
    echo "Next steps:"
    echo "───────────"
    case "$shell_type" in
        fish)
            echo "1. Restart your terminal or run: source ~/.config/fish/config.fish"
            ;;
        *)
            echo "1. Restart your terminal or run: source $(get_config_file "$shell_type")"
            ;;
    esac
    echo "2. Test the alias: sail --version"
    echo ""
    echo "The alias will work in all new terminals."
    echo ""
}

# Main execution
main() {
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║         Laravel Sail Alias Setup for Linux/macOS           ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""

    # Detect shell
    local shell_type
    shell_type=$(detect_shell)

    info "Detected shell: $shell_type"
    echo ""
    info "The Sail alias will be added to ALL shell configurations:"
    info "  - bash (~/.bashrc or ~/.bash_profile)"
    info "  - zsh (~/.zshrc)"
    info "  - fish (~/.config/fish/config.fish)"
    echo ""

    # Array of shells to configure
    declare -A shells=(
        ["bash"]="$HOME/.bashrc"
        ["zsh"]="$HOME/.zshrc"
        ["fish"]="$HOME/.config/fish/config.fish"
    )

    # Check if any shell already has the alias
    local found_existing=false
    for shell_name in "${!shells[@]}"; do
        config_file="${shells[$shell_name]}"
        if [ -f "$config_file" ] && alias_exists "$config_file" "$shell_name"; then
            found_existing=true
            warn "Sail alias already exists in $shell_name ($config_file)"
        fi
    done

    if [ "$found_existing" = true ]; then
        echo ""
        read -p "Do you want to update existing aliases? (y/N): " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            info "Aborted by user"
            exit 0
        fi
    fi

    # Add alias to all shells
    echo ""
    info "Adding Sail alias to all shell configurations..."
    echo ""

    for shell_name in "${!shells[@]}"; do
        config_file="${shells[$shell_name]}"
        if [ "$shell_name" = "fish" ]; then
            setup_fish "$config_file"
        else
            add_alias_to_shell "$config_file" "$shell_name"
        fi
    done

    # Print instructions
    print_instructions "$shell_type"
}

# Run main function
main
