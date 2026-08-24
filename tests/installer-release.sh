#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
temporary_directory="$(mktemp -d)"
state_directory="$temporary_directory/state"
release_directory="$temporary_directory/release"
bootstrap_directory="$temporary_directory/bootstrap"
fixture_bin="$temporary_directory/bin"

cleanup() {
    rm -rf -- "$temporary_directory"
}
trap cleanup EXIT

mkdir -p "$release_directory" "$bootstrap_directory" "$fixture_bin"
cp "$repository_root/scripts/install.sh" "$bootstrap_directory/install.sh"
cp "$repository_root/.env.production.example" \
    "$repository_root/compose.production.yaml" \
    "$repository_root/scripts/install.sh" \
    "$repository_root/scripts/koakademy" \
    "$repository_root/scripts/swarm-stack.yml" \
    "$repository_root/scripts/Caddyfile" \
    "$repository_root/scripts/koakademy-app-entrypoint.sh" \
    "$release_directory/"

printf '%s\n' '{' \
    '  "version": "1.0.0",' \
    '  "image": "ghcr.io/yukazakiri/koakademy:sha-0123456789012345678901234567890123456789"' \
    '}' \
    >"$release_directory/version.json"

(cd "$release_directory" && sha256sum \
    .env.production.example compose.production.yaml install.sh koakademy \
    swarm-stack.yml Caddyfile koakademy-app-entrypoint.sh version.json \
    >SHA256SUMS)

bash "$repository_root/scripts/check-release-assets.sh" "$release_directory"

ln -s "$repository_root/tests/Fixtures/installer/docker" "$fixture_bin/docker"
ln -s "$repository_root/tests/Fixtures/installer/curl" "$fixture_bin/curl"

export KOAKADEMY_INSTALLER_TEST_STATE="$state_directory"
export KOAKADEMY_INSTALLER_TEST_RELEASE_DIR="$release_directory"
export KOAKADEMY_INSTALLER_TEST_RELEASE_TAG="v1.0.0"
export KOAKADEMY_ROOT="$state_directory/runtime"
export PATH="$fixture_bin:$PATH"

bash "$bootstrap_directory/install.sh" install --domain school.example

[[ -f "$state_directory/runtime/runtime.env" ]]
[[ -f "$state_directory/runtime/bin/koakademy" ]]
grep -Fq 'INSTALLATION_COMPLETE=true' "$state_directory/runtime/runtime.env"

printf 'Release-backed installer fixture passed.\n'
