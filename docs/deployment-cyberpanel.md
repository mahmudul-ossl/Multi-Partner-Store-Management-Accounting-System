# Deploying to a CyberPanel subdomain

`.github/workflows/deploy.yml` runs the test suite on every push and pull request. On a push to `main` that passes, it builds the app (Composer without dev packages, then `npm run build`), uploads it over SSH, and runs `scripts/deploy/cyberpanel-deploy.sh` on the server. Nothing is built on the server, so it doesn't need Composer or Node.

Each deploy goes into its own `releases/<timestamp>-<sha>` directory. The script runs migrations, caches config and routes, switches the `current` symlink, restarts the queue workers, and restarts `lsphp` so OPcache serves the new code. The five most recent releases are kept.

```
/home/app.example.com/
├── releases/20261008101500-a1b2c3d/
├── shared/.env
├── shared/storage/
├── current -> releases/20261008101500-a1b2c3d
└── public_html -> current/public
```

## 1. Create the subdomain

In CyberPanel, go to **Websites → Create Website** and enter the subdomain (for example `app.example.com`) as the domain. Choose **PHP 8.3**. A separate website gets its own Linux user and `/home/app.example.com`, which keeps it apart from the main site.

Then:

- **Databases → Create Database**: a MySQL database and user for the app.
- **Websites → Manage → SSL → Issue SSL**.
- Check the PHP extensions under **Server → PHP → Install Extensions** (lsphp83): `mbstring`, `intl`, `bcmath`, `mysql` (pdo_mysql), `redis`.
- Redis: `QUEUE_CONNECTION=redis`. Install Redis on the server (CyberPanel → **Manage Services**, or `apt install redis-server`), or set the cache, queue, and session drivers to `database` in `.env`.

If you created the subdomain as a **Child Domain** instead, set its path to something outside the parent's `public_html`, for example `/home/example.com/app/public_html`, and use `/home/example.com/app` as `DEPLOY_PATH` below.

## 2. SSH access for the site user

Go to **Websites → Manage → SSH Access** (SSH and SFTP) for the subdomain, and enable a shell for its user. Deploys run as that user, so files keep the right owner and no `chown` is needed.

Create a key pair just for deploys on your machine:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/mpstore_deploy -N "" -C "github-actions-deploy"
```

Add `~/.ssh/mpstore_deploy.pub` to `/home/app.example.com/.ssh/authorized_keys` on the server (mode `700` on `.ssh`, `600` on the file). Check that it works:

```bash
ssh -i ~/.ssh/mpstore_deploy app-user@your-server-ip 'php -v; /usr/local/lsws/lsphp83/bin/php -v'
```

## 3. First-time server setup

Run these as the site user on the server:

```bash
cd /home/app.example.com
mkdir -p releases shared/storage
nano shared/.env
```

Fill in `shared/.env` from `.env.example` and the production notes in [deployment.md](deployment.md#environment). At least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
APP_KEY=base64:...        # php artisan key:generate --show
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
TRUSTED_PROXIES=          # "*" only behind Cloudflare
```

Then point the document root at the live release. Move the default `public_html` aside and replace it with a symlink:

```bash
mv public_html public_html.default
ln -s current/public public_html
```

OpenLiteSpeed follows this symlink because the target stays inside the site's home directory. If you prefer not to use a symlink, open **Websites → Manage → vHost Conf** and set `docRoot $VH_ROOT/current/public` instead.

The symlink won't resolve until the first deploy creates `current`, so the site returns 404 until then.

## 4. GitHub secrets and variables

In the repository, open **Settings → Environments**, create an environment named `production` (you can add required reviewers there to gate deploys), and add:

| Kind | Name | Value |
| --- | --- | --- |
| Secret | `SSH_HOST` | Server IP or hostname |
| Secret | `SSH_PORT` | SSH port. Optional, defaults to `22`. |
| Secret | `SSH_USER` | The subdomain's Linux user |
| Secret | `SSH_PRIVATE_KEY` | Contents of `~/.ssh/mpstore_deploy` |
| Secret | `SSH_KNOWN_HOSTS` | Output of `ssh-keyscan -p 22 your-server-ip` (run it once and check the fingerprint) |
| Variable | `DEPLOY_PATH` | `/home/app.example.com` |
| Variable | `PHP_BIN` | Optional. Defaults to `/usr/local/lsws/lsphp83/bin/php`. |

Push to `main`, or start the workflow from the **Actions** tab.

## 5. After the first deploy

Seed the database once, then change the seeded passwords:

```bash
cd /home/app.example.com/current
/usr/local/lsws/lsphp83/bin/php artisan db:seed --force
```

Add the scheduler and a queue worker in **Cron Jobs** (CyberPanel → Websites → Manage → Cron Jobs) for the site user:

```cron
* * * * * /usr/local/lsws/lsphp83/bin/php /home/app.example.com/current/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/local/lsws/lsphp83/bin/php /home/app.example.com/current/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

The cron worker is enough for queued notifications. For a long-running worker, use Supervisor with `queue:work --max-time=3600`; deploys call `queue:restart` either way.

## Rollback

Point `current` at an earlier release and restart lsphp:

```bash
cd /home/app.example.com
ls -1t releases
ln -sfn releases/<previous> current.next && mv -Tf current.next current
pkill -u "$(id -un)" lsphp
```

Migrations are not rolled back automatically. Take a database backup before deploying a migration (see [deployment.md](deployment.md#backups)).

## Troubleshooting

- **404 on every route except `/`**: rewrite rules aren't loading. In **vHost Conf**, check that the context has `rewrite { enable 1 autoLoadHtaccess 1 }`, or copy the rules from `public/.htaccess` into **Rewrite Rules**.
- **Old code still served**: the deploy user couldn't stop lsphp. Restart OpenLiteSpeed from CyberPanel, or run `systemctl restart lsws` as root.
- **500 after deploy**: check `shared/storage/logs/laravel.log`. Make sure `shared/.env` exists and has `APP_KEY`.
- **Permission denied writing storage**: `shared/` must belong to the site user (`chown -R app-user:app-user shared` as root).
