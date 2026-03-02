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
