#!/usr/bin/env sh
set -e

ROOT="${ROOT:-/app}"
exec /bin/sh "${ROOT}/docker/docker-scripts/scout-index.sh" "$@"
