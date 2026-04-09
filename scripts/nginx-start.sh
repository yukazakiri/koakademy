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
