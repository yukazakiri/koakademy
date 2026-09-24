#!/bin/sh
# KoAkademy seamless one-line installer bootstrap (Dokploy-style UX).
#
# The installed `koakademy` operator contains the Swarm lifecycle; this file
# only resolves the release to install, runs fast pre-flight checks, downloads
# the matching versioned operator, verifies its checksum, and hands control
# to it.
#
# Seamless usage (mirrors https://dokploy.com/install.sh):
#   curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | bash
#   curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | sh
#   curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | env KOAKADEMY_DOMAIN=school.example bash
#   curl -fsSL https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.sh | bash -s -- edge
#   KOAKADEMY_VERSION=vX.Y.Z bash install.sh [--domain school.example] [--port 8000]
#   DOKPLOY_VERSION-style pin: KOAKADEMY_VERSION=vX.Y.Z (exact stable tag)
#
# Channels:
#   stable (default) - latest published GitHub Release.
#   edge             - current master commit.
# Access (both channels): with --domain/KOAKADEMY_DOMAIN you get HTTPS via
# Caddy; without one you get direct self-hosted http://server-ip:PORT access.
set -eu

repository="${KOAKADEMY_REPOSITORY:-yukazakiri/koakademy}"
requested_tag="${KOAKADEMY_VERSION:-}"
channel="stable"
channel_explicit=""
command="install"
domain="${KOAKADEMY_DOMAIN:-}"
public_port="${KOAKADEMY_PUBLIC_PORT:-8000}"
port_explicit=""
release_flag=""
source_sha_flag=""
temporary_directory=""
# Args forwarded to the versioned operator (domain/port/channel normalized).
forward_args=""

# Dokploy parity: ADVERTISE_ADDR is the documented public-IP override there.
# Map it onto the operator's KOAKADEMY_ADVERTISE_ADDR when the native knob is
# unset so both spellings behave identically through sudo re-exec.
if [ -z "${KOAKADEMY_ADVERTISE_ADDR:-}" ] && [ -n "${ADVERTISE_ADDR:-}" ]; then
    KOAKADEMY_ADVERTISE_ADDR="${ADVERTISE_ADDR}"
    export KOAKADEMY_ADVERTISE_ADDR
fi

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

log_info() { printf "${GREEN}==> %s${NC}\n" "$*"; }
log_step() { printf "${BLUE}==> %s${NC}\n" "$*"; }
log_warn() { printf "${YELLOW}WARN: %s${NC}\n" "$*" >&2; }
log_error() { printf "${RED}ERROR: %s${NC}\n" "$*" >&2; }
fail() { log_error "$*"; exit 1; }

is_test() { test -n "${KOAKADEMY_INSTALLER_TEST_STATE:-}"; }
command_exists() { command -v "$1" >/dev/null 2>&1; }

usage() {
    cat <<EOF
KoAkademy seamless installer

Usage:
  curl -fsSL https://github.com/${repository}/releases/latest/download/install.sh | bash
  curl -fsSL https://github.com/${repository}/releases/latest/download/install.sh | env KOAKADEMY_DOMAIN=school.example bash
  bash install.sh [--domain school.example] [--port 8000] [--channel stable|edge]
  bash install.sh edge [--port 8000]
  bash install.sh update [--channel stable|edge]

Options:
  edge, --edge            Install the current master commit (direct HTTP port access)
  --stable                Install the latest stable release (default, HTTPS + domain)
  --channel stable|edge   Explicit channel selection
  --domain HOST           Optional HTTPS hostname (or KOAKADEMY_DOMAIN).
                          Without one, installs use direct http://server-ip:PORT access.
  --port PORT             Direct-access public port (default: 8000)
  --release vX.Y.Z        Pin an exact stable release (or KOAKADEMY_VERSION)
  --source-sha SHA        Pin an exact 40-char master commit for edge installs
  install                 Default command (optional, for Dokploy-style parity)
  update                  Download the operator then run 'koakademy update'
  --no-wait               Return once services are deployed; the app warms up
                          in the background (forwarded to the operator)
  -h, --help              Show this help
  -V, --version           Show the resolved release without installing

Environment:
  KOAKADEMY_VERSION       Exact stable tag (vX.Y.Z) to install instead of latest
  KOAKADEMY_REPOSITORY    GitHub owner/name (default: yukazakiri/koakademy)
  KOAKADEMY_DOMAIN        Optional HTTPS hostname for non-interactive installs.
                          When empty, installs use direct http://server-ip:PORT access.
  KOAKADEMY_ROOT          Install prefix (default: /opt/koakademy)
  KOAKADEMY_ADVERTISE_ADDR  Swarm advertise address override
  DOCKER_SWARM_INIT_ARGS  Extra args appended to 'docker swarm init'
  ADVERTISE_ADDR          Public-IP fallback override (Dokploy parity)
  ENDPOINT_MODE           Set to 'dnsrr' on kernels without IPVS (Proxmox LXC)
EOF
}

# Dokploy-style version detection: follow the /releases/latest redirect so we
# never touch the rate-limited api.github.com endpoint for the common case.
detect_stable_tag() {
    version_tag=""
    if [ -n "${requested_tag}" ]; then
        version_tag="${requested_tag}"
        printf '%s\n' "${version_tag}"
        return
    fi
    if [ -n "${release_flag}" ]; then
        printf '%s\n' "${release_flag}"
        return
    fi

    effective_url=""
    if command_exists curl; then
        effective_url="$(curl -fsSL --connect-timeout 10 -o /dev/null -w '%{url_effective}\n' \
            "https://github.com/${repository}/releases/latest" 2>/dev/null || true)"
    fi
    case "${effective_url}" in
        */tag/v[0-9]*)
            version_tag="${effective_url##*/tag/}"
            ;;
        *) version_tag="" ;;
    esac

    # Fallback to the GitHub API when the redirect probe fails (offline mirror,
    # curl fixture, or network hiccup). The fixture used by the installer tests
    # answers this endpoint without touching the network.
    if [ -z "${version_tag}" ]; then
        api_response=""
        api_response="$(curl --fail --location --silent --show-error \
            "https://api.github.com/repos/${repository}/releases/latest" 2>/dev/null || true)"
        version_tag="$(printf '%s' "${api_response}" \
            | sed -nE 's/^[[:space:]]*"tag_name":[[:space:]]*"([^"]+)".*/\1/p' \
            | head -n 1 || true)"
    fi

    printf '%s\n' "${version_tag}"
}

resolve_edge_source_sha() {
    if [ -n "${source_sha_flag}" ]; then
        printf '%s\n' "${source_sha_flag}"
        return
    fi
    commit=""
    commit="$(curl --fail --location --silent --show-error \
        "https://api.github.com/repos/${repository}/commits/master")" \
        || fail "Could not resolve the current KoAkademy master commit."
    sha="$(printf '%s' "${commit}" \
        | sed -nE 's/^[[:space:]]*"sha":[[:space:]]*"([0-9a-f]{40})".*/\1/p' \
        | head -n 1)"
    case "${sha}" in
        *[!0-9a-f]* | "") fail "GitHub returned an invalid KoAkademy master commit." ;;
    esac
    if [ "${#sha}" -ne 40 ]; then
        fail "GitHub returned an invalid KoAkademy master commit."
    fi
    printf '%s\n' "${sha}"
}

download_raw_asset() {
    src_sha="$1"
    asset="$2"
    destination="$3"
    curl --fail --location --silent --show-error \
        "https://raw.githubusercontent.com/${repository}/${src_sha}/scripts/${asset}" \
        --output "${destination}" \
        || fail "Could not download edge asset ${asset} for ${src_sha}."
}

is_proxmox_lxc() {
    if [ -n "${container:-}" ] && [ "${container}" = "lxc" ]; then
        return 0
    fi
    if grep -q "container=lxc" /proc/1/environ 2>/dev/null; then
        return 0
    fi
    return 1
}

check_port_free() {
    port="$1"
    hint="$2"
    if ! command_exists ss; then
        return 0
    fi
    if ss -ltn 2>/dev/null | awk '{print $4}' | grep -Eq "(:|\\.)${port}\$"; then
        fail "Port ${port} is already in use. ${hint}"
    fi
}

# ---- Argument parsing (POSIX; unknown flags are forwarded to the operator) ----
while [ $# -gt 0 ]; do
    case "$1" in
        -h | --help)
            usage
            exit 0
            ;;
        -V | --version)
            # Resolve and print only; useful for pinning checks in CI.
            if [ "${channel}" = "edge" ]; then
                resolve_edge_source_sha
            else
                detect_stable_tag
            fi
            exit 0
            ;;
        edge | --edge)
            if [ -n "${channel_explicit}" ] && [ "${channel}" != "edge" ]; then
                fail "Choose either edge or --stable, not both."
            fi
            channel="edge"
            channel_explicit="edge"
            shift
            ;;
        --stable)
            if [ -n "${channel_explicit}" ] && [ "${channel}" != "stable" ]; then
                fail "Choose either edge or --stable, not both."
            fi
            channel="stable"
            channel_explicit="stable"
            shift
            ;;
        --channel)
            [ $# -ge 2 ] || fail "--channel requires stable or edge."
            case "$2" in
                stable | edge) channel="$2" ;;
                *) fail "Channel must be stable or edge." ;;
            esac
            channel_explicit="${channel}"
            forward_args="${forward_args} --channel $2"
            shift 2
            ;;
        --channel=*)
            value="${1#--channel=}"
            case "${value}" in
                stable | edge) channel="${value}" ;;
                *) fail "Channel must be stable or edge." ;;
            esac
            channel_explicit="${channel}"
            forward_args="${forward_args} --channel ${value}"
            shift
            ;;
        --domain)
            [ $# -ge 2 ] || fail "--domain requires a hostname."
            domain="$2"
            forward_args="${forward_args} --domain $2"
            shift 2
            ;;
        --domain=*)
            domain="${1#--domain=}"
            forward_args="${forward_args} --domain ${domain}"
            shift
            ;;
        --port)
            [ $# -ge 2 ] || fail "--port requires a number."
            public_port="$2"
            port_explicit="flag"
            forward_args="${forward_args} --port $2"
            shift 2
            ;;
        --port=*)
            public_port="${1#--port=}"
            port_explicit="flag"
            forward_args="${forward_args} --port ${public_port}"
            shift
            ;;
        --release)
            [ $# -ge 2 ] || fail "--release requires a tag."
            release_flag="$2"
            forward_args="${forward_args} --release $2"
            shift 2
            ;;
        --release=*)
            release_flag="${1#--release=}"
            forward_args="${forward_args} --release ${release_flag}"
            shift
            ;;
        --source-sha)
            [ $# -ge 2 ] || fail "--source-sha requires a Git SHA."
            source_sha_flag="$2"
            forward_args="${forward_args} --source-sha $2"
            shift 2
            ;;
        --source-sha=*)
            source_sha_flag="${1#--source-sha=}"
            forward_args="${forward_args} --source-sha ${source_sha_flag}"
            shift
            ;;
        install)
            command="install"
            shift
            ;;
        update)
            # 'update' replaces the default install command; the verb itself is
            # carried by $command so it must not also land in forward_args.
            command="update"
            shift
            # Remaining args after 'update' are operator update flags.
            while [ $# -gt 0 ]; do
                case "$1" in
                    --channel)
                        [ $# -ge 2 ] || fail "--channel requires stable or edge."
                        case "$2" in
                            stable | edge) channel="$2" ;;
                            *) fail "Channel must be stable or edge." ;;
                        esac
                        channel_explicit="${channel}"
                        forward_args="${forward_args} --channel $2"
                        shift 2
                        ;;
                    --release | --port | --source-sha)
                        if [ "$1" = "--release" ]; then release_flag="$2"; fi
                        if [ "$1" = "--source-sha" ]; then source_sha_flag="$2"; fi
                        if [ "$1" = "--port" ]; then public_port="$2"; port_explicit="flag"; fi
                        forward_args="${forward_args} $1 $2"
                        shift 2
                        ;;
                    --release=* | --port=* | --source-sha=* | --stable | --edge)
                        case "$1" in
                            --stable) channel="stable"; channel_explicit="stable" ;;
                            --edge) channel="edge"; channel_explicit="edge" ;;
                            --release=*) release_flag="${1#--release=}" ;;
                            --source-sha=*) source_sha_flag="${1#--source-sha=}" ;;
                            --port=*) public_port="${1#--port=}"; port_explicit="flag" ;;
                        esac
                        forward_args="${forward_args} $1"
                        shift
                        ;;
                    --domain | --domain=*)
                        case "$1" in
                            --domain) domain="$2"; forward_args="${forward_args} --domain $2"; shift 2 ;;
                            *) domain="${1#--domain=}"; forward_args="${forward_args} $1"; shift ;;
                        esac
                        ;;
                    *) forward_args="${forward_args} $1"; shift ;;
                esac
            done
            break
            ;;
        --yes)
            forward_args="${forward_args} --yes"
            shift
            ;;
        --wait | --no-wait)
            forward_args="${forward_args} $1"
            shift
            ;;
        --) shift; break ;;
        -*) fail "Unknown option: $1 (see --help)." ;;
        *) break ;;
    esac
done
# Any remaining positional args belong to the operator (e.g. extra flags).
while [ $# -gt 0 ]; do
    forward_args="${forward_args} $1"
    shift
done

# Make KOAKADEMY_PUBLIC_PORT effective on direct-access installs: the operator
# only accepts --port, so promote the env value when no explicit flag was
# given. Direct access is decided by the absence of a domain on every channel.
if [ -z "${domain}" ] && [ -z "${port_explicit}" ] && [ -n "${KOAKADEMY_PUBLIC_PORT:-}" ]; then
    case "${forward_args}" in
        *" --port "* | *" --port="*) ;;
        *) forward_args="${forward_args} --port ${public_port}" ;;
    esac
fi

# ---- Seamless pre-flight checks (Dokploy parity, fail fast) ----
if ! is_test && [ "$(id -u 2>/dev/null || printf '0')" -ne 0 ]; then
    # Capture the invoking user before re-executing as root so the operator can
    # grant docker-group access, exactly like the previous bootstrap.
    if [ -z "${KOAKADEMY_INSTALLER_USER:-${SUDO_USER:-}}" ]; then
        if command_exists id; then
            KOAKADEMY_INSTALLER_USER="$(id -un)"
            export KOAKADEMY_INSTALLER_USER
        fi
    else
        KOAKADEMY_INSTALLER_USER="${KOAKADEMY_INSTALLER_USER:-${SUDO_USER:-}}"
        export KOAKADEMY_INSTALLER_USER
    fi
fi
if [ -n "${KOAKADEMY_INSTALLER_USER:-${SUDO_USER:-}}" ]; then
    export KOAKADEMY_INSTALLER_USER="${KOAKADEMY_INSTALLER_USER:-${SUDO_USER:-}}"
fi

# macOS / container guards (same wording family as Dokploy).
if command_exists uname && [ "$(uname)" = "Darwin" ]; then
    fail "This script must be run on Linux."
fi
if [ -f /.dockerenv ] && ! is_test; then
    fail "This script must be run on a Linux host, not inside a container."
fi
if is_proxmox_lxc && ! is_test; then
    log_warn "Detected Proxmox LXC container. If Swarm services fail with overlay networking errors, re-run with ENDPOINT_MODE=dnsrr."
    sleep 2
fi
if [ -n "${ENDPOINT_MODE:-}" ] && [ "${ENDPOINT_MODE}" != "dnsrr" ] && [ "${ENDPOINT_MODE}" != "" ]; then
    log_warn "Ignoring unknown ENDPOINT_MODE='${ENDPOINT_MODE}' (expected 'dnsrr' or empty)."
fi

# Validate the public port early so edge installs fail before downloading.
case "${public_port}" in
    '' | *[!0-9]* | 0*) fail "Public ports must be between 1 and 65535." ;;
esac
if [ "${public_port}" -lt 1 ] || [ "${public_port}" -gt 65535 ]; then
    fail "Public ports must be between 1 and 65535."
fi

# Port availability: a domain means Caddy HTTPS on 80/443; without one the
# install uses direct self-hosted HTTP access on the public port. The domain
# is optional, so decide by what is known up front (flag or environment).
if [ "${command}" = "install" ]; then
    if [ -n "${domain}" ]; then
        check_port_free 80 "Caddy needs ports 80 and 443."
        check_port_free 443 "Caddy needs ports 80 and 443."
    else
        # shellcheck disable=SC2086
        check_port_free "${public_port}" "Choose another port with --port."
    fi
fi

for required in curl sha256sum; do
    command_exists "${required}" || fail "Missing required command: ${required}"
done

# ---- Resolve the release (Dokploy-style "Installing version: X" output) ----
tag=""
source_sha=""
if [ "${channel}" = "stable" ]; then
    tag="$(detect_stable_tag)"
    # Strict vX.Y.Z check (POSIX case cannot express full semver, so verify).
    if ! printf '%s' "${tag}" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+$'; then
        fail "KoAkademy requires an exact stable vX.Y.Z release tag (got '${tag}'). Set KOAKADEMY_VERSION=vX.Y.Z or pass --release vX.Y.Z."
    fi
    log_info "Installing KoAkademy ${tag} (${channel})"
else
    source_sha="$(resolve_edge_source_sha)"
    log_info "Installing KoAkademy edge (${source_sha})"
fi

# Test hook: run the checked-in operator directly without network downloads.
# Uses $0 (POSIX) instead of BASH_SOURCE so 'sh install.sh' also works.
script_dir="$(dirname "$0")"
if is_test && [ -f "${script_dir}/koakademy" ]; then
    # shellcheck disable=SC2086
    if [ "${channel}" = "stable" ]; then
        # shellcheck disable=SC2086
        exec bash "${script_dir}/koakademy" "${command}" --channel stable --release "${tag}" ${forward_args}
    fi
    # shellcheck disable=SC2086
    exec bash "${script_dir}/koakademy" "${command}" --channel edge --source-sha "${source_sha}" ${forward_args}
fi

cleanup() {
    if [ -n "${temporary_directory}" ]; then
        rm -rf -- "${temporary_directory}"
    fi
}
trap cleanup EXIT

temporary_directory="$(mktemp -d)"
command_path="${temporary_directory}/koakademy"

if [ "${channel}" = "stable" ]; then
    checksums_path="${temporary_directory}/SHA256SUMS"
    log_step "Downloading KoAkademy ${tag} operator..."
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
    log_step "Downloading KoAkademy edge operator (${source_sha})..."
    download_raw_asset "${source_sha}" koakademy "${command_path}"
fi
chmod 0755 "${command_path}"

run_operator() {
    # Preserve the full installer environment across the sudo boundary so a
    # non-root one-liner ('curl ... | bash') behaves identically to root.
    # Dokploy simply requires root; we auto-elevate but keep every knob.
    if is_test || [ "$(id -u 2>/dev/null || printf '0')" -eq 0 ]; then
        # shellcheck disable=SC2086
        exec "${command_path}" "$@"
    fi

    command_exists sudo ||
        fail "Root privileges are required to configure Docker and Swarm. Install sudo or run this command as root."

    # Build 'sudo env K=V ... operator args' without bash arrays (POSIX).
    set -- "$command_path" "$@"
    # shellcheck disable=SC2086
    exec sudo env \
        "KOAKADEMY_INSTALLER_USER=${KOAKADEMY_INSTALLER_USER:-}" \
        ${KOAKADEMY_DOMAIN:+KOAKADEMY_DOMAIN="${KOAKADEMY_DOMAIN}"} \
        ${KOAKADEMY_ROOT:+KOAKADEMY_ROOT="${KOAKADEMY_ROOT}"} \
        ${KOAKADEMY_ADVERTISE_ADDR:+KOAKADEMY_ADVERTISE_ADDR="${KOAKADEMY_ADVERTISE_ADDR}"} \
        ${KOAKADEMY_VERSION:+KOAKADEMY_VERSION="${KOAKADEMY_VERSION}"} \
        ${KOAKADEMY_REPOSITORY:+KOAKADEMY_REPOSITORY="${KOAKADEMY_REPOSITORY}"} \
        ${KOAKADEMY_PUBLIC_PORT:+KOAKADEMY_PUBLIC_PORT="${KOAKADEMY_PUBLIC_PORT}"} \
        ${ADVERTISE_ADDR:+ADVERTISE_ADDR="${ADVERTISE_ADDR}"} \
        ${DOCKER_SWARM_INIT_ARGS:+DOCKER_SWARM_INIT_ARGS="${DOCKER_SWARM_INIT_ARGS}"} \
        ${ENDPOINT_MODE:+ENDPOINT_MODE="${ENDPOINT_MODE}"} \
        "$@"
}

# shellcheck disable=SC2086
if [ "${channel}" = "stable" ]; then
    # shellcheck disable=SC2086
    run_operator "${command}" --channel stable --release "${tag}" ${forward_args}
fi
# shellcheck disable=SC2086
run_operator "${command}" --channel edge --source-sha "${source_sha}" ${forward_args}
