#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
policy="${repository_root}/.github/ci-required.jq"
full_results='{
  "scope": {"result": "success"},
  "validate": {"result": "success"},
  "workflow-security": {"result": "success"},
  "docs": {"result": "success"},
  "pr-title": {"result": "success"}
}'
release_results="$(jq '.validate.result = "skipped" | .["workflow-security"].result = "skipped" | .docs.result = "skipped"' <<<"${full_results}")"
checks=0

assert_policy() {
    local expected="$1" event_name="$2" release_only="$3" results="$4" description="$5"
    local actual=fail

    if jq --exit-status --arg event_name "${event_name}" --arg release_only "${release_only}" \
        --from-file "${policy}" <<<"${results}" >/dev/null; then
        actual=pass
    fi

    if [[ "${actual}" != "${expected}" ]]; then
        printf 'Required-check policy: %s (expected %s, got %s).\n' \
            "${description}" "${expected}" "${actual}" >&2
        exit 1
    fi
    checks=$((checks + 1))
}

assert_policy pass pull_request false "${full_results}" 'all pull-request jobs passed'
assert_policy pass pull_request true "${release_results}" 'validated release metadata fast path'
assert_policy fail pull_request false "${release_results}" 'normal PR cannot skip validation'
assert_policy fail pull_request '' "${release_results}" 'missing classifier output cannot skip validation'
assert_policy fail pull_request true "${full_results}" 'release fast path must match expected jobs'
assert_policy fail pull_request false '{}' 'missing jobs must fail closed'
assert_policy fail pull_request false "$(jq '.unexpected.result = "skipped"' <<<"${full_results}")" 'unreviewed skipped job must fail closed'
assert_policy fail pull_request false "$(jq '.unexpected.result = "failure"' <<<"${full_results}")" 'unreviewed failed job must fail closed'

for event_name in push workflow_dispatch; do
    delivery_results="$(jq '.["pr-title"].result = "skipped"' <<<"${full_results}")"
    assert_policy pass "${event_name}" false "${delivery_results}" 'Delivery validates full source'
    assert_policy fail "${event_name}" true "${release_results}" 'Delivery cannot use PR metadata fast path'
    assert_policy fail "${event_name}" false "${full_results}" 'non-PR runs must skip PR title check'
done

for job in scope validate workflow-security docs pr-title; do
    for result in failure cancelled skipped; do
        failed_results="$(jq --arg job "${job}" --arg result "${result}" '.[$job].result = $result' <<<"${full_results}")"
        assert_policy fail pull_request false "${failed_results}" "${job} ${result} blocks merge"
    done
    missing_results="$(jq --arg job "${job}" 'del(.[$job])' <<<"${full_results}")"
    assert_policy fail pull_request false "${missing_results}" "missing ${job} blocks merge"
done

for job in scope pr-title; do
    for result in failure cancelled skipped; do
        failed_results="$(jq --arg job "${job}" --arg result "${result}" '.[$job].result = $result' <<<"${release_results}")"
        assert_policy fail pull_request true "${failed_results}" "release metadata still requires ${job}"
    done
done

printf 'Required-check policy: %s cases passed.\n' "${checks}"
