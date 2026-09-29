# Deployment

Production runs from `docker-compose.prod.yml`. The app image is a multi-stage build: Composer installs dependencies without dev packages, Node builds the Vite assets, and the final PHP-FPM image runs as `www-data` with OPcache timestamps frozen. Nginx is a separate image that only contains `public/`. MySQL is not published to the host. A queue worker and a scheduler run beside PHP-FPM.

`docker-compose.yml` is the local stack. It bind-mounts the source tree and builds the `development` target, which still includes Composer.

## Environment

Copy `.env.example` to `.env` and set every secret before the first boot. Quote any value that contains `#` or a space.

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
| `APP_PORT` | Host port for nginx. Default `8080`. |

## First deploy

```bash
cp .env.example .env
php artisan key:generate
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```

The app container runs `php artisan migrate --force` on start. Seed is a separate command so a later restart does not reload demo partners. Change the seeded passwords before anyone else can sign in.

Open `http://localhost:8080` (or `APP_URL`).

## API

`GET /api/v1` is public and returns the API version plus the route list (`POST /api/v1/login`, partners, investments, withdrawals, approvals, products, purchases, sales, and `GET /api/v1/reports/monthly`). Other `/api/v1` routes require a Sanctum bearer token from `POST /api/v1/login`. Approve and reject are limited to 30 requests per minute. The API group is limited to 60 requests per minute. Login is limited to 5 attempts per minute.

## Migrations

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

A new image also migrates on boot. Take a database backup before deploying a migration.

## Backups

MySQL data lives in the `mysql_data` volume. Redis AOF lives in `redis_data`.

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
