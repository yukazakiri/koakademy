#!/usr/bin/env bash

# KoAkademy's tiny installer bootstrap. The installed command contains the
# Swarm lifecycle; this file resolves a stable release or the current edge
# commit and downloads the matching command.
set -Eeuo pipefail

repository="${KOAKADEMY_REPOSITORY:-yukazakiri/koakademy}"
requested_tag="${KOAKADEMY_VERSION:-}"
channel="stable"
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

resolve_edge_source_sha() {
    local commit sha

    commit="$(curl --fail --location --silent --show-error \
        "https://api.github.com/repos/${repository}/commits/master")" \
        || fail "Could not resolve the current KoAkademy master commit."
    sha="$(sed -nE 's/^[[:space:]]*"sha":[[:space:]]*"([0-9a-f]{40})".*/\1/p' <<<"${commit}" | head -n 1)"
    [[ "${sha}" =~ ^[0-9a-f]{40}$ ]] \
        || fail "GitHub returned an invalid KoAkademy master commit."
    printf '%s\n' "${sha}"
}

download_raw_asset() {
    local source_sha="$1"
    local asset="$2"
    local destination="$3"

    curl --fail --location --silent --show-error \
        "https://raw.githubusercontent.com/${repository}/${source_sha}/scripts/${asset}" \
        --output "${destination}" \
        || fail "Could not download edge asset ${asset} for ${source_sha}."
}

while (( $# > 0 )); do
    case "$1" in
        edge|--edge)
            [[ "${channel}" == stable ]] || fail "Choose either edge or --stable, not both."
            channel=edge
            shift
            ;;
        --stable)
            [[ "${channel}" == stable ]] || fail "Choose either edge or --stable, not both."
            channel=stable
            shift
            ;;
        install)
            shift
            ;;
        *)
            break
            ;;
    esac
done

if [[ "${channel}" == stable ]]; then
    tag="$(resolve_latest_tag)"
    [[ "${tag}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] \
        || fail "KoAkademy requires an exact stable vX.Y.Z release tag."
else
    source_sha="$(resolve_edge_source_sha)"
fi

if [[ -n "${KOAKADEMY_INSTALLER_TEST_STATE:-}" && -f "$(dirname "${BASH_SOURCE[0]}")/koakademy" ]]; then
    if [[ "${channel}" == stable ]]; then
        exec bash "$(dirname "${BASH_SOURCE[0]}")/koakademy" install --channel stable --release "${tag}" "$@"
    fi
    exec bash "$(dirname "${BASH_SOURCE[0]}")/koakademy" install --channel edge --source-sha "${source_sha}" "$@"
fi

temporary_directory="$(mktemp -d)"
command_path="${temporary_directory}/koakademy"

if [[ "${channel}" == stable ]]; then
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
else
    download_raw_asset "${source_sha}" koakademy "${command_path}"
fi
chmod 0755 "${command_path}"

if [[ "${channel}" == stable ]]; then
    exec "${command_path}" install --channel stable --release "${tag}" "$@"
fi
exec "${command_path}" install --channel edge --source-sha "${source_sha}" "$@"
