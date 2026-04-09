#!/usr/bin/env sh
set -e

SCRIPTS_DIR="/var/www/html/docker-scripts"
FLAG_DIR="/var/www/html/storage/.docker-scripts"
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
    local script_path=$1
    if command -v sha256sum > /dev/null 2>&1; then
        sha256sum "${script_path}" | awk '{print $1}'
    else
        md5sum "${script_path}" | awk '{print $1}'
    fi
}

has_script_run() {
    local script_name=$1
    local current_hash=$2
    
    if [ -f "${SCRIPTS_RUN_FILE}" ]; then
        local stored_hash=$(php -r "
            \$data = json_decode(file_get_contents('${SCRIPTS_RUN_FILE}'), true);
            echo \$data['${script_name}']['hash'] ?? '';
        ")
        [ "${stored_hash}" = "${current_hash}" ] && return 0
    fi
    return 1
}

mark_script_ran() {
    local script_name=$1
    local script_hash=$2
    local timestamp=$(date +%s)
    
    php -r "
        \$data = json_decode(file_get_contents('${SCRIPTS_RUN_FILE}'), true);
        \$data['${script_name}'] = [
            'hash' => '${script_hash}',
            'ran_at' => ${timestamp}
        ];
        file_put_contents('${SCRIPTS_RUN_FILE}', json_encode(\$data, JSON_PRETTY_PRINT));
    "
}

run_scripts() {
    local mode=$1
    
    init_flags
    
    if [ ! -d "${SCRIPTS_DIR}" ]; then
        echo "Scripts directory ${SCRIPTS_DIR} does not exist. Skipping..."
        return 0
    fi
    
    local scripts_found=$(find "${SCRIPTS_DIR}" -maxdepth 1 -type f -name "*.sh" 2>/dev/null | sort)
    
    if [ -z "${scripts_found}" ]; then
        echo "No scripts found in ${SCRIPTS_DIR}. Skipping..."
        return 0
    fi
    
    local run_count=0
    local skip_count=0
    
    for script_path in ${scripts_found}; do
        local script_name=$(basename "${script_path}")
        local script_hash=$(get_script_hash "${script_path}")
        
        chmod +x "${script_path}"
        
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

case "$1" in
    run)
        run_scripts "manual"
        ;;
    reset)
        rm -rf "${FLAG_DIR}"
        echo "Script tracking reset. All scripts will run on next execution."
        ;;
    list)
        init_flags
        echo "Previously executed scripts:"
        php -r "
            \$data = json_decode(file_get_contents('${SCRIPTS_RUN_FILE}'), true);
            if (empty(\$data)) {
                echo '  (none)';
            } else {
                foreach (\$data as \$name => \$info) {
                    echo \"  \$name (hash: \" . substr(\$info['hash'], 0, 8) . \"...)\";
                }
            }
        "
        ;;
    *)
        echo "Usage: $0 {run|reset|list}"
        echo ""
        echo "  run   - Execute all scripts in ${SCRIPTS_DIR}"
        echo "  reset - Clear tracking (all scripts will run again)"
        echo "  list  - Show previously executed scripts"
        ;;
esac
