#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
tmp_dir="$(mktemp -d)"
trap 'rm -rf -- "${tmp_dir}"' EXIT

# Extract the pure helper functions from docker/start-container without executing
# the container entrypoint at the bottom of the script.
helper_file="${tmp_dir}/helpers.sh"
awk '
    /^strip_optional_quotes\(\) \{/,/^\}/ { print }
    /^is_truthy\(\) \{/,/^\}/ { print }
    /^sync_shared_build_assets\(\) \{/,/^\}/ { print }
' "${repository_root}/docker/start-container" > "${helper_file}"

sh -n "${helper_file}"

shared_dir="${tmp_dir}/storage/app/build-assets"

# Release 1 container boots and publishes its hashed assets into shared storage.
rel1_dir="${tmp_dir}/release-1"
mkdir -p "${rel1_dir}/public/build/assets/chunks"
printf 'console.log("v1");\n' > "${rel1_dir}/public/build/assets/app-v1.js"
printf 'console.log("chunk-v1");\n' > "${rel1_dir}/public/build/assets/chunks/page-v1.js"
printf '{"manifest":"v1"}\n' > "${rel1_dir}/public/build/manifest.json"

(
    cd "${rel1_dir}"
    SHARED_BUILD_ASSETS_DIR="${shared_dir}" \
    RETAIN_PREVIOUS_BUILD_ASSETS=true \
    BUILD_ASSETS_RETENTION_DAYS=7 \
    sh -ec ". '${helper_file}'; sync_shared_build_assets"
)

[[ -L "${rel1_dir}/public/build/assets" ]]
[[ -f "${rel1_dir}/public/build/assets/app-v1.js" ]]
[[ -f "${rel1_dir}/public/build/assets/chunks/page-v1.js" ]]
grep -Fq '"manifest":"v1"' "${rel1_dir}/public/build/manifest.json"

# Simulate an expired asset from 14 days ago in shared storage.
printf 'console.log("ancient");\n' > "${shared_dir}/ancient-v0.js"
touch -d '14 days ago' "${shared_dir}/ancient-v0.js"

# Release 2 container boots alongside the same shared volume.
rel2_dir="${tmp_dir}/release-2"
mkdir -p "${rel2_dir}/public/build/assets/chunks"
printf 'console.log("v2");\n' > "${rel2_dir}/public/build/assets/app-v2.js"
printf 'console.log("chunk-v2");\n' > "${rel2_dir}/public/build/assets/chunks/page-v2.js"
printf '{"manifest":"v2"}\n' > "${rel2_dir}/public/build/manifest.json"

(
    cd "${rel2_dir}"
    SHARED_BUILD_ASSETS_DIR="${shared_dir}" \
    RETAIN_PREVIOUS_BUILD_ASSETS=true \
    BUILD_ASSETS_RETENTION_DAYS=7 \
    sh -ec ". '${helper_file}'; sync_shared_build_assets"
)

# Release 2 serves BOTH v1 and v2 chunks while keeping its own manifest.json,
# and Release 1 can also serve v2 chunks during start-first rolling overlap!
[[ -L "${rel2_dir}/public/build/assets" ]]
[[ -f "${rel2_dir}/public/build/assets/app-v1.js" ]]
[[ -f "${rel2_dir}/public/build/assets/chunks/page-v1.js" ]]
[[ -f "${rel2_dir}/public/build/assets/app-v2.js" ]]
[[ -f "${rel2_dir}/public/build/assets/chunks/page-v2.js" ]]
[[ -f "${rel1_dir}/public/build/assets/app-v2.js" ]]
[[ ! -e "${shared_dir}/ancient-v0.js" ]]
grep -Fq '"manifest":"v1"' "${rel1_dir}/public/build/manifest.json"
grep -Fq '"manifest":"v2"' "${rel2_dir}/public/build/manifest.json"

# Re-running on an already-symlinked container is idempotent.
(
    cd "${rel2_dir}"
    SHARED_BUILD_ASSETS_DIR="${shared_dir}" \
    RETAIN_PREVIOUS_BUILD_ASSETS=true \
    sh -ec ". '${helper_file}'; sync_shared_build_assets"
)
[[ -L "${rel2_dir}/public/build/assets" ]]

# Disabling retention leaves container-local assets untouched.
rel3_dir="${tmp_dir}/release-3"
mkdir -p "${rel3_dir}/public/build/assets"
printf 'console.log("v3");\n' > "${rel3_dir}/public/build/assets/app-v3.js"
(
    cd "${rel3_dir}"
    SHARED_BUILD_ASSETS_DIR="${shared_dir}" \
    RETAIN_PREVIOUS_BUILD_ASSETS=false \
    sh -ec ". '${helper_file}'; sync_shared_build_assets"
)
[[ ! -L "${rel3_dir}/public/build/assets" ]]
[[ -d "${rel3_dir}/public/build/assets" ]]

printf 'Shared build asset retention test passed.\n'
