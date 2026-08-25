#!/usr/bin/env bash

set -Eeuo pipefail

channel="${1:-stable}"
[[ "$channel" == stable || "$channel" == edge ]] || {
    printf 'Usage: %s [stable|edge]\n' "$0" >&2
    exit 2
}

source_sha="0123456789012345678901234567890123456789"
previous_source_sha="1111111111111111111111111111111111111111"
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
cp "$repository_root/compose.production.yaml" \
    "$repository_root/scripts/install.sh" \
    "$repository_root/scripts/koakademy" \
    "$repository_root/scripts/swarm-stack.yml" \
    "$repository_root/scripts/swarm-stack-direct.yml" \
    "$repository_root/scripts/Caddyfile" \
    "$repository_root/scripts/koakademy-app-entrypoint.sh" \
    "$release_directory/"
cp "$repository_root/.env.production.example" \
    "$release_directory/default.env.production.example"

printf '%s\n' '{' \
    '  "version": "1.0.0",' \
    '  "image": "ghcr.io/yukazakiri/koakademy:sha-0123456789012345678901234567890123456789"' \
    '}' \
    >"$release_directory/version.json"

(cd "$release_directory" && sha256sum \
    default.env.production.example compose.production.yaml install.sh koakademy \
    swarm-stack.yml swarm-stack-direct.yml Caddyfile koakademy-app-entrypoint.sh version.json \
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
base_path="$PATH"

if [[ "$channel" == stable ]]; then
    export KOAKADEMY_DOMAIN=school.example
    bash "$bootstrap_directory/install.sh"

    # The explicit --domain form must remain equivalent to the environment
    # form used by the no-argument one-line installer.
    flag_state="$temporary_directory/flag-state"
    unset KOAKADEMY_DOMAIN
    export KOAKADEMY_INSTALLER_TEST_STATE="$flag_state"
    export KOAKADEMY_ROOT="$flag_state/runtime"
    export KOAKADEMY_INSTALLER_TEST_SWARM_STATE=inactive
    export KOAKADEMY_ADVERTISE_ADDR=198.51.100.10

    # Mask the host Docker binary so the fixture exercises the official Docker
    # bootstrap path before the test fixture becomes available again.
    minimal_bin="$temporary_directory/minimal-bin"
    mkdir -p "$minimal_bin"
    for utility in bash sh env ln mkdir rm cp chmod sed sha256sum grep head awk \
        cut tr date openssl install stat sleep base64 dirname find mv mktemp cat; do
        utility_path="$(type -P "$utility")"
        ln -s "$utility_path" "$minimal_bin/$utility"
    done
    ln -s "$fixture_bin/curl" "$minimal_bin/curl"
    rm "$fixture_bin/docker"
    export KOAKADEMY_INSTALLER_TEST_DOCKER_SOURCE="$repository_root/tests/Fixtures/installer/docker"
    export KOAKADEMY_INSTALLER_TEST_DOCKER_TARGET="$minimal_bin/docker"
    export PATH="$minimal_bin"
    bash "$bootstrap_directory/install.sh" --domain flag.example
    export PATH="$base_path"
    ln -s "$repository_root/tests/Fixtures/installer/docker" "$fixture_bin/docker"
    unset KOAKADEMY_INSTALLER_TEST_DOCKER_SOURCE KOAKADEMY_INSTALLER_TEST_DOCKER_TARGET
    grep -Fq 'KOAKADEMY_DOMAIN=flag.example' "$flag_state/runtime/runtime.env"
    grep -Fq 'swarm init --advertise-addr 198.51.100.10' "$flag_state/docker.log"
    unset KOAKADEMY_INSTALLER_TEST_SWARM_STATE KOAKADEMY_ADVERTISE_ADDR

    # A piped, non-interactive stable install must fail with an actionable
    # domain instruction instead of silently selecting an HTTP-only default.
    missing_state="$temporary_directory/missing-state"
    export KOAKADEMY_INSTALLER_TEST_STATE="$missing_state"
    export KOAKADEMY_ROOT="$missing_state/runtime"
    export KOAKADEMY_INSTALLER_TEST_NONINTERACTIVE=1
    if bash "$bootstrap_directory/install.sh"; then
        printf 'Expected a non-interactive stable install without a domain to fail.\n' >&2
        exit 1
    fi
    unset KOAKADEMY_INSTALLER_TEST_NONINTERACTIVE

    # Restore the primary fixture state for the update lifecycle assertions.
    export KOAKADEMY_INSTALLER_TEST_STATE="$state_directory"
    export KOAKADEMY_ROOT="$state_directory/runtime"
    export KOAKADEMY_DOMAIN=school.example
    if grep -Fq 'swarm init' "$state_directory/docker.log"; then
        printf 'The active Swarm installation must not reinitialize the manager.\n' >&2
        exit 1
    fi
    export KOAKADEMY_INSTALLER_TEST_IMAGE="ghcr.io/yukazakiri/koakademy:sha-1111111111111111111111111111111111111111"
    bash "$state_directory/runtime/bin/koakademy" update --stable
    unset KOAKADEMY_INSTALLER_TEST_IMAGE
else
    export KOAKADEMY_INSTALLER_TEST_SOURCE_SHA="$previous_source_sha"
    export KOAKADEMY_INSTALLER_TEST_FAIL_FIRST_STACK=1
    if bash "$bootstrap_directory/install.sh" edge; then
        printf 'Expected the first edge deployment to be interrupted.\n' >&2
        exit 1
    fi
    sed -i 's/^MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=no-reply@localhost/' \
        "$state_directory/runtime/runtime.env"
    unset KOAKADEMY_INSTALLER_TEST_FAIL_FIRST_STACK
    export KOAKADEMY_INSTALLER_TEST_SOURCE_SHA="$source_sha"
    bash "$bootstrap_directory/install.sh" edge
fi

[[ -f "$state_directory/runtime/runtime.env" ]]
[[ -f "$state_directory/runtime/bin/koakademy" ]]
grep -Fq 'INSTALLATION_COMPLETE=true' "$state_directory/runtime/runtime.env"
grep -Fq "RELEASE_CHANNEL=$channel" "$state_directory/runtime/runtime.env"
grep -Fq -- '--env DB_CONNECTION=pgsql' "$state_directory/docker.log"
grep -Fq -- '--env DB_HOST=postgres' "$state_directory/docker.log"
grep -Fq -- '--env REDIS_HOST=redis' "$state_directory/docker.log"
grep -Fq -- '--env TELESCOPE_ENABLED=false' "$state_directory/docker.log"
grep -Fq 'KOAKADEMY_NETWORK=koakademy_private' "$state_directory/runtime/runtime.env"
grep -Fq 'AUTO_MIGRATE=true' "$state_directory/runtime/runtime.env"
test -f "$state_directory/networks/koakademy_private"
if [[ "$channel" == edge ]]; then
    grep -Fq "RELEASE_TAG=edge-$source_sha" "$state_directory/runtime/runtime.env"
    grep -Fq 'KOAKADEMY_DIRECT_ACCESS=true' "$state_directory/runtime/runtime.env"
    grep -Fq 'KOAKADEMY_PUBLIC_PORT=8000' "$state_directory/runtime/runtime.env"
    grep -Fq 'APP_URL=http://127.0.0.1:8000' "$state_directory/runtime/runtime.env"
    grep -Fq 'MAIL_FROM_ADDRESS=no-reply@koakademy.localhost' \
        "$state_directory/runtime/runtime.env"
    grep -Fq 'KOAKADEMY_IMAGE=ghcr.io/yukazakiri/koakademy:edge-frankenphp' \
        "$state_directory/runtime/runtime.env"
    grep -Fq 'swarm-stack-direct.yml' "$state_directory/docker.log"
    grep -Fq "releases/edge-$source_sha/swarm-stack-direct.yml" \
        "$state_directory/docker.log"
else
    compgen -G "$state_directory/runtime/backups/*.dump" >/dev/null
fi

printf '%s installer fixture passed.\n' "$channel"
