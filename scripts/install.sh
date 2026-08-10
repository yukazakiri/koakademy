#!/usr/bin/env bash

# KoAkademy's tiny release bootstrap. The installed command contains the
# Swarm lifecycle; this file deliberately only resolves and downloads it.
set -Eeuo pipefail

repository="${KOAKADEMY_REPOSITORY:-yukazakiri/koakademy}"
requested_tag="${KOAKADEMY_VERSION:-}"
temporary_directory=""

cleanup() {
    [[ -z "${temporary_directory}" ]] || rm -rf -- "${temporary_directory}"
}
trap cleanup EXIT

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

resolve_latest_tag() {
    local release tag

    if [[ -n "${requested_tag}" ]]; then
        printf '%s\n' "${requested_tag}"
        return
    fi

    release="$(curl --fail --location --silent --show-error \
        "https://api.github.com/repos/${repository}/releases/latest")" \
        || fail "Could not resolve the latest KoAkademy release."
    tag="$(sed -nE 's/^[[:space:]]*"tag_name":[[:space:]]*"([^"]+)".*/\1/p' <<<"${release}" | head -n 1)"
    printf '%s\n' "${tag}"
}

tag="$(resolve_latest_tag)"
[[ "${tag}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] \
    || fail "KoAkademy requires an exact stable vX.Y.Z release tag."

if [[ "${1:-}" == install ]]; then
    shift
fi

if [[ -n "${KOAKADEMY_INSTALLER_TEST_STATE:-}" && -f "$(dirname "${BASH_SOURCE[0]}")/koakademy" ]]; then
    exec bash "$(dirname "${BASH_SOURCE[0]}")/koakademy" install --release "${tag}" "$@"
fi

temporary_directory="$(mktemp -d)"
command_path="${temporary_directory}/koakademy"
checksums_path="${temporary_directory}/SHA256SUMS"

curl --fail --location --silent --show-error \
    "https://github.com/${repository}/releases/download/${tag}/koakademy" \
    --output "${command_path}" \
    || fail "Could not download the KoAkademy installer for ${tag}."
curl --fail --location --silent --show-error \
    "https://github.com/${repository}/releases/download/${tag}/SHA256SUMS" \
    --output "${checksums_path}" \
    || fail "Could not download release checksums for ${tag}."
(
    cd "${temporary_directory}"
    grep -E '[[:space:]]koakademy$' SHA256SUMS | sha256sum --check
) || fail "The KoAkademy installer checksum did not match ${tag}."
chmod 0755 "${command_path}"

exec "${command_path}" install --release "${tag}" "$@"
