#!/usr/bin/env sh
set -e

echo "Starting Scout indexing process..."

SCOUT_DRIVER=$(php artisan tinker --execute="echo config('scout.driver');" 2>/dev/null || echo "collection")

echo "Detected Scout driver: ${SCOUT_DRIVER}"

get_searchable_models() {
    php artisan tinker --execute='
        $models = [];
        $modelFiles = glob(base_path("app/Models/*.php"));
        foreach ($modelFiles as $file) {
            $class = "App\\Models\\" . basename($file, ".php");
            if (class_exists($class) && in_array("Laravel\\Scout\\Searchable", class_uses($class))) {
                $models[] = $class;
            }
        }
        echo implode(",", $models);
    ' 2>/dev/null || echo ""
}

check_driver_connection() {
    local driver=$1
    
    case "$driver" in
        meilisearch)
            echo "Checking Meilisearch connection..."
            php artisan tinker --execute='
                try {
                    $client = new \Meilisearch\Client(config("scout.meilisearch.host"), config("scout.meilisearch.key"));
                    $health = $client->health();
                    echo "Meilisearch is reachable.";
                } catch (Exception $e) {
                    echo "Meilisearch connection failed: " . $e->getMessage();
                    exit(1);
                }
            ' 2>&1
            ;;
        algolia)
            echo "Checking Algolia connection..."
            php artisan tinker --execute='
                try {
                    $config = config("scout.algolia");
                    $client = new \Algolia\AlgoliaSearch\AlgoliaClient($config["id"], $config["secret"]);
                    $client->listIndices();
                    echo "Algolia is reachable.";
                } catch (Exception $e) {
                    echo "Algolia connection failed: " . $e->getMessage();
                    exit(1);
                }
            ' 2>&1
            ;;
        typesense)
            echo "Checking Typesense connection..."
            php artisan tinker --execute='
                try {
                    $config = config("scout.typesense.client-settings");
                    $host = $config["nodes"][0]["host"] ?? "localhost";
                    $port = $config["nodes"][0]["port"] ?? 8108;
                    $protocol = $config["nodes"][0]["protocol"] ?? "http";
                    $apiKey = $config["api_key"] ?? "";
                    $client = new \Typesense\Client([
                        "node" => $protocol . "://" . $host . ":" . $port,
                        "api_key" => $apiKey,
                        "connection_timeout_seconds" => 2
                    ]);
                    $client->retrieve()->create();
                    echo "Typesense is reachable.";
                } catch (Exception $e) {
                    echo "Typesense connection failed: " . $e->getMessage();
                    exit(1);
                }
            ' 2>&1
            ;;
        database|collection|null)
            echo "Driver ${driver} does not require external connection check."
            return 0
            ;;
        *)
            echo "Unknown driver: ${driver}. Skipping connection check."
            return 0
            ;;
    esac
}

sync_index_settings() {
    local driver=$1
    
    case "$driver" in
        meilisearch|algolia|typesense)
            echo "Syncing ${driver} index settings..."
            php artisan scout:sync-index-settings 2>&1 || echo "Index settings sync attempted."
            ;;
        *)
            echo "Driver ${driver} does not support index settings sync."
            ;;
    esac
}

import_model() {
    local model=$1
    local model_class=$2
    
    echo "Importing ${model}..."
    
    php artisan scout:import "${model_class}" 2>&1
    
    if [ $? -eq 0 ]; then
        echo "  ${model} indexed successfully."
    else
        echo "  Warning: Failed to index ${model}. Continuing..."
    fi
}

run_indexing() {
    local driver=$1
    
    if [ "$driver" = "null" ] || [ "$driver" = "collection" ] || [ "$driver" = "database" ]; then
        echo "Driver ${driver} does not require indexing (local/ephemeral)."
        echo "All searchable models will be indexed on-demand."
        return 0
    fi
    
    MODELS=$(get_searchable_models)
    
    if [ -z "$MODELS" ]; then
        echo "No searchable models found."
        return 0
    fi
    
    echo "Found searchable models: ${MODELS}"
    echo "Starting indexing..."
    
    OLD_IFS=$IFS
    IFS=','
    for model_class in $MODELS; do
        model_name=$(basename "$model_class")
        import_model "$model_name" "$model_class"
    done
    IFS=$OLD_IFS
}

case "$SCOUT_DRIVER" in
    meilisearch)
        check_driver_connection "$SCOUT_DRIVER"
        sync_index_settings "$SCOUT_DRIVER"
        run_indexing "$SCOUT_DRIVER"
        ;;
    algolia)
        check_driver_connection "$SCOUT_DRIVER"
        sync_index_settings "$SCOUT_DRIVER"
        run_indexing "$SCOUT_DRIVER"
        ;;
    typesense)
        check_driver_connection "$SCOUT_DRIVER"
        sync_index_settings "$SCOUT_DRIVER"
        run_indexing "$SCOUT_DRIVER"
        ;;
    database|collection|null)
        check_driver_connection "$SCOUT_DRIVER"
        run_indexing "$SCOUT_DRIVER"
        ;;
    *)
        echo "Unknown or unsupported Scout driver: ${SCOUT_DRIVER}"
        exit 1
        ;;
esac

echo "Scout indexing completed."
