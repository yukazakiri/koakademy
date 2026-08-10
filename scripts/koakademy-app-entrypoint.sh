#!/bin/sh
set -eu

placeholder='__KOAKADEMY_NOT_CONFIGURED__'

load_required_secret() {
    variable_name="$1"
    secret_path="$2"
    value="$(cat "$secret_path")"

    [ -n "$value" ] && [ "$value" != "$placeholder" ] || {
        printf 'Required Docker secret %s is not configured.\n' "$variable_name" >&2
        exit 1
    }

    export "$variable_name=$value"
}

load_optional_secret() {
    variable_name="$1"
    secret_path="$2"

    [ -f "$secret_path" ] || return 0
    value="$(cat "$secret_path")"
    [ -n "$value" ] && [ "$value" != "$placeholder" ] || return 0
    export "$variable_name=$value"
}

load_required_secret APP_KEY /run/secrets/koakademy-app-key
load_required_secret DB_PASSWORD /run/secrets/koakademy-db-password
load_required_secret REDIS_PASSWORD /run/secrets/koakademy-redis-password
load_optional_secret AWS_ACCESS_KEY_ID /run/secrets/koakademy-s3-access-key
load_optional_secret AWS_SECRET_ACCESS_KEY /run/secrets/koakademy-s3-secret-key
load_optional_secret LIBRARY_R2_ACCESS_KEY_ID /run/secrets/koakademy-library-access-key
load_optional_secret LIBRARY_R2_SECRET_ACCESS_KEY /run/secrets/koakademy-library-secret-key
load_optional_secret MAIL_PASSWORD /run/secrets/koakademy-smtp-password
load_optional_secret SEQUENZY_API_KEY /run/secrets/koakademy-sequenzy-api-key
load_optional_secret MEILISEARCH_KEY /run/secrets/koakademy-meilisearch-key

exec /usr/local/bin/start-container "$@"
