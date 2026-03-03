# Deployment Guide

## Local Development

```bash
# Start services (PostgreSQL, Redis, Mailpit)
docker compose up -d

# Run the app
composer run dev
```

- App: http://localhost:8000
- Mailpit: http://localhost:8025

## Production (VPS)

### Oracle Cloud — Firewall Setup (one-time)

Oracle Cloud blocks all ports by default at **two independent layers**. Both must be opened or the server will time out even if the app is running.

**1. Cloud Security List** (Oracle Console → Networking → VCN → Security Lists → Default):

Add two Ingress Rules (Cloud Security List). If Oracle limits the number of CIDR rules you can add, keep these open and enforce Cloudflare-only at the OS firewall (next step):

| Stateless | Source CIDR | Protocol | Dest. Port | Description |
|-----------|-------------|----------|------------|-------------|
| No | 0.0.0.0/0 | TCP | 80 | HTTP (will be restricted by OS firewall) |
| No | 0.0.0.0/0 | TCP | 443 | HTTPS (will be restricted by OS firewall) |

**2. OS firewall (Cloudflare-only)** (inside the VPS):

```bash
# Install dependencies (Debian/Ubuntu)
sudo apt-get update
sudo apt-get install -y ipset ipset-persistent netfilter-persistent

# Run the Cloudflare allowlist updater (updates ipset + iptables and generates nginx real-ip config)
cd /srv/reservation-system
sudo bash scripts/cloudflare/update.sh

# Persist rules across reboots
sudo netfilter-persistent save
```

This locks ports **80/443** to Cloudflare IP ranges. Direct-to-origin requests (curling the VPS IP with `Host: reservafisi.org.pe`) will be blocked.
When Docker is used for port publishing, rules are attached to `DOCKER-USER` (fallback: `INPUT`) so they apply to container traffic as well.

---

### First-time Setup

```bash
# 1. Install Docker
curl -fsSL https://get.docker.com | sh

# 2. Clone the repo
sudo mkdir -p /srv
sudo chown -R "$USER":"$USER" /srv
git clone https://github.com/your-user/reservation-system.git /srv/reservation-system
cd /srv/reservation-system

# 3. Create .env.production (see .env.example)
#    Key values to set:
#      APP_ENV=production
#      APP_DEBUG=false
#      APP_KEY=         (generate locally: php artisan key:generate --show)
#      APP_URL=http://your-vps-ip
#      DB_HOST=postgres
#      DB_PORT=5432
#      DB_PASSWORD=strong-password
#      REDIS_HOST=redis
#      LOG_LEVEL=error

# 4. Build and start
#
#    If you’re running “production” locally and don’t want to create /srv,
#    you can override the storage mount:
#    APP_STORAGE_PATH=./storage docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
#
#    The containers run as uid 1000 (user "www"). Ensure storage is writable
#    so queued jobs can generate/read PDFs.
sudo mkdir -p /srv/reservation-system/storage
sudo chown -R 1000:1000 /srv/reservation-system/storage

#    IMPORTANT: run the Cloudflare updater once before the first `docker compose up`
#    so the bind-mounted real-ip config exists (see `docs/origin-hardening.md`).
sudo bash scripts/cloudflare/update.sh

#    IMPORTANT: `docker-compose.prod.yml` uses ${DB_PASSWORD} for Postgres init.
#    Use --env-file so Compose reads DB_PASSWORD (and other variables) from .env.production.
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build

# NOTE: `docker-compose.prod.yml` includes a `queue` service that processes queued jobs (PDF generation, reservation emails, and email verification notifications).

# 5. Run migrations and seed
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan db:seed --force
```

### Deploying Changes

```bash
cd reservation-system
git pull
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan migrate --force

# Reload PHP-FPM to clear OPcache after a rebuild (bytecode is cached from build time)
docker compose --env-file .env.production -f docker-compose.prod.yml exec app kill -USR2 1
```

### Useful Commands

```bash
# View logs
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f app
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f queue

# Restart services
docker compose --env-file .env.production -f docker-compose.prod.yml restart

# Stop everything
docker compose --env-file .env.production -f docker-compose.prod.yml down

# Stop and delete database volume (destructive!)
docker compose --env-file .env.production -f docker-compose.prod.yml down -v

# Run artisan commands
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan <command>

# Open a shell in the app container
docker compose --env-file .env.production -f docker-compose.prod.yml exec app sh
```

### Connecting to Production DB (via SSH tunnel)

Do not expose the database port. Use an SSH tunnel instead:

```bash
ssh -L 5432:localhost:5432 user@your-vps-ip
```

Then connect DBeaver to `localhost:5432` with your DB credentials.

Alternatively, configure the SSH tunnel directly in DBeaver's connection settings.
