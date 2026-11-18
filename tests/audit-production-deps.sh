#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
temp_dir="$(mktemp -d)"
trap 'rm -rf "${temp_dir}"' EXIT

cat >"${temp_dir}/npm" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
printf '%s\n' "$*" >>"${AUDIT_TEST_CALLS}"
case "${AUDIT_TEST_RESULT}" in
    success)
        printf '%s\n' '{"vulnerabilities":{}}'
        ;;
    vulnerability)
        printf '%s\n' '{"vulnerabilities":{"example":{"severity":"high"}}}'
        exit 1
        ;;
    unavailable)
        printf '%s\n' 'registry unavailable' >&2
        exit 1
        ;;
esac
EOF
chmod +x "${temp_dir}/npm"

export PATH="${temp_dir}:${PATH}"
export AUDIT_TEST_CALLS="${temp_dir}/calls"
export AUDIT_ATTEMPTS=2 AUDIT_RETRY_DELAY=0

AUDIT_TEST_RESULT=success bash "${repository_root}/scripts/audit-production-deps.sh" >"${temp_dir}/output"
grep -Fxq 'audit --omit=dev --omit=optional --audit-level=high --json' "${AUDIT_TEST_CALLS}"
[[ "$(wc -l <"${AUDIT_TEST_CALLS}")" -eq 1 ]]

: >"${AUDIT_TEST_CALLS}"
if AUDIT_TEST_RESULT=vulnerability bash "${repository_root}/scripts/audit-production-deps.sh" >"${temp_dir}/output" 2>&1; then
    printf 'Audit unexpectedly passed a high-severity vulnerability.\n' >&2
    exit 1
fi
[[ "$(wc -l <"${AUDIT_TEST_CALLS}")" -eq 1 ]]

: >"${AUDIT_TEST_CALLS}"
if AUDIT_TEST_RESULT=unavailable bash "${repository_root}/scripts/audit-production-deps.sh" >"${temp_dir}/output" 2>&1; then
    printf 'Audit unexpectedly passed after registry failures.\n' >&2
    exit 1
fi
[[ "$(wc -l <"${AUDIT_TEST_CALLS}")" -eq 2 ]]

printf 'Docker runtime dependency audit: scope, vulnerability and registry failure cases passed.\n'
