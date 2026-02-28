# Deployment Guide
157.137.233.117
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

### First-time Setup

```bash
# 1. Install Docker
curl -fsSL https://get.docker.com | sh

# 2. Clone the repo
git clone https://github.com/your-user/reservation-system.git
cd reservation-system

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

#    IMPORTANT: `docker-compose.prod.yml` uses ${DB_PASSWORD} for Postgres init.
#    Use --env-file so Compose reads DB_PASSWORD (and other variables) from .env.production.
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build

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
```

### Useful Commands

```bash
# View logs
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f app

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
