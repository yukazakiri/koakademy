#!/usr/bin/env bash

set -Eeuo pipefail

readonly KOAKADEMY_REPOSITORY="yukazakiri/koakademy"
readonly RUSTFS_REPOSITORY="rustfs/rustfs"
readonly INSTALLER_LABEL="com.koakademy.managed-by=swarm-installer"
readonly NETWORK_NAME="koakademy-network"
readonly APP_SERVICE="koakademy-app"
readonly POSTGRES_SERVICE="koakademy-postgres"
readonly REDIS_SERVICE="koakademy-redis"
readonly GOTENBERG_SERVICE="koakademy-gotenberg"
readonly RUSTFS_SERVICE="koakademy-rustfs"
readonly APP_VOLUME="koakademy-app-storage"
readonly POSTGRES_VOLUME="koakademy-postgres-data"
readonly REDIS_VOLUME="koakademy-redis-data"
readonly RUSTFS_VOLUME="koakademy-rustfs-data"
readonly APP_KEY_SECRET="koakademy-app-key"
readonly DB_PASSWORD_SECRET="koakademy-db-password"
readonly REDIS_PASSWORD_SECRET="koakademy-redis-password"
readonly S3_ACCESS_KEY_SECRET="koakademy-s3-access-key"
readonly S3_SECRET_KEY_SECRET="koakademy-s3-secret-key"
readonly APP_ENTRYPOINT_CONFIG="koakademy-app-entrypoint-v1"
readonly REDIS_ENTRYPOINT_CONFIG="koakademy-redis-entrypoint-v1"
readonly STORAGE_INIT_CONFIG="koakademy-storage-init-v1"
readonly POSTGRES_IMAGE="${KOAKADEMY_POSTGRES_IMAGE:-postgres:18-alpine}"
readonly REDIS_IMAGE="${KOAKADEMY_REDIS_IMAGE:-redis:8-alpine}"
readonly GOTENBERG_IMAGE="${KOAKADEMY_GOTENBERG_IMAGE:-gotenberg/gotenberg:8}"
readonly AWS_CLI_IMAGE="${KOAKADEMY_AWS_CLI_IMAGE:-amazon/aws-cli:2}"
readonly ALPINE_IMAGE="${KOAKADEMY_ALPINE_IMAGE:-alpine:3.22}"

APP_PORT="${KOAKADEMY_APP_PORT:-8000}"
RUSTFS_PORT="${KOAKADEMY_RUSTFS_PORT:-9000}"
APP_URL="${KOAKADEMY_APP_URL:-}"
STORAGE_MODE="${KOAKADEMY_STORAGE:-}"
KOAKADEMY_VERSION="${KOAKADEMY_VERSION:-}"
RUSTFS_VERSION="${RUSTFS_VERSION:-}"
CURRENT_NODE=""
APP_HOST=""
SESSION_SECURE_COOKIE="false"
AWS_ACCESS_KEY_ID_VALUE=""
AWS_SECRET_ACCESS_KEY_VALUE=""
AWS_DEFAULT_REGION_VALUE=""
AWS_BUCKET_VALUE=""
AWS_ENDPOINT_VALUE=""
AWS_URL_VALUE=""
AWS_USE_PATH_STYLE_VALUE="true"
TEMP_ENV_FILE=""

log() {
    printf '%s\n' "$*"
}

info() {
    printf '==> %s\n' "$*"
}

warn() {
    printf 'WARNING: %s\n' "$*" >&2
}

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

cleanup() {
    if [[ -n "${TEMP_ENV_FILE}" && -f "${TEMP_ENV_FILE}" ]]; then
        chmod 600 "${TEMP_ENV_FILE}" 2>/dev/null || true
        rm -f -- "${TEMP_ENV_FILE}"
    fi
}

trap cleanup EXIT

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

require_command() {
    command_exists "$1" || fail "Required command '$1' was not found."
}

validate_port() {
    local value="$1"
    local label="$2"

    [[ "${value}" =~ ^[0-9]+$ ]] || fail "${label} must be a numeric TCP port."
    (( value >= 1 && value <= 65535 )) || fail "${label} must be between 1 and 65535."
}

validate_tag() {
    local value="$1"
    local label="$2"

    [[ "${value}" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9][A-Za-z0-9.-]*)?$ ]] \
        || fail "${label} '${value}' is not a safe container tag."
}

validate_http_url() {
    local value="$1"
    local label="$2"

    [[ "${value}" != *$'\n'* && "${value}" != *$'\r'* ]] \
        || fail "${label} must not contain line breaks."
    [[ "${value}" =~ ^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[^[:space:]\"\'\\]*)?$ ]] \
        || fail "${label} must be an http(s) URL with a hostname or IPv4 address."
}

validate_app_url() {
    local value="$1"

    validate_http_url "${value}" "Application URL"
    [[ "${value}" =~ ^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?/?$ ]] \
        || fail "Application URL must contain only the scheme, hostname/IP, and optional port."
}

validate_bucket() {
    local value="$1"

    (( ${#value} >= 3 && ${#value} <= 63 )) \
        || fail "S3 bucket names must contain between 3 and 63 characters."
    [[ "${value}" =~ ^[a-z0-9][a-z0-9.-]*[a-z0-9]$ ]] \
        || fail "S3 bucket names may contain lowercase letters, digits, dots, and hyphens."
}

validate_region() {
    local value="$1"

    [[ "${value}" =~ ^[A-Za-z0-9-]+$ ]] || fail "S3 region contains unsupported characters."
}

validate_single_line() {
    local value="$1"
    local label="$2"

    [[ "${value}" != *$'\n'* && "${value}" != *$'\r'* ]] \
        || fail "${label} must not contain line breaks."
}

prompt() {
    local variable_name="$1"
    local message="$2"
    local default_value="${3:-}"
    local value=""

    if [[ -n "${default_value}" ]]; then
        read -r -p "${message} [${default_value}]: " value </dev/tty
        value="${value:-${default_value}}"
    else
        read -r -p "${message}: " value </dev/tty
    fi

    printf -v "${variable_name}" '%s' "${value}"
}

prompt_secret() {
    local variable_name="$1"
    local message="$2"
    local value=""

    read -r -s -p "${message}: " value </dev/tty
    printf '\n' >/dev/tty
    [[ -n "${value}" ]] || fail "${message} cannot be empty."
    [[ "${value}" != *$'\n'* && "${value}" != *$'\r'* ]] \
        || fail "${message} must not contain line breaks."
    printf -v "${variable_name}" '%s' "${value}"
}

prompt_yes_no() {
    local message="$1"
    local default_value="${2:-y}"
    local answer=""
    local suffix="[Y/n]"

    [[ "${default_value}" == "n" ]] && suffix="[y/N]"
    read -r -p "${message} ${suffix}: " answer </dev/tty
    answer="${answer:-${default_value}}"

    [[ "${answer}" =~ ^[Yy]$ ]]
}

random_hex() {
    local bytes="${1:-32}"

    od -An -N "${bytes}" -tx1 /dev/urandom | tr -d ' \n'
}

generate_app_key() {
    printf 'base64:%s' "$(head -c 32 /dev/urandom | base64 | tr -d '\r\n')"
}

github_tags() {
    local repository="$1"
    local page=1
    local response=""
    local names=""
    local found=0
    local authorization_config=""

    if [[ -n "${GITHUB_TOKEN:-}" ]]; then
        [[ "${GITHUB_TOKEN}" =~ ^[A-Za-z0-9_]+$ ]] \
            || fail "GITHUB_TOKEN contains unsupported characters."
        authorization_config="header = \"Authorization: Bearer ${GITHUB_TOKEN}\""
    fi

    while (( page <= 10 )); do
        if [[ -n "${authorization_config}" ]]; then
            response="$(printf '%s\n' "${authorization_config}" \
                | curl --config - -fsSL --connect-timeout 10 \
                    -H 'Accept: application/vnd.github+json' \
                    "https://api.github.com/repos/${repository}/tags?per_page=100&page=${page}" 2>/dev/null)" \
                || break
        else
            response="$(curl -fsSL --connect-timeout 10 \
                -H 'Accept: application/vnd.github+json' \
                "https://api.github.com/repos/${repository}/tags?per_page=100&page=${page}" 2>/dev/null)" \
                || break
        fi

        names="$(printf '%s' "${response}" | sed -n 's/^[[:space:]]*"name":[[:space:]]*"\([^"]*\)",*$/\1/p')"
        [[ -n "${names}" ]] || break
        printf '%s\n' "${names}"
        found=1

        if (( $(printf '%s\n' "${names}" | wc -l) < 100 )); then
            break
        fi

        page=$((page + 1))
    done

    if (( found == 0 )); then
        curl -fsSL --connect-timeout 10 "https://github.com/${repository}/tags.atom" 2>/dev/null \
            | sed -n 's#.*<id>tag:github.com,2008:Repository/[0-9][0-9]*/\([^<]*\)</id>.*#\1#p'
    fi
}

github_latest_published_release() {
    local repository="$1"
    local response=""
    local candidate=""
    local redirected=""
    local authorization_config=""

    if [[ -n "${GITHUB_TOKEN:-}" ]]; then
        [[ "${GITHUB_TOKEN}" =~ ^[A-Za-z0-9_]+$ ]] \
            || fail "GITHUB_TOKEN contains unsupported characters."
        authorization_config="header = \"Authorization: Bearer ${GITHUB_TOKEN}\""
    fi

    if [[ -n "${authorization_config}" ]]; then
        response="$(printf '%s\n' "${authorization_config}" |
            curl --config - -fsSL --connect-timeout 10 \
                -H 'Accept: application/vnd.github+json' \
                "https://api.github.com/repos/${repository}/releases/latest" 2>/dev/null)" \
            || response=""
    else
        response="$(curl -fsSL --connect-timeout 10 \
            -H 'Accept: application/vnd.github+json' \
            "https://api.github.com/repos/${repository}/releases/latest" 2>/dev/null)" \
            || response=""
    fi

    candidate="$(printf '%s' "${response}" |
        sed -n 's/.*"tag_name":[[:space:]]*"\([^"]*\)".*/\1/p' |
        head -n 1)"
    if [[ "${candidate}" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]] &&
       grep -q '"draft":[[:space:]]*false' <<<"${response}" &&
       grep -q '"prerelease":[[:space:]]*false' <<<"${response}"; then
        printf '%s' "${candidate}"
        return
    fi

    redirected="$(curl -fsSL --connect-timeout 10 -o /dev/null -w '%{url_effective}' \
        "https://github.com/${repository}/releases/latest" 2>/dev/null || true)"
    candidate="${redirected##*/tag/}"
    if [[ "${redirected}" == *"/releases/tag/"* ]] &&
       [[ "${candidate}" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        printf '%s' "${candidate}"
    fi
}

resolve_latest_koakademy_version() {
    local candidate=""

    if [[ -n "${KOAKADEMY_VERSION}" ]]; then
        if [[ "${KOAKADEMY_VERSION}" == "edge" ]]; then
            warn "KOAKADEMY_VERSION=edge selects the unsupported rolling channel."
            warn "The edge image can change after every green master build; pin an exact vX.Y.Z tag for production."
            printf '%s' "${KOAKADEMY_VERSION}"
            return
        fi
        validate_tag "${KOAKADEMY_VERSION}" "KOAKADEMY_VERSION"
        printf '%s' "${KOAKADEMY_VERSION}"
        return
    fi

    info "Detecting the latest published stable KoAkademy release from GitHub..." >&2
    candidate="$(github_latest_published_release "${KOAKADEMY_REPOSITORY}")"

    [[ -n "${candidate}" ]] || fail \
        "No published stable KoAkademy release could be resolved. The repository must be public, or set KOAKADEMY_VERSION explicitly."
    validate_tag "${candidate}" "Resolved KoAkademy version"
    printf '%s' "${candidate}"
}

resolve_latest_rustfs_version() {
    local tags=""
    local candidate=""
    local candidates=""

    if [[ -n "${RUSTFS_VERSION}" ]]; then
        validate_tag "${RUSTFS_VERSION}" "RUSTFS_VERSION"
        printf '%s' "${RUSTFS_VERSION#v}"
        return
    fi

    info "Detecting the latest non-preview RustFS tag from GitHub..." >&2
    tags="$(github_tags "${RUSTFS_REPOSITORY}")"
    candidates="$(printf '%s\n' "${tags}" \
        | sed -nE '/^v?[0-9]+\.[0-9]+\.[0-9]+$/p' \
        | sort -Vr)"

    if [[ -z "${candidates}" ]]; then
        candidates="$(printf '%s\n' "${tags}" \
            | sed -nE '/^v?[0-9]+\.[0-9]+\.[0-9]+-beta\.[0-9]+$/p' \
            | sort -Vr)"
    fi

    [[ -n "${candidates}" ]] || fail \
        "No stable or non-preview RustFS tag could be resolved. Set RUSTFS_VERSION explicitly."

    while IFS= read -r candidate; do
        validate_tag "${candidate}" "Resolved RustFS version"
        if docker manifest inspect "rustfs/rustfs:${candidate#v}" >/dev/null 2>&1; then
            printf '%s' "${candidate#v}"
            return
        fi
        warn "Skipping RustFS ${candidate}: its Docker image is not published yet."
    done <<<"${candidates}"

    fail "GitHub returned RustFS tags, but none has a published rustfs/rustfs image."
}

detect_access_host() {
    local private_ip=""

    if command_exists ip; then
        private_ip="$(ip -o -4 addr show scope global 2>/dev/null \
            | awk '$2 !~ /^(docker|br-|veth)/ {print $4}' \
            | cut -d/ -f1 \
            | head -n 1)"
    fi

    if [[ "${private_ip}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        printf '%s' "${private_ip}"
    else
        printf 'localhost'
    fi
}

docker_object_exists() {
    local type="$1"
    local name="$2"

    docker "${type}" inspect "${name}" >/dev/null 2>&1
}

docker_object_is_installer_managed() {
    local type="$1"
    local name="$2"
    local label=""

    case "${type}" in
        service|secret|config)
            label="$(docker "${type}" inspect --format \
                '{{index .Spec.Labels "com.koakademy.managed-by"}}' "${name}" 2>/dev/null || true)"
            ;;
        network|volume)
            label="$(docker "${type}" inspect --format \
                '{{index .Labels "com.koakademy.managed-by"}}' "${name}" 2>/dev/null || true)"
            ;;
        *)
            fail "Unsupported Docker object type '${type}'."
            ;;
    esac

    [[ "${label}" == "swarm-installer" ]]
}

service_is_installer_managed() {
    local name="$1"

    docker_object_is_installer_managed service "${name}"
}

ensure_secret_value() {
    local name="$1"
    local value="$2"

    if docker_object_exists secret "${name}"; then
        docker_object_is_installer_managed secret "${name}" \
            || fail "Docker secret ${name} exists but is not installer-managed."
        info "Reusing Docker secret ${name}."
        return
    fi

    [[ -n "${value}" ]] || fail "Cannot create Docker secret ${name} from an empty value."
    validate_single_line "${value}" "Docker secret ${name}"
    printf '%s' "${value}" | docker secret create \
        --label "${INSTALLER_LABEL}" \
        "${name}" - >/dev/null
    info "Created Docker secret ${name}."
}

ensure_generated_secret() {
    local name="$1"
    local kind="$2"
    local value=""

    if docker_object_exists secret "${name}"; then
        docker_object_is_installer_managed secret "${name}" \
            || fail "Docker secret ${name} exists but is not installer-managed."
        info "Reusing Docker secret ${name}."
        return
    fi

    if [[ "${kind}" == "app-key" ]]; then
        value="$(generate_app_key)"
    else
        value="$(random_hex 32)"
    fi

    ensure_secret_value "${name}" "${value}"
}

ensure_configs() {
    if ! docker_object_exists config "${APP_ENTRYPOINT_CONFIG}"; then
        docker config create --label "${INSTALLER_LABEL}" "${APP_ENTRYPOINT_CONFIG}" - >/dev/null <<'EOF'
#!/bin/sh
set -eu

load_secret() {
    variable_name="$1"
    secret_path="$2"
    value="$(cat "$secret_path")"
    export "$variable_name=$value"
}

load_secret APP_KEY /run/secrets/koakademy-app-key
load_secret DB_PASSWORD /run/secrets/koakademy-db-password
load_secret REDIS_PASSWORD /run/secrets/koakademy-redis-password
load_secret AWS_ACCESS_KEY_ID /run/secrets/koakademy-s3-access-key
load_secret AWS_SECRET_ACCESS_KEY /run/secrets/koakademy-s3-secret-key

exec /usr/local/bin/start-container "$@"
EOF
        info "Created application secret-loader config."
    elif ! docker_object_is_installer_managed config "${APP_ENTRYPOINT_CONFIG}"; then
        fail "Docker config ${APP_ENTRYPOINT_CONFIG} exists but is not installer-managed."
    fi

    if ! docker_object_exists config "${REDIS_ENTRYPOINT_CONFIG}"; then
        docker config create --label "${INSTALLER_LABEL}" "${REDIS_ENTRYPOINT_CONFIG}" - >/dev/null <<'EOF'
#!/bin/sh
set -eu

redis_password="$(cat /run/secrets/koakademy-redis-password)"
exec redis-server --appendonly yes --requirepass "$redis_password"
EOF
        info "Created Redis secret-loader config."
    elif ! docker_object_is_installer_managed config "${REDIS_ENTRYPOINT_CONFIG}"; then
        fail "Docker config ${REDIS_ENTRYPOINT_CONFIG} exists but is not installer-managed."
    fi

    if ! docker_object_exists config "${STORAGE_INIT_CONFIG}"; then
        docker config create --label "${INSTALLER_LABEL}" "${STORAGE_INIT_CONFIG}" - >/dev/null <<'EOF'
#!/bin/sh
set -eu

export AWS_ACCESS_KEY_ID="$(cat /run/secrets/koakademy-s3-access-key)"
export AWS_SECRET_ACCESS_KEY="$(cat /run/secrets/koakademy-s3-secret-key)"
export AWS_EC2_METADATA_DISABLED=true

aws_s3() {
    aws --no-cli-pager --endpoint-url "$AWS_ENDPOINT" "$@"
}

attempt=0
until aws_s3 s3api head-bucket --bucket "$AWS_BUCKET" >/dev/null 2>&1; do
    attempt=$((attempt + 1))

    if [ "$STORAGE_MODE" = "rustfs" ]; then
        if aws_s3 s3api create-bucket --bucket "$AWS_BUCKET" >/dev/null 2>&1; then
            break
        fi
    fi

    if [ "$attempt" -ge 60 ]; then
        echo "Unable to access S3 bucket '$AWS_BUCKET' at '$AWS_ENDPOINT'." >&2
        exit 1
    fi

    sleep 2
done

if [ "$STORAGE_MODE" = "rustfs" ]; then
    policy="$(printf '{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Principal":"*","Action":["s3:GetObject"],"Resource":["arn:aws:s3:::%s/*"]}]}' "$AWS_BUCKET")"
    cors="$(printf '{"CORSRules":[{"AllowedHeaders":["*"],"AllowedMethods":["GET","HEAD"],"AllowedOrigins":["%s"],"ExposeHeaders":["ETag"],"MaxAgeSeconds":3600}]}' "$APP_ORIGIN")"
    aws_s3 s3api put-bucket-policy --bucket "$AWS_BUCKET" --policy "$policy"
    aws_s3 s3api put-bucket-cors --bucket "$AWS_BUCKET" --cors-configuration "$cors"
fi

echo "S3 bucket '$AWS_BUCKET' is ready."
EOF
        info "Created storage initialization config."
    elif ! docker_object_is_installer_managed config "${STORAGE_INIT_CONFIG}"; then
        fail "Docker config ${STORAGE_INIT_CONFIG} exists but is not installer-managed."
    fi
}

ensure_network() {
    local driver=""
    local scope=""
    local attachable=""

    if docker_object_exists network "${NETWORK_NAME}"; then
        docker_object_is_installer_managed network "${NETWORK_NAME}" \
            || fail "Docker network ${NETWORK_NAME} exists but is not installer-managed."
        driver="$(docker network inspect --format '{{.Driver}}' "${NETWORK_NAME}")"
        scope="$(docker network inspect --format '{{.Scope}}' "${NETWORK_NAME}")"
        attachable="$(docker network inspect --format '{{.Attachable}}' "${NETWORK_NAME}")"
        [[ "${driver}" == "overlay" && "${scope}" == "swarm" && "${attachable}" == "true" ]] \
            || fail "Existing network ${NETWORK_NAME} is not an attachable Swarm overlay."
        info "Reusing network ${NETWORK_NAME}."
        return
    fi

    docker network create \
        --driver overlay \
        --attachable \
        --label "${INSTALLER_LABEL}" \
        "${NETWORK_NAME}" >/dev/null
    info "Created network ${NETWORK_NAME}."
}

ensure_volume() {
    local name="$1"

    if docker_object_exists volume "${name}"; then
        docker_object_is_installer_managed volume "${name}" \
            || fail "Docker volume ${name} exists but is not installer-managed."
        info "Reusing volume ${name}."
        return
    fi

    docker volume create --label "${INSTALLER_LABEL}" "${name}" >/dev/null
    info "Created volume ${name}."
}

ensure_volumes() {
    ensure_volume "${APP_VOLUME}"
    ensure_volume "${POSTGRES_VOLUME}"
    ensure_volume "${REDIS_VOLUME}"

    if [[ "${STORAGE_MODE}" == "rustfs" ]]; then
        ensure_volume "${RUSTFS_VOLUME}"
    fi
}

ensure_swarm_manager() {
    local swarm_state=""
    local control_available=""
    local os_type=""
    local architecture=""

    require_command docker
    require_command curl
    require_command sed
    require_command sort
    require_command od
    require_command base64

    docker version >/dev/null 2>&1 || fail "Docker is installed but the daemon is not reachable."
    os_type="$(docker info --format '{{.OSType}}')"
    architecture="$(docker info --format '{{.Architecture}}')"
    [[ "${os_type}" == "linux" ]] || fail "KoAkademy requires Docker's Linux container engine."
    [[ "${architecture}" == "x86_64" || "${architecture}" == "amd64" ||
       "${architecture}" == "aarch64" || "${architecture}" == "arm64" ]] \
        || fail "The published KoAkademy image supports linux/amd64 and linux/arm64."

    swarm_state="$(docker info --format '{{.Swarm.LocalNodeState}}')"
    if [[ "${swarm_state}" == "inactive" ]]; then
        info "Initializing a single-node Docker Swarm..."
        docker swarm init >/dev/null
    elif [[ "${swarm_state}" != "active" ]]; then
        fail "Docker Swarm is in unsupported state '${swarm_state}'."
    else
        info "Docker Swarm is already active; preserving the existing cluster."
    fi

    control_available="$(docker info --format '{{.Swarm.ControlAvailable}}')"
    [[ "${control_available}" == "true" ]] \
        || fail "Run the installer on a Docker Swarm manager node."
    CURRENT_NODE="$(docker info --format '{{.Name}}')"
    [[ "${CURRENT_NODE}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]*$ ]] \
        || fail "Docker node name contains unsupported characters."
}

ensure_image() {
    local image="$1"
    local label="$2"

    info "Verifying ${label} image ${image}..."
    docker manifest inspect "${image}" >/dev/null 2>&1 \
        || fail "Container image ${image} could not be resolved."
}

ensure_service_absent_or_owned() {
    local service="$1"

    if ! docker_object_exists service "${service}"; then
        return
    fi

    service_is_installer_managed "${service}" \
        || fail "Service ${service} already exists but is not managed by the KoAkademy installer."
}

create_service_if_missing() {
    local service="$1"
    shift

    ensure_service_absent_or_owned "${service}"
    if docker_object_exists service "${service}"; then
        info "Reusing service ${service}."
        return
    fi

    docker service create \
        --name "${service}" \
        --label "${INSTALLER_LABEL}" \
        "$@" >/dev/null
    info "Created service ${service}."
}

wait_for_service() {
    local service="$1"
    local timeout_seconds="${2:-300}"
    local elapsed=0
    local replicas=""
    local running=""
    local desired=""

    info "Waiting for ${service}..."
    while (( elapsed < timeout_seconds )); do
        replicas="$(docker service ls --filter "name=^${service}$" --format '{{.Replicas}}' 2>/dev/null || true)"
        if [[ "${replicas}" =~ ^([0-9]+)/([0-9]+)$ ]]; then
            running="${BASH_REMATCH[1]}"
            desired="${BASH_REMATCH[2]}"
            if (( running >= 1 && running == desired )); then
                return
            fi
        fi

        sleep 2
        elapsed=$((elapsed + 2))
    done

    docker service ps --no-trunc "${service}" >&2 || true
    fail "Service ${service} did not converge within ${timeout_seconds} seconds."
}

wait_for_job() {
    local service="$1"
    local timeout_seconds="${2:-300}"
    local elapsed=0
    local state=""
    local error=""

    while (( elapsed < timeout_seconds )); do
        state="$(docker service ps --no-trunc --format '{{.CurrentState}}' "${service}" 2>/dev/null | head -n 1)"
        error="$(docker service ps --no-trunc --format '{{.Error}}' "${service}" 2>/dev/null | head -n 1)"

        if [[ "${state}" == Complete* ]]; then
            docker service rm "${service}" >/dev/null
            return
        fi

        sleep 2
        elapsed=$((elapsed + 2))
    done

    docker service ps --no-trunc "${service}" >&2 || true
    fail "One-off service ${service} did not complete within ${timeout_seconds} seconds: ${error:-${state:-unknown state}}"
}

run_volume_permission_job() {
    local service="koakademy-rustfs-volume-init"

    if docker_object_exists service "${RUSTFS_SERVICE}"; then
        return
    fi

    docker service rm "${service}" >/dev/null 2>&1 || true
    docker service create \
        --name "${service}" \
        --label "${INSTALLER_LABEL}" \
        --mode replicated-job \
        --restart-condition on-failure \
        --restart-max-attempts 3 \
        --constraint "node.hostname==${CURRENT_NODE}" \
        --mount "type=volume,source=${RUSTFS_VOLUME},target=/data" \
        "${ALPINE_IMAGE}" \
        chown -R 10001:10001 /data >/dev/null
    wait_for_job "${service}" 120
}

run_storage_init_job() {
    local service="koakademy-storage-init"

    docker service rm "${service}" >/dev/null 2>&1 || true
    docker service create \
        --name "${service}" \
        --label "${INSTALLER_LABEL}" \
        --mode replicated-job \
        --restart-condition on-failure \
        --restart-max-attempts 3 \
        --constraint "node.hostname==${CURRENT_NODE}" \
        --network "${NETWORK_NAME}" \
        --secret "source=${S3_ACCESS_KEY_SECRET},target=koakademy-s3-access-key" \
        --secret "source=${S3_SECRET_KEY_SECRET},target=koakademy-s3-secret-key" \
        --config "source=${STORAGE_INIT_CONFIG},target=/run/configs/koakademy-storage-init,mode=0555" \
        --env "STORAGE_MODE=${STORAGE_MODE}" \
        --env "AWS_ENDPOINT=${AWS_ENDPOINT_VALUE}" \
        --env "AWS_BUCKET=${AWS_BUCKET_VALUE}" \
        --env "AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION_VALUE}" \
        --env "APP_ORIGIN=${APP_URL%/}" \
        --entrypoint /bin/sh \
        "${AWS_CLI_IMAGE}" \
        /run/configs/koakademy-storage-init >/dev/null
    wait_for_job "${service}" 300
}

run_migration_job() {
    local service="koakademy-migrate"
    local image="$1"

    docker service rm "${service}" >/dev/null 2>&1 || true
    docker service create \
        --name "${service}" \
        --label "${INSTALLER_LABEL}" \
        --mode replicated-job \
        --restart-condition on-failure \
        --restart-max-attempts 20 \
        --restart-delay 3s \
        --constraint "node.hostname==${CURRENT_NODE}" \
        --network "${NETWORK_NAME}" \
        --env-file "${TEMP_ENV_FILE}" \
        --secret "source=${APP_KEY_SECRET},target=koakademy-app-key" \
        --secret "source=${DB_PASSWORD_SECRET},target=koakademy-db-password" \
        --secret "source=${REDIS_PASSWORD_SECRET},target=koakademy-redis-password" \
        --secret "source=${S3_ACCESS_KEY_SECRET},target=koakademy-s3-access-key" \
        --secret "source=${S3_SECRET_KEY_SECRET},target=koakademy-s3-secret-key" \
        --config "source=${APP_ENTRYPOINT_CONFIG},target=/run/configs/koakademy-app-entrypoint,mode=0555" \
        --entrypoint /bin/sh \
        "${image}" \
        /run/configs/koakademy-app-entrypoint \
        php artisan migrate --force --no-interaction >/dev/null
    wait_for_job "${service}" 600
}

configure_application_url() {
    local default_host=""

    validate_port "${APP_PORT}" "KOAKADEMY_APP_PORT"

    if [[ -z "${APP_URL}" ]]; then
        default_host="$(detect_access_host)"
        prompt APP_URL "Public KoAkademy URL" "http://${default_host}:${APP_PORT}"
    fi

    APP_URL="${APP_URL%/}"
    validate_app_url "${APP_URL}"
    APP_HOST="${APP_URL#*://}"
    APP_HOST="${APP_HOST%%:*}"
    [[ -n "${APP_HOST}" ]] || fail "Could not derive a trusted host from APP_URL."

    if [[ "${APP_URL}" == https://* ]]; then
        SESSION_SECURE_COOKIE="true"
        warn "The installer publishes host port ${APP_PORT} without TLS. Ensure your existing HTTPS edge forwards to this port."
    else
        SESSION_SECURE_COOKIE="false"
        warn "HTTP is suitable for local/LAN evaluation only. Add an HTTPS edge before production use."
    fi
}

configure_storage() {
    local storage_choice=""
    local path_style_answer=""
    local default_storage_url=""

    if [[ -z "${STORAGE_MODE}" ]]; then
        log ""
        log "Choose upload storage:"
        log "  1) Local RustFS (single-node, no built-in redundancy)"
        log "  2) External S3-compatible service (for example Cloudflare R2)"
        prompt storage_choice "Storage option" "1"
        case "${storage_choice}" in
            1|rustfs) STORAGE_MODE="rustfs" ;;
            2|external|s3) STORAGE_MODE="external" ;;
            *) fail "Storage option must be 1 or 2." ;;
        esac
    fi

    case "${STORAGE_MODE}" in
        rustfs)
            validate_port "${RUSTFS_PORT}" "KOAKADEMY_RUSTFS_PORT"
            RUSTFS_VERSION="$(resolve_latest_rustfs_version)"
            AWS_BUCKET_VALUE="${KOAKADEMY_S3_BUCKET:-koakademy}"
            validate_bucket "${AWS_BUCKET_VALUE}"
            AWS_DEFAULT_REGION_VALUE="${KOAKADEMY_S3_REGION:-us-east-1}"
            AWS_ENDPOINT_VALUE="http://${RUSTFS_SERVICE}:9000"
            default_storage_url="${APP_URL#*://}"
            default_storage_url="${default_storage_url%%:*}"
            default_storage_url="http://${default_storage_url}:${RUSTFS_PORT}/${AWS_BUCKET_VALUE}"
            AWS_URL_VALUE="${KOAKADEMY_S3_PUBLIC_URL:-${default_storage_url}}"
            validate_http_url "${AWS_URL_VALUE}" "RustFS public object URL"
            AWS_USE_PATH_STYLE_VALUE="true"

            if [[ "${APP_URL}" == https://* && "${AWS_URL_VALUE}" == http://* ]]; then
                fail "HTTPS KoAkademy cannot use an HTTP RustFS public URL. Set KOAKADEMY_S3_PUBLIC_URL to an HTTPS edge for port ${RUSTFS_PORT}."
            fi

            if ! docker_object_exists secret "${S3_ACCESS_KEY_SECRET}"; then
                AWS_ACCESS_KEY_ID_VALUE="koa$(random_hex 12)"
                AWS_SECRET_ACCESS_KEY_VALUE="$(random_hex 32)"
            fi
            ;;
        external)
            prompt AWS_ENDPOINT_VALUE "S3 API endpoint (for R2: https://ACCOUNT_ID.r2.cloudflarestorage.com)" \
                "${KOAKADEMY_S3_ENDPOINT:-}"
            validate_http_url "${AWS_ENDPOINT_VALUE}" "S3 endpoint"

            prompt AWS_BUCKET_VALUE "Existing S3 bucket name" "${KOAKADEMY_S3_BUCKET:-}"
            validate_bucket "${AWS_BUCKET_VALUE}"

            prompt AWS_DEFAULT_REGION_VALUE "S3 region" "${KOAKADEMY_S3_REGION:-auto}"
            validate_region "${AWS_DEFAULT_REGION_VALUE}"

            prompt AWS_URL_VALUE "Public object base URL (CDN, r2.dev, or custom domain)" \
                "${KOAKADEMY_S3_PUBLIC_URL:-}"
            validate_http_url "${AWS_URL_VALUE}" "S3 public object URL"

            if [[ -n "${KOAKADEMY_S3_PATH_STYLE:-}" ]]; then
                path_style_answer="${KOAKADEMY_S3_PATH_STYLE}"
            elif prompt_yes_no "Use path-style S3 requests?" "n"; then
                path_style_answer="true"
            else
                path_style_answer="false"
            fi

            case "${path_style_answer}" in
                true|1|yes|y) AWS_USE_PATH_STYLE_VALUE="true" ;;
                false|0|no|n) AWS_USE_PATH_STYLE_VALUE="false" ;;
                *) fail "KOAKADEMY_S3_PATH_STYLE must be true or false." ;;
            esac

            if ! docker_object_exists secret "${S3_ACCESS_KEY_SECRET}"; then
                if [[ -n "${KOAKADEMY_S3_ACCESS_KEY:-}" ]]; then
                    AWS_ACCESS_KEY_ID_VALUE="${KOAKADEMY_S3_ACCESS_KEY}"
                else
                    prompt AWS_ACCESS_KEY_ID_VALUE "S3 access key ID"
                fi
                [[ -n "${AWS_ACCESS_KEY_ID_VALUE}" ]] || fail "S3 access key ID cannot be empty."

                if [[ -n "${KOAKADEMY_S3_SECRET_KEY:-}" ]]; then
                    AWS_SECRET_ACCESS_KEY_VALUE="${KOAKADEMY_S3_SECRET_KEY}"
                else
                    prompt_secret AWS_SECRET_ACCESS_KEY_VALUE "S3 secret access key"
                fi
            fi
            ;;
        *)
            fail "KOAKADEMY_STORAGE must be 'rustfs' or 'external'."
            ;;
    esac
}

write_application_env() {
    TEMP_ENV_FILE="$(mktemp)"
    chmod 600 "${TEMP_ENV_FILE}"
    validate_single_line "${KOAKADEMY_TRUSTED_PROXIES:-}" "KOAKADEMY_TRUSTED_PROXIES"

    {
        printf 'APP_NAME=KoAkademy\n'
        printf 'APP_ENV=production\n'
        printf 'APP_DEBUG=false\n'
        printf 'APP_URL=%s\n' "${APP_URL}"
        printf 'APP_TIMEZONE=UTC\n'
        printf 'APP_LOCALE=en\n'
        printf 'APP_FALLBACK_LOCALE=en\n'
        printf 'APP_MAINTENANCE_DRIVER=file\n'
        printf 'PORTAL_HOST=%s\n' "${APP_HOST}"
        printf 'ADMIN_HOST=%s\n' "${APP_HOST}"
        printf 'TRUSTED_HOSTS=\n'
        printf 'TRUSTED_PROXIES=%s\n' "${KOAKADEMY_TRUSTED_PROXIES:-}"
        printf 'APP_PORT=8000\n'
        printf 'OCTANE_SERVER=frankenphp\n'
        printf 'OCTANE_HTTPS=false\n'
        printf 'OCTANE_WORKERS=auto\n'
        printf 'OCTANE_MAX_REQUESTS=500\n'
        printf 'DB_CONNECTION=pgsql\n'
        printf 'DB_HOST=%s\n' "${POSTGRES_SERVICE}"
        printf 'DB_PORT=5432\n'
        printf 'DB_DATABASE=koakademy\n'
        printf 'DB_USERNAME=koakademy\n'
        printf 'REDIS_CLIENT=phpredis\n'
        printf 'REDIS_HOST=%s\n' "${REDIS_SERVICE}"
        printf 'REDIS_PORT=6379\n'
        printf 'REDIS_DB=0\n'
        printf 'REDIS_CACHE_DB=1\n'
        printf 'REDIS_QUEUE_DB=2\n'
        printf 'CACHE_STORE=redis\n'
        printf 'QUEUE_CONNECTION=redis\n'
        printf 'SESSION_DRIVER=redis\n'
        printf 'SESSION_LIFETIME=120\n'
        printf 'SESSION_ENCRYPT=true\n'
        printf 'SESSION_SECURE_COOKIE=%s\n' "${SESSION_SECURE_COOKIE}"
        printf 'SESSION_HTTP_ONLY=true\n'
        printf 'SESSION_SAME_SITE=lax\n'
        printf 'SESSION_DOMAIN=\n'
        printf 'FILESYSTEM_DISK=s3\n'
        printf 'AWS_DEFAULT_REGION=%s\n' "${AWS_DEFAULT_REGION_VALUE}"
        printf 'AWS_BUCKET=%s\n' "${AWS_BUCKET_VALUE}"
        printf 'AWS_ENDPOINT=%s\n' "${AWS_ENDPOINT_VALUE}"
        printf 'AWS_URL=%s\n' "${AWS_URL_VALUE}"
        printf 'AWS_USE_PATH_STYLE_ENDPOINT=%s\n' "${AWS_USE_PATH_STYLE_VALUE}"
        printf 'LARAVEL_PDF_DRIVER=gotenberg\n'
        printf 'LARAVEL_PDF_PRODUCTION_DRIVER=gotenberg\n'
        printf 'LARAVEL_PDF_PRODUCTION_FALLBACK=\n'
        printf 'LARAVEL_PDF_ROLLBACK_DRIVER=gotenberg\n'
        printf 'GOTENBERG_URL=http://%s:3000\n' "${GOTENBERG_SERVICE}"
        printf 'GOTENBERG_USERNAME=\n'
        printf 'GOTENBERG_PASSWORD=\n'
        printf 'MAIL_MAILER=log\n'
        printf 'MAIL_FROM_ADDRESS=no-reply@%s\n' "${APP_HOST}"
        printf 'MAIL_FROM_NAME=KoAkademy\n'
        printf 'BROADCAST_CONNECTION=log\n'
        printf 'LOG_CHANNEL=stack\n'
        printf 'LOG_STACK=single\n'
        printf 'LOG_LEVEL=info\n'
        printf 'RUN_MIGRATIONS=false\n'
        printf 'RUN_DOCKER_SCRIPTS=false\n'
        printf 'RUN_SCOUT_SETTINGS=false\n'
        printf 'RUN_OPTIMIZE=foreground\n'
        printf 'STATION_ENABLED=true\n'
        printf 'STATION_DRIVER=redis\n'
        printf 'STATION_PROCESSES=2\n'
        printf 'STATION_PDF_PROCESSES=1\n'
        printf 'PULSE_ENABLED=false\n'
        printf 'NIGHTWATCH_ENABLED=false\n'
        printf 'TELESCOPE_ENABLED=false\n'
        printf 'SCOUT_DRIVER=null\n'
        printf 'SENTRY_LARAVEL_DSN=\n'
        printf 'SENTRY_TRACES_SAMPLE_RATE=0.0\n'
        printf 'VITE_APP_NAME=KoAkademy\n'
    } >"${TEMP_ENV_FILE}"
}

deploy_dependencies() {
    local node_constraint="node.hostname==${CURRENT_NODE}"

    create_service_if_missing "${POSTGRES_SERVICE}" \
        --constraint "${node_constraint}" \
        --network "${NETWORK_NAME}" \
        --secret "source=${DB_PASSWORD_SECRET},target=koakademy-db-password" \
        --env POSTGRES_DB=koakademy \
        --env POSTGRES_USER=koakademy \
        --env POSTGRES_PASSWORD_FILE=/run/secrets/koakademy-db-password \
        --mount "type=volume,source=${POSTGRES_VOLUME},target=/var/lib/postgresql" \
        --health-cmd 'pg_isready -U koakademy -d koakademy' \
        --health-interval 10s \
        --health-timeout 5s \
        --health-retries 10 \
        --restart-condition any \
        "${POSTGRES_IMAGE}"

    # Command substitution runs inside the Redis container's health check.
    # shellcheck disable=SC2016
    create_service_if_missing "${REDIS_SERVICE}" \
        --constraint "${node_constraint}" \
        --network "${NETWORK_NAME}" \
        --secret "source=${REDIS_PASSWORD_SECRET},target=koakademy-redis-password" \
        --config "source=${REDIS_ENTRYPOINT_CONFIG},target=/run/configs/koakademy-redis-entrypoint,mode=0555" \
        --mount "type=volume,source=${REDIS_VOLUME},target=/data" \
        --health-cmd 'redis-cli -a "$(cat /run/secrets/koakademy-redis-password)" ping | grep -q PONG' \
        --health-interval 10s \
        --health-timeout 5s \
        --health-retries 10 \
        --restart-condition any \
        --entrypoint /bin/sh \
        "${REDIS_IMAGE}" \
        /run/configs/koakademy-redis-entrypoint

    create_service_if_missing "${GOTENBERG_SERVICE}" \
        --constraint "${node_constraint}" \
        --network "${NETWORK_NAME}" \
        --health-cmd 'curl --fail --silent --show-error http://localhost:3000/health' \
        --health-interval 10s \
        --health-timeout 5s \
        --health-retries 10 \
        --restart-condition any \
        "${GOTENBERG_IMAGE}"

    if [[ "${STORAGE_MODE}" == "rustfs" ]]; then
        run_volume_permission_job
        create_service_if_missing "${RUSTFS_SERVICE}" \
            --constraint "${node_constraint}" \
            --network "${NETWORK_NAME}" \
            --secret "source=${S3_ACCESS_KEY_SECRET},target=koakademy-s3-access-key" \
            --secret "source=${S3_SECRET_KEY_SECRET},target=koakademy-s3-secret-key" \
            --env RUSTFS_ACCESS_KEY_FILE=/run/secrets/koakademy-s3-access-key \
            --env RUSTFS_SECRET_KEY_FILE=/run/secrets/koakademy-s3-secret-key \
            --env RUSTFS_VOLUMES=/data \
            --env RUSTFS_ADDRESS=0.0.0.0:9000 \
            --env RUSTFS_CONSOLE_ADDRESS=0.0.0.0:9001 \
            --env RUSTFS_CONSOLE_ENABLE=true \
            --env RUSTFS_OBS_LOGGER_LEVEL=warn \
            --mount "type=volume,source=${RUSTFS_VOLUME},target=/data" \
            --publish "published=${RUSTFS_PORT},target=9000,mode=host" \
            --health-cmd 'curl --fail --silent --show-error http://127.0.0.1:9000/health' \
            --health-interval 15s \
            --health-timeout 5s \
            --health-retries 20 \
            --health-start-period 30s \
            --restart-condition any \
            "rustfs/rustfs:${RUSTFS_VERSION}"
    fi

    wait_for_service "${POSTGRES_SERVICE}" 300
    wait_for_service "${REDIS_SERVICE}" 300
    wait_for_service "${GOTENBERG_SERVICE}" 300
    if [[ "${STORAGE_MODE}" == "rustfs" ]]; then
        wait_for_service "${RUSTFS_SERVICE}" 300
    fi
}

deploy_application() {
    local image="ghcr.io/${KOAKADEMY_REPOSITORY}:${KOAKADEMY_VERSION}"

    run_storage_init_job
    run_migration_job "${image}"

    create_service_if_missing "${APP_SERVICE}" \
        --constraint "node.hostname==${CURRENT_NODE}" \
        --network "${NETWORK_NAME}" \
        --env-file "${TEMP_ENV_FILE}" \
        --secret "source=${APP_KEY_SECRET},target=koakademy-app-key" \
        --secret "source=${DB_PASSWORD_SECRET},target=koakademy-db-password" \
        --secret "source=${REDIS_PASSWORD_SECRET},target=koakademy-redis-password" \
        --secret "source=${S3_ACCESS_KEY_SECRET},target=koakademy-s3-access-key" \
        --secret "source=${S3_SECRET_KEY_SECRET},target=koakademy-s3-secret-key" \
        --config "source=${APP_ENTRYPOINT_CONFIG},target=/run/configs/koakademy-app-entrypoint,mode=0555" \
        --mount "type=volume,source=${APP_VOLUME},target=/app/storage" \
        --publish "published=${APP_PORT},target=8000,mode=host" \
        --health-cmd healthcheck \
        --health-interval 30s \
        --health-timeout 10s \
        --health-retries 5 \
        --health-start-period 60s \
        --restart-condition any \
        --update-parallelism 1 \
        --update-order stop-first \
        --rollback-parallelism 1 \
        --rollback-order stop-first \
        --entrypoint /bin/sh \
        "${image}" \
        /run/configs/koakademy-app-entrypoint

    wait_for_service "${APP_SERVICE}" 600
}

verify_application() {
    local health_url="http://127.0.0.1:${APP_PORT}/up"
    local attempt=0

    info "Checking ${health_url}..."
    while (( attempt < 90 )); do
        if curl -fsS --max-time 5 "${health_url}" >/dev/null 2>&1; then
            return
        fi

        sleep 2
        attempt=$((attempt + 1))
    done

    docker service ps --no-trunc "${APP_SERVICE}" >&2 || true
    fail "KoAkademy did not become healthy at ${health_url}."
}

main() {
    local koakademy_image=""

    log "KoAkademy Docker Swarm installer"
    log ""

    ensure_swarm_manager

    if docker_object_exists service "${APP_SERVICE}"; then
        service_is_installer_managed "${APP_SERVICE}" \
            || fail "Service ${APP_SERVICE} already exists but is not installer-managed."
        log "KoAkademy is already installed."
        log "Application: $(docker service inspect --format '{{range .Spec.TaskTemplate.ContainerSpec.Env}}{{println .}}{{end}}' "${APP_SERVICE}" \
            | sed -n 's/^APP_URL=//p' | head -n 1)"
        exit 0
    fi

    configure_application_url
    configure_storage

    KOAKADEMY_VERSION="$(resolve_latest_koakademy_version)"
    koakademy_image="ghcr.io/${KOAKADEMY_REPOSITORY}:${KOAKADEMY_VERSION}"
    ensure_image "${koakademy_image}" "KoAkademy"
    if [[ "${STORAGE_MODE}" == "rustfs" ]]; then
        ensure_image "rustfs/rustfs:${RUSTFS_VERSION}" "RustFS"
    fi

    ensure_network
    ensure_volumes
    ensure_generated_secret "${APP_KEY_SECRET}" app-key
    ensure_generated_secret "${DB_PASSWORD_SECRET}" password
    ensure_generated_secret "${REDIS_PASSWORD_SECRET}" password
    ensure_secret_value "${S3_ACCESS_KEY_SECRET}" "${AWS_ACCESS_KEY_ID_VALUE}"
    ensure_secret_value "${S3_SECRET_KEY_SECRET}" "${AWS_SECRET_ACCESS_KEY_VALUE}"
    ensure_configs
    write_application_env
    deploy_dependencies
    deploy_application
    verify_application

    log ""
    log "KoAkademy ${KOAKADEMY_VERSION} is installed."
    log "Application: ${APP_URL}"
    log "First-time setup: ${APP_URL}/setup"
    log "Admin portal: ${APP_URL}/admin"
    log ""
    log "Useful commands:"
    log "  docker service ls --filter label=com.koakademy.managed-by=swarm-installer"
    log "  docker service logs -f ${APP_SERVICE}"
    log ""
    warn "Back up PostgreSQL, application storage, and your S3/RustFS data before upgrades."
}

main "$@"
