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
