#!/usr/bin/env bash

set -Eeuo pipefail

usage() {
    printf '%s\n' \
        "Usage: $0 --channel <edge|stable> --version <X.Y.Z> --commit <sha> \\" \
        "  --image <registry/image:tag> --build-url <url> --repository <owner/repo> \\" \
        "  [--timestamp <RFC3339>] [--output <path>]"
}

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

channel=""
version=""
commit=""
image=""
build_url=""
repository=""
timestamp=""
output="version.json"

while (( $# > 0 )); do
    case "$1" in
        --channel|--version|--commit|--image|--build-url|--repository|--timestamp|--output)
            (( $# >= 2 )) || fail "Missing value for $1."
            case "$1" in
                --channel) channel="$2" ;;
                --version) version="$2" ;;
                --commit) commit="$2" ;;
                --image) image="$2" ;;
                --build-url) build_url="$2" ;;
                --repository) repository="$2" ;;
                --timestamp) timestamp="$2" ;;
                --output) output="$2" ;;
            esac
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            fail "Unknown argument: $1"
            ;;
    esac
done

[[ "${channel}" == "edge" || "${channel}" == "stable" ]] \
    || fail "Channel must be 'edge' or 'stable'."
[[ "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] \
    || fail "Version must be a strict X.Y.Z release."
[[ "${commit}" =~ ^[0-9a-f]{40}$ ]] \
    || fail "Commit must be a full lowercase Git SHA."
[[ "${image}" =~ ^[A-Za-z0-9._/-]+:[A-Za-z0-9._-]+$ ]] \
    || fail "Image must be a fully qualified container reference with a tag."
[[ "${build_url}" =~ ^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/actions/runs/[0-9]+$ ]] \
    || fail "Build URL must identify a GitHub Actions run."
[[ "${repository}" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] \
    || fail "Repository must use owner/name form."

if [[ -z "${timestamp}" ]]; then
    timestamp="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
fi

[[ "${timestamp}" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$ ]] \
    || fail "Timestamp must use UTC RFC3339 form."

short_commit="${commit:0:12}"
display_version="${version}"
release_type="stable"

if [[ "${channel}" == "edge" ]]; then
    display_version="${version}-edge+sha.${short_commit}"
    release_type="edge"
fi

mkdir -p -- "$(dirname -- "${output}")"

jq --null-input \
    --arg version "${display_version}" \
    --arg image "${image}" \
    --arg commit "${commit}" \
    --arg timestamp "${timestamp}" \
    --arg build_url "${build_url}" \
    --arg release_type "${release_type}" \
    --arg channel "${channel}" \
    --arg repository "${repository}" \
    '{
        version: $version,
        image: $image,
        commit: $commit,
        branch: "master",
        timestamp: $timestamp,
        build_url: $build_url,
        release_type: $release_type,
        changelog: {
            current: ("KoAkademy " + $version + " (" + $channel + ")"),
            previous: ""
        },
        metadata: {
            author: "github-actions[bot]",
            workflow: "Delivery",
            repository: $repository,
            channel: $channel
        }
    }' > "${output}"

jq --exit-status 'type == "object"' "${output}" >/dev/null
