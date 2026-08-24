#!/usr/bin/env bash

set -Eeuo pipefail

channel="${1:-stable}"
[[ "$channel" == stable || "$channel" == edge ]] || {
    printf 'Usage: %s [stable|edge]\n' "$0" >&2
    exit 2
}

source_sha="0123456789012345678901234567890123456789"
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
export KOAKADEMY_INSTALLER_TEST_SOURCE_SHA="$source_sha"
export KOAKADEMY_ROOT="$state_directory/runtime"
export PATH="$fixture_bin:$PATH"

if [[ "$channel" == stable ]]; then
    bash "$bootstrap_directory/install.sh" --stable --domain school.example
else
    bash "$bootstrap_directory/install.sh" edge --domain school.example
fi

[[ -f "$state_directory/runtime/runtime.env" ]]
[[ -f "$state_directory/runtime/bin/koakademy" ]]
grep -Fq 'INSTALLATION_COMPLETE=true' "$state_directory/runtime/runtime.env"
grep -Fq "RELEASE_CHANNEL=$channel" "$state_directory/runtime/runtime.env"
if [[ "$channel" == edge ]]; then
    grep -Fq "RELEASE_TAG=edge-$source_sha" "$state_directory/runtime/runtime.env"
    grep -Fq 'KOAKADEMY_IMAGE=ghcr.io/yukazakiri/koakademy:edge-frankenphp' \
        "$state_directory/runtime/runtime.env"
fi

printf '%s installer fixture passed.\n' "$channel"
