#!/usr/bin/env bash

# Audit the JavaScript runtime tree shipped in the production Docker images.
# Both Dockerfiles prune dev and optional dependencies after building assets.
# Build-time and optional dependencies are not covered by this runtime audit.
#
# `npm audit` exits non-zero both when it finds vulnerabilities and when it
# cannot reach the advisory endpoint, so a transient registry outage would
# otherwise fail a delivery run for a reason unrelated to the code. Retry only
# the infrastructure failures: a report that npm actually produced always
# fails the run immediately, whatever it contains.

set -Eeuo pipefail

attempts="${AUDIT_ATTEMPTS:-3}"
delay_seconds="${AUDIT_RETRY_DELAY:-20}"

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

if ! [[ "$attempts" =~ ^[0-9]+$ ]] || [ "$attempts" -lt 1 ]; then
    fail "AUDIT_ATTEMPTS must be a positive integer."
fi

if ! [[ "$delay_seconds" =~ ^[0-9]+$ ]]; then
    fail "AUDIT_RETRY_DELAY must be a non-negative integer."
fi

stdout_file="$(mktemp)"
stderr_file="$(mktemp)"
trap 'rm -f "$stdout_file" "$stderr_file"' EXIT

for attempt in $(seq 1 "$attempts"); do
    status=0
    npm audit --omit=dev --omit=optional --audit-level=high --json >"$stdout_file" 2>"$stderr_file" || status=$?

    if [ "$status" -eq 0 ]; then
        cat "$stdout_file"
        printf 'Production dependency audit passed on attempt %s of %s.\n' "$attempt" "$attempts"
        exit 0
    fi

    # npm prints the JSON report on stdout. A report means npm reached the
    # advisory endpoint, so this is a real result and must not be retried.
    if node -e '
        const fs = require("fs");
        let report;
        try {
            report = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
        } catch {
            process.exit(1);
        }
        process.exit(report && report.vulnerabilities ? 0 : 1);
    ' "$stdout_file"; then
        cat "$stdout_file"
        cat "$stderr_file" >&2 || true
        fail "npm audit reported vulnerabilities in the production dependencies."
    fi

    printf 'npm audit attempt %s of %s could not reach the registry:\n' "$attempt" "$attempts" >&2
    cat "$stderr_file" >&2 || true

    if [ "$attempt" -lt "$attempts" ]; then
        sleep "$delay_seconds"
    fi
done

cat "$stderr_file" >&2 || true
fail "npm audit could not reach the advisory endpoint after ${attempts} attempts."
