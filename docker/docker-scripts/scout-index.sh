#!/usr/bin/env sh
set -e

scout_driver=${SCOUT_DRIVER:-"collection"}
scout_index_models=${SCOUT_INDEX_MODELS:-""}

echo "Starting Scout indexing process..."
echo "Detected Scout driver: ${scout_driver}"

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

    echo "Importing ${model_class}..."
    php artisan scout:import "${model_class}" --no-interaction
    echo "Indexed ${model_class} successfully."
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
        sync_index_settings
        run_indexing
        ;;
    *)
        echo "Unknown or unsupported Scout driver: ${scout_driver}"
        exit 1
        ;;
esac

echo "Scout indexing completed."
