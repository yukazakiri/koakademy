#!/usr/bin/env sh
set -e

ROOT="${ROOT:-/app}"
if [ -n "${DOCKER_SCRIPTS_DIR:-}" ]; then
    SCRIPTS_DIR="${DOCKER_SCRIPTS_DIR}"
elif [ -d "/var/www/html/docker-scripts" ]; then
    SCRIPTS_DIR="/var/www/html/docker-scripts"
else
    SCRIPTS_DIR="${ROOT}/docker/docker-scripts"
fi

FLAG_DIR="${ROOT}/storage/.docker-scripts"
SCRIPTS_RUN_FILE="${FLAG_DIR}/ran.json"

init_flags() {
    if [ ! -d "${FLAG_DIR}" ]; then
        mkdir -p "${FLAG_DIR}"
    fi

    if [ ! -f "${SCRIPTS_RUN_FILE}" ]; then
        echo "{}" > "${SCRIPTS_RUN_FILE}"
    fi
}

get_script_hash() {
    script_path="$1"
    if command -v sha256sum > /dev/null 2>&1; then
        sha256sum "${script_path}" | awk '{print $1}'
    else
        md5sum "${script_path}" | awk '{print $1}'
    fi
}

has_script_run() {
    script_name="$1"
    current_hash="$2"

    if [ -f "${SCRIPTS_RUN_FILE}" ]; then
        stored_hash=$(php -r '
            $file = $argv[1];
            $name = $argv[2];
            $data = json_decode((string) @file_get_contents($file), true);
            echo is_array($data) ? ($data[$name]["hash"] ?? "") : "";
        ' "${SCRIPTS_RUN_FILE}" "${script_name}")
        [ "${stored_hash}" = "${current_hash}" ] && return 0
    fi
    return 1
}

mark_script_ran() {
    script_name="$1"
    script_hash="$2"
    timestamp=$(date +%s)

    php -r '
        $file = $argv[1];
        $name = $argv[2];
        $hash = $argv[3];
        $timestamp = (int) $argv[4];
        $data = json_decode((string) @file_get_contents($file), true);
        if (! is_array($data)) {
            $data = [];
        }
        $data[$name] = [
            "hash" => $hash,
            "ran_at" => $timestamp,
        ];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    ' "${SCRIPTS_RUN_FILE}" "${script_name}" "${script_hash}" "${timestamp}"
}

run_scripts() {
    init_flags

    if [ ! -d "${SCRIPTS_DIR}" ]; then
        echo "Scripts directory ${SCRIPTS_DIR} does not exist. Skipping..."
        return 0
    fi

    scripts_found=$(find "${SCRIPTS_DIR}" -maxdepth 1 -type f -name "*.sh" 2>/dev/null | sort)

    if [ -z "${scripts_found}" ]; then
        echo "No scripts found in ${SCRIPTS_DIR}. Skipping..."
        return 0
    fi

    run_count=0
    skip_count=0

    for script_path in ${scripts_found}; do
        script_name=$(basename "${script_path}")
        script_hash=$(get_script_hash "${script_path}")

        chmod +x "${script_path}" 2>/dev/null || true

        if has_script_run "${script_name}" "${script_hash}"; then
            echo "[SKIP] ${script_name} (already ran)"
            skip_count=$((skip_count + 1))
            continue
        fi

        echo "[RUN] ${script_name}..."
        if /bin/sh "${script_path}"; then
            mark_script_ran "${script_name}" "${script_hash}"
            echo "[DONE] ${script_name}"
            run_count=$((run_count + 1))
        else
            echo "[ERROR] ${script_name} failed with exit code $?"
        fi
    done

    echo ""
    echo "Docker scripts summary:"
    echo "  Executed: ${run_count}"
    echo "  Skipped: ${skip_count}"
    echo "  Total: $((run_count + skip_count))"
}

case "${1:-}" in
    run)
        run_scripts
        ;;
    reset)
        rm -rf "${FLAG_DIR}"
        echo "Script tracking reset. All scripts will run on next execution."
        ;;
    list)
        init_flags
        echo "Previously executed scripts:"
        php -r '
            $file = $argv[1];
            $data = json_decode((string) @file_get_contents($file), true);
            if (empty($data) || ! is_array($data)) {
                echo "  (none)\n";
            } else {
                foreach ($data as $name => $info) {
                    $hash = substr((string) ($info["hash"] ?? ""), 0, 8);
                    echo "  {$name} (hash: {$hash}...)\n";
                }
            }
        ' "${SCRIPTS_RUN_FILE}"
        ;;
    *)
        echo "Usage: $0 {run|reset|list}"
        echo ""
        echo "  run   - Execute all scripts in ${SCRIPTS_DIR}"
        echo "  reset - Clear tracking (all scripts will run again)"
        echo "  list  - Show previously executed scripts"
        ;;
esac
