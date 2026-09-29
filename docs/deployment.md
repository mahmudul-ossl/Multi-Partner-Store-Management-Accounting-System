# Deployment

## Local

`docker compose up -d` is the local path. A fresh clone does not need a `.env` file, Composer, npm, `key:generate`, or `migrate`.

The root Compose file builds the multi-stage image (Composer without dev packages, Vite, PHP-FPM), then starts:

| Service | Role |
| --- | --- |
| `app` | PHP-FPM. The only container that migrates and seeds. |
| `nginx` | HTTP on `APP_PORT` (default **8000**). |
| `mysql` | MySQL 8.4. Not published on the host. |
| `redis` | Cache, session, and queue. |
| `queue` | `queue:work`. Waits until migrations have finished. |
| `scheduler` | `schedule:work`. Waits the same way. |

Named volumes: `mysql_data`, `redis_data`, `app_storage` (Laravel `storage/`), and `bootstrap_state` (the generated `APP_KEY` and the one-time seed marker).

On first start the app entrypoint creates `.env` from the container environment, generates `APP_KEY` when it is empty, and stores that key in `bootstrap_state`. It waits until MySQL accepts connections, runs `php artisan migrate --force`, and runs `php artisan db:seed --force` only when `SEED_ON_BOOT=true` and the seed marker is absent. The seed runs in a transaction. A later restart migrates again and does not seed again. The entrypoint then caches config and routes. Queue and scheduler containers do not migrate; they wait for the ready marker on `bootstrap_state`.

```bash
docker compose up -d
```

Open http://localhost:8000. Sign in as `admin@mpstore.test` / `Partner#2026`, or `superadmin@mpstore.test` / `SuperAdmin#2026`.

```bash
docker compose down -v
```

That deletes the database, uploaded files, the seed marker, and the generated key.

`docker compose --profile tools up -d` also starts phpMyAdmin on port 8081 (`PHPMYADMIN_PORT`).

The Dockerfile still has a `development` target (PHP plus the Composer binary only). The root Compose file does not use it.

## Production

Production runs from `docker-compose.prod.yml`. It uses the same image: Composer installs dependencies without dev packages, Node builds the Vite assets, and PHP-FPM workers run as `www-data` with OPcache timestamps frozen. The entrypoint starts as root only to chown the storage and secrets volumes, then re-executes as `www-data`. Nginx is a separate image that serves `public/`. MySQL is not published to the host. `SEED_ON_BOOT=false`, so a restart never reloads demo data. A queue worker and a scheduler run beside PHP-FPM and wait for the app container to finish migrating.

## Environment

Copy `.env.example` to `.env` and set every secret before the first production boot. Quote any value that contains `#` or a space. The local Compose file does not read this file.

| Variable | Production note |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE` | `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Dhaka`. Generate `APP_KEY` with `php artisan key:generate --show`. |
| `APP_CURRENCY`, `APP_CURRENCY_SYMBOL`, `APP_DATE_FORMAT` | `BDT`, `৳`, `d-M-Y`. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Inside Compose, `DB_HOST` is overridden to `mysql`. Do not publish MySQL in production. |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | `REDIS_HOST` is overridden to `redis`. Set a password before exposing Redis beyond the Compose network. |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis` in production. |
| `MAIL_*` | Real mailer. Approval and account notifications are queued. |
| `SEED_SUPER_ADMIN_PASSWORD`, `SEED_DEMO_PASSWORD` | Required only for the first seed. Change them. Do not re-seed a live database. |
| `TRUSTED_PROXIES` | Empty when the app is reached directly. Set `*` only behind a trusted proxy such as Cloudflare. |
| `APP_PORT` | Host port for nginx. Local default `8000`. Production default `8080`. Set `APP_URL` to the same origin. |
| `SEED_ON_BOOT` | `true` on the local Compose file (seed once). `false` in production. |

## First deploy

```bash
cp .env.example .env
php artisan key:generate
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```

The app container runs `php artisan migrate --force` on start, then caches config and routes. With `SEED_ON_BOOT=false` it does not seed. Run the seed command once for a new production database, then change the seeded passwords before anyone else can sign in. Set `APP_KEY` in `.env` before the first boot so the key is one you can back up; if it is empty, the entrypoint generates one and stores it on the `bootstrap_state` volume.

Open `APP_URL` (production Compose publishes nginx on `APP_PORT`, default 8080).

## API

`GET /api/v1` is public and returns the API version plus the route list (`POST /api/v1/login`, partners, investments, withdrawals, approvals, products, purchases, sales, and `GET /api/v1/reports/monthly`). Other `/api/v1` routes require a Sanctum bearer token from `POST /api/v1/login`. Approve and reject are limited to 30 requests per minute. The API group is limited to 60 requests per minute. Login is limited to 5 attempts per minute.

## Migrations

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

A new image also migrates on boot. Take a database backup before deploying a migration.

## Backups

MySQL data lives in the `mysql_data` volume. Redis AOF lives in `redis_data`. Uploaded files live in `app_storage`. The generated or persisted `APP_KEY` and the seed marker live in `bootstrap_state`. Back up `APP_KEY` with the database. Losing that volume invalidates sessions and anything encrypted with the key.

```bash
docker compose -f docker-compose.prod.yml exec mysql \
  mysqldump -u"$DB_USERNAME" -p"$DB_PASSWORD" --single-transaction --routines "$DB_DATABASE" \
  > "mpstore-$(date +%F).sql"
```

Copy that file off the server. Restoring is `mysql` against an empty database, then `php artisan migrate --force` if the dump is older than the code. Uploaded files, if any, are under `storage/app`. Financial history and stock movements are not hard-deleted; backups are the recovery path.

## Upgrading

```bash
git pull
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

OPcache does not re-read PHP files until the container is recreated, which `up -d` does. Confirm nginx is healthy and the queue worker is running:

```bash
docker compose -f docker-compose.prod.yml ps
```

Do not run `migrate:fresh` or `db:seed` on a database that already holds live journals.
