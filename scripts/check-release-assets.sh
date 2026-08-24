#!/usr/bin/env bash

set -Eeuo pipefail

directory="${1:-release-assets}"
required_assets=(
    .env.production.example
    compose.production.yaml
    install.sh
    koakademy
    swarm-stack.yml
    swarm-stack-direct.yml
    Caddyfile
    koakademy-app-entrypoint.sh
    version.json
)

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[[ -d "$directory" ]] || fail "Release asset directory does not exist: $directory"
[[ -f "$directory/SHA256SUMS" ]] || fail "Release checksum manifest is missing."

for asset in "${required_assets[@]}"; do
    [[ -f "$directory/$asset" ]] || fail "Required release asset is missing: $asset"
    grep -Fq "  $asset" "$directory/SHA256SUMS" ||
        fail "Required release asset is missing from SHA256SUMS: $asset"
done

(cd "$directory" && sha256sum --check SHA256SUMS) ||
    fail "Release asset checksum verification failed."

printf 'Release asset contract passed for %s.\n' "$directory"
