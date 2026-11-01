# ChurchCRM Production Deployment (Docker Compose + Caddy)

This folder contains a production stack for ChurchCRM:
- `docker-compose.prod.yml`
- `.env.prod.example`
- `deploy/Caddyfile`

## 1. Prepare server

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y docker.io docker-compose-plugin git
sudo usermod -aG docker $USER
newgrp docker
```

## 2. Clone your app code

```bash
git clone https://github.com/gefetobor/churchcrm.git churchcrm
cd churchcrm
```

## 3. Add production files

Copy these files into the repo root on the server:
- `docker-compose.prod.yml`
- `.env.prod.example` (rename to `.env.prod`)
- `deploy/Caddyfile`

## 4. Configure environment

```bash
cp .env.prod.example .env.prod
nano .env.prod
```

Set at minimum:
- `DOMAIN=spiritembassyleeds.co.uk`
- `ACME_EMAIL=<your-email>`
- strong `DB_PASSWORD`
- strong `MYSQL_ROOT_PASSWORD`

## 5. Start production

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

## 6. Point domain to server

In Cloudflare DNS:
- `A` record for `@` -> your VPS IPv4
- `A` record for `www` -> your VPS IPv4 (optional)

Keep proxy **off** initially (DNS only / gray cloud) while testing TLS issuance.

## 7. Verify

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml ps
docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f caddy
docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f churchcrm
```

Then open:
- `https://spiritembassyleeds.co.uk`

## 8. Stop/Start later (without DB reset)

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml stop
docker compose --env-file .env.prod -f docker-compose.prod.yml start
```

Do **not** run `down -v` unless you intentionally want to remove DB data.

## 9. Scheduled jobs (birthday notifications)

The `churchcrm-cron` service runs the daily birthday notification at
`BIRTHDAY_NOTIFICATION_TIME` (default `08:00`, in the `TZ` timezone). It starts
with the rest of the stack; no host crontab is needed.

To use it, open **System Settings > Birthday Notifications** and:
- set **Enable** to True
- add recipient emails (Admin users are included by default)
- keep or edit the HTML template (nothing is sent while the template is empty)

Email must be enabled and SMTP configured under **Email Setup**.

Check the scheduler and run the job by hand:

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f churchcrm-cron
docker compose --env-file .env.prod -f docker-compose.prod.yml exec churchcrm-cron php /var/www/html/src/cron/send-birthday-notifications.php
```

Running it more than once a day is safe: each recipient gets at most one email per day.

## 10. Upgrading to 7.8.0

7.8.0 is the first version where the database upgrade scripts actually run for
this fork (earlier fork versions all reported 7.0.0). After deploying, the
first page load shows the database upgrade page; run it once. The scripts check
for each table and column first, so they are safe on a database that already
has some or all of the changes. Take a database backup before upgrading.
