# Running an app

An AIC app runs two ways, from the same `bootstrap/serve.php`:

- **Under PHP 8.5**, through `public/index.php`: php-fpm behind nginx, or FrankenPHP. This is the
  runtime to put in production today.
- **Compiled with elephc** into one prefork HTTP server ([native.md](native.md)). It answers as PHP
  does on every step gate:23 runs, but the compiler is young, and its fork carries fixes upstream
  has not taken yet. Treat it as experimental: run it beside PHP, compare, and keep PHP as the
  production path until you trust it with your traffic.

Both serve the committed `build/` output, so a deploy needs no build step and no dev packages:
`composer install --no-dev` is enough. Run `composer run build` (a dev command) before you commit
a change, never on the server.

## What every deploy sets

| Variable | What it is |
|---|---|
| `AIC_APP_SECRET` | The app secret, 32 bytes or more ([security.md](security.md)). The app does not boot without it. |
| `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD` | The Postgres 17 database, as `config/database.php` reads them. |
| `AIC_MAIL_HOST`, `AIC_MAIL_PORT`, `AIC_MAIL_FROM` | Where notifications go out by SMTP. |

Then, once per release and on a schedule:

- `bin/aic migrate` applies the migrations.
- `bin/aic schedule:run` every minute (a systemd timer or cron) queues the schedules due.
- `bin/aic queue:work`, under a service that restarts it, runs the queued jobs.

Stored files live under `var/storage/` ([modules.md](modules.md#assets) covers the public build
assets). Keep `var/storage/` on a volume that survives a release.

## FrankenPHP

FrankenPHP runs AIC in classic mode with no change to the app: each request runs `public/index.php`
with fresh PHP state, as under php-fpm. Worker mode is not supported. AIC's fixture app ships the
two files, under `deploy/frankenphp/`:

```caddyfile
{
	frankenphp
}

{$SERVER_NAME:localhost} {
	root * /app/public
	encode zstd br gzip
	php_server
}
```

```dockerfile
FROM dunglas/frankenphp:php8.5

RUN install-php-extensions pdo_pgsql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json ./
COPY framework framework
COPY gates gates
COPY workflow workflow
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY bin/aic bin/aic
COPY bootstrap bootstrap
COPY build build
COPY config config
COPY modules modules
COPY public public
COPY themes themes
RUN composer dump-autoload --no-dev --optimize --no-scripts

COPY deploy/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
```

An app that requires `aic/*` from Packagist or a VCS repository copies its own root instead of the
three package folders. Build from a clean checkout, so no local `vendor/` or cache goes in:

```bash
docker build -f deploy/frankenphp/Dockerfile -t my-app .
docker run -d --name my-app -p 80:80 -p 443:443 \
  -e SERVER_NAME=example.com -e AIC_APP_SECRET=... -e PGHOST=... -e PGPASSWORD=... my-app
docker exec my-app bin/aic migrate
```

`SERVER_NAME` is a domain, for automatic HTTPS, or a bare port such as `:8080` behind another
proxy. AIC serves `/assets/` itself, with the cache headers [modules.md](modules.md#assets)
describes; the Caddyfile needs no rule for them.

## php-fpm

Point the web server's document root at `public/` and send every request that is not a file to
`public/index.php`. DDEV's nginx-fpm setup in `.ddev/` is the reference: it is how the gates run the
app under PHP.
