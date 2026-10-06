#!/bin/sh
# First boot: .env, APP_KEY, MySQL wait, one migrator, seed once, then config and route cache.
set -eu

cd "${APP_ROOT:-/var/www/html}"

SECRETS_DIR="${SECRETS_DIR:-/var/www/secrets}"
export SECRETS_DIR
KEY_FILE="${SECRETS_DIR}/app.key"
READY_FILE="${SECRETS_DIR}/ready"
SEEDED_FILE="${SECRETS_DIR}/seeded"
LOCK_FILE="${SECRETS_DIR}/bootstrap.lock"

if [ "$(id -u)" -eq 0 ]; then
    mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        "${SECRETS_DIR}"
    chown -R www-data:www-data storage bootstrap/cache "${SECRETS_DIR}"
fi

umask 022

# Artisan, the queue worker, and the scheduler run as www-data.
# php-fpm stays root so the master can open its error log; the pool user is www-data.
run_as_www() {
    if [ "$(id -u)" -eq 0 ]; then
        runuser --preserve-environment -u www-data -- "$@"
    else
        "$@"
    fi
}

read_key_file() {
    if [ -s "${KEY_FILE}" ]; then
        tr -d '\r\n' < "${KEY_FILE}"
    fi
}

persist_key() {
    mkdir -p "${SECRETS_DIR}"
    (umask 077; printf '%s\n' "$1" > "${KEY_FILE}")
}

if [ -z "${APP_KEY:-}" ]; then
    existing="$(read_key_file || true)"
    if [ -n "${existing}" ]; then
        APP_KEY="${existing}"
        export APP_KEY
    else
        APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
        export APP_KEY
        persist_key "${APP_KEY}"
    fi
else
    persist_key "${APP_KEY}"
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty." >&2
    exit 1
fi

write_env_if_missing() {
    if [ -f .env ]; then
        return 0
    fi

    (umask 077; run_as_www php <<'PHP'
<?php

declare(strict_types=1);

$appEnv = getenv('APP_ENV');
if ($appEnv === false || $appEnv === '') {
    $appEnv = 'local';
}

$debugDefault = $appEnv === 'production' ? 'false' : 'true';
$urlDefault = $appEnv === 'production' ? 'http://localhost' : 'http://localhost:8000';

$defaults = [
    'APP_NAME' => 'MP Store',
    'APP_ENV' => $appEnv,
    'APP_KEY' => (string) getenv('APP_KEY'),
    'APP_DEBUG' => $debugDefault,
    'APP_URL' => $urlDefault,
    'APP_TIMEZONE' => 'Asia/Dhaka',
    'APP_LOCALE' => 'en',
    'APP_FALLBACK_LOCALE' => 'en',
    'APP_FAKER_LOCALE' => 'en_US',
    'APP_CURRENCY' => 'BDT',
    'APP_CURRENCY_SYMBOL' => '৳',
    'APP_DATE_FORMAT' => 'd-M-Y',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '12',
    'LOG_CHANNEL' => 'stack',
    'LOG_STACK' => 'single',
    'LOG_LEVEL' => $appEnv === 'production' ? 'info' : 'debug',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'mysql',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'mpstore',
    'DB_USERNAME' => 'mpstore',
    'DB_PASSWORD' => 'mpstore',
    'SESSION_DRIVER' => 'redis',
    'SESSION_LIFETIME' => '120',
    'BROADCAST_CONNECTION' => 'log',
    'FILESYSTEM_DISK' => 'local',
    'QUEUE_CONNECTION' => 'redis',
    'CACHE_STORE' => 'redis',
    'REDIS_CLIENT' => 'phpredis',
    'REDIS_HOST' => 'redis',
    'REDIS_PORT' => '6379',
    'MAIL_MAILER' => 'log',
    'MAIL_FROM_ADDRESS' => 'hello@mpstore.test',
    'MAIL_FROM_NAME' => 'MP Store',
    'SEED_SUPER_ADMIN_NAME' => 'System Administrator',
    'SEED_SUPER_ADMIN_EMAIL' => 'superadmin@mpstore.test',
    'SEED_SUPER_ADMIN_PASSWORD' => 'SuperAdmin#2026',
    'SEED_DEMO_PASSWORD' => 'Partner#2026',
];

if ($defaults['APP_KEY'] === '') {
    fwrite(STDERR, "Refusing to write .env without APP_KEY.\n");
    exit(1);
}

$lines = [
    '# Created by docker/entrypoint.sh. APP_KEY is also stored on the bootstrap_state volume.',
];

foreach ($defaults as $key => $default) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $default;
    }
    $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
    $lines[] = $key.'="'.$escaped.'"';
}

if (file_put_contents('.env', implode("\n", $lines)."\n") === false) {
    fwrite(STDERR, "Could not write .env.\n");
    exit(1);
}
PHP
    )
}

write_env_if_missing

mysql_is_ready() {
    php <<'PHP'
<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'mysql';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'mpstore';
$username = getenv('DB_USERNAME') ?: 'mpstore';
$password = getenv('DB_PASSWORD');
if ($password === false || $password === '') {
    $password = 'mpstore';
}

try {
    new PDO(
        "mysql:host={$host};port={$port};dbname={$database}",
        $username,
        $password,
        [PDO::ATTR_TIMEOUT => 3],
    );
} catch (Throwable) {
    fwrite(STDERR, "waiting for mysql\n");
    exit(1);
}
PHP
}

wait_for_mysql() {
    attempt=0
    while [ "${attempt}" -lt 60 ]; do
        if mysql_is_ready; then
            return 0
        fi
        attempt=$((attempt + 1))
        sleep 2
    done
    echo "MySQL did not become ready." >&2
    exit 1
}

seed_once() {
    if [ "${SEED_ON_BOOT:-false}" != "true" ]; then
        return 0
    fi
    if [ -f "${SEEDED_FILE}" ]; then
        echo "Demo data already seeded."
        return 0
    fi

    echo "Seeding demo data."
    run_as_www php <<'PHP'
<?php

declare(strict_types=1);

$root = getcwd() ?: '/var/www/html';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Illuminate\Support\Facades\DB::beginTransaction();
try {
    $status = $kernel->call('db:seed', ['--force' => true]);
    if ($status !== 0) {
        Illuminate\Support\Facades\DB::rollBack();
        exit($status === 0 ? 1 : $status);
    }
    Illuminate\Support\Facades\DB::commit();
} catch (Throwable $e) {
    Illuminate\Support\Facades\DB::rollBack();
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}

$secrets = getenv('SECRETS_DIR') ?: '/var/www/secrets';
if (touch($secrets.'/seeded') !== true) {
    fwrite(STDERR, "Seed committed but the seed marker could not be written.\n");
    exit(1);
}
PHP
}

bootstrap_database() {
    echo "Preparing the database."
    exec 9>"${LOCK_FILE}"
    flock -w 600 9
    wait_for_mysql
    run_as_www php artisan migrate --force --no-interaction
    seed_once
    if [ ! -L public/storage ]; then
        run_as_www php artisan storage:link --no-interaction
    fi
    touch "${READY_FILE}"
    flock -u 9
    exec 9>&-
}

wait_until_ready() {
    attempt=0
    while [ ! -f "${READY_FILE}" ]; do
        attempt=$((attempt + 1))
        if [ "${attempt}" -gt 300 ]; then
            echo "Timed out waiting for migrations." >&2
            exit 1
        fi
        if [ $((attempt % 10)) -eq 0 ]; then
            echo "Waiting for migrations to finish."
        fi
        sleep 2
    done
}

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    bootstrap_database
else
    wait_until_ready
fi

run_as_www php artisan config:cache --no-interaction
run_as_www php artisan route:cache --no-interaction

if [ "${1:-}" = "php-fpm" ]; then
    exec php-fpm
fi

if [ "$(id -u)" -eq 0 ]; then
    exec runuser --preserve-environment -u www-data -- "$@"
fi

exec "$@"
