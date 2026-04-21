#!/usr/bin/env sh
set -e

scout_driver=${SCOUT_DRIVER:-"collection"}
scout_index_models=${SCOUT_INDEX_MODELS:-""}
# When SCOUT_IMPORT_QUEUE=1, scout:import is invoked with --queue so per-chunk
# MakeSearchable jobs are dispatched to the queue (processed by Horizon) instead
# of running inline. This makes the import command return in seconds.
scout_import_queue=${SCOUT_IMPORT_QUEUE:-"0"}

echo "Starting Scout indexing process..."
echo "Detected Scout driver: ${scout_driver}"
if [ "${scout_import_queue}" = "1" ]; then
    echo "Scout import mode: queued (jobs dispatched to queue, processed by Horizon)"
else
    echo "Scout import mode: inline (synchronous)"
fi

get_searchable_models() {
    if [ -n "${scout_index_models}" ]; then
        printf '%s' "${scout_index_models}"
        return 0
    fi

    php artisan tinker --execute='
        $models = [];

        foreach (glob(app_path("Models/*.php")) as $file) {
            $class = "App\\Models\\" . basename($file, ".php");

            if (! class_exists($class)) {
                continue;
            }

            if (! in_array(Laravel\Scout\Searchable::class, class_uses_recursive($class), true)) {
                continue;
            }

            $models[] = $class;
        }

        echo implode(",", $models);
    ' 2>/dev/null || echo ""
}

sync_index_settings() {
    case "${scout_driver}" in
        meilisearch|algolia|typesense)
            echo "Syncing ${scout_driver} index settings..."
            php artisan scout:sync-index-settings --no-interaction
            ;;
        *)
            echo "Driver ${scout_driver} does not support index settings sync."
            ;;
    esac
}

import_model() {
    model_class=$1

    if [ "${scout_import_queue}" = "1" ]; then
        echo "Dispatching queued import for ${model_class}..."
        php artisan scout:import "${model_class}" --queue --no-interaction
        echo "Queued import dispatched for ${model_class}."
    else
        echo "Importing ${model_class}..."
        php artisan scout:import "${model_class}" --no-interaction
        echo "Indexed ${model_class} successfully."
    fi
}

run_indexing() {
    case "${scout_driver}" in
        database|collection|null)
            echo "Driver ${scout_driver} does not require external indexing."
            return 0
            ;;
    esac

    models=$(get_searchable_models)

    if [ -z "${models}" ]; then
        echo "No searchable models found."
        return 0
    fi

    echo "Found searchable models: ${models}"

    old_ifs=$IFS
    IFS=','

    for model_class in ${models}; do
        import_model "${model_class}"
    done

    IFS=$old_ifs
}

case "${scout_driver}" in
    meilisearch|algolia|typesense|database|collection|null)
        if [ "${SCOUT_SKIP_SETTINGS_SYNC:-0}" = "1" ]; then
            echo "Skipping index settings sync (SCOUT_SKIP_SETTINGS_SYNC=1)."
        else
            sync_index_settings
        fi
        run_indexing
        ;;
    *)
        echo "Unknown or unsupported Scout driver: ${scout_driver}"
        exit 1
        ;;
esac

echo "Scout indexing completed."
