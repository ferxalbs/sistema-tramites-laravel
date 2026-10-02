#!/bin/sh
set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
temporary_dir=$(mktemp -d)
trap 'rm -rf "$temporary_dir"' EXIT HUP INT TERM
mkdir -p "$temporary_dir/bin" "$temporary_dir/app"

# Redirect the container-only paths and permission change into a temporary
# directory so the real entrypoint can be exercised without Docker.
sed "s|/app|$temporary_dir/app|g; s|chown -R www-data:www-data|: #|g" \
    "$project_dir/docker/entrypoint.sh" > "$temporary_dir/entrypoint.sh"

cat > "$temporary_dir/bin/php" <<'PHP'
#!/bin/sh
case "$*" in
    *migrate*) exit 23 ;;
esac
exit 0
PHP
cat > "$temporary_dir/bin/frankenphp" <<'SERVER'
#!/bin/sh
touch "$START_MARKER"
SERVER
chmod +x "$temporary_dir/bin/php" "$temporary_dir/bin/frankenphp"

export PATH="$temporary_dir/bin:$PATH"
export START_MARKER="$temporary_dir/started"
export APP_ENV=testing DB_CONNECTION=libsql RAILWAY_ENVIRONMENT= RAILWAY_VOLUME_MOUNT_PATH=

set +e
sh "$temporary_dir/entrypoint.sh" > "$temporary_dir/output" 2>&1
status=$?
set -e
[ "$status" -eq 23 ] || { cat "$temporary_dir/output"; echo "Expected migration failure 23, received $status" >&2; exit 1; }
[ ! -f "$START_MARKER" ] || { echo 'Server started after a migration failure' >&2; exit 1; }

DB_CONNECTION=sqlite DB_DATABASE="$temporary_dir/missing.sqlite" \
    sh "$temporary_dir/entrypoint.sh" > "$temporary_dir/output" 2>&1 && {
        echo 'Server started with a missing SQLite database' >&2
        exit 1
    }
[ ! -f "$START_MARKER" ] || { echo 'Server started without a database' >&2; exit 1; }

cat > "$temporary_dir/bin/mountpoint" <<'MOUNT'
#!/bin/sh
exit 1
MOUNT
chmod +x "$temporary_dir/bin/mountpoint"
RAILWAY_VOLUME_MOUNT_PATH="$temporary_dir/app/storage/app/private" \
    sh "$temporary_dir/entrypoint.sh" > "$temporary_dir/output" 2>&1 && {
        echo 'Server started when the declared volume was not mounted' >&2
        exit 1
    }
[ ! -f "$START_MARKER" ] || { echo 'Server started without its volume' >&2; exit 1; }

echo 'Entrypoint rejects migration failure, missing database and missing declared volume.'
