# Cloudflare 521 Error — SSL/TLS Setup Fix

## Problem

With Cloudflare SSL/TLS mode set to **Full**, Cloudflare attempts to reach the origin server over **HTTPS on port 443**. The origin (Docker nginx container) was only listening on **port 80 (HTTP)**, so Cloudflare received a "connection refused" — resulting in a **521 error** for all visitors.

## Root Cause

- `docker/nginx/default.conf` only had `listen 80`
- `docker-compose.prod.yml` only mapped port `80:80`
- No SSL certificate was configured on the origin

## Solution

### 1. Cloudflare Origin Certificate

Generated a Cloudflare Origin Certificate (valid 15 years) from the Cloudflare dashboard:
**SSL/TLS → Origin Server → Create Certificate**

- Hostnames: `*.reservafisi.org.pe`, `reservafisi.org.pe`
- Private key type: RSA (2048)
- Files saved to `docker/nginx/ssl/` (gitignored):
  - `origin.pem` (certificate)
  - `origin.key` (private key, chmod 600)

### 2. Nginx Configuration (`docker/nginx/default.conf`)

Updated to two server blocks:

- **Port 80**: Redirects all HTTP traffic to HTTPS (`301 redirect`)
- **Port 443**: Serves the app over SSL using the Cloudflare Origin Certificate

```nginx
server {
    listen 80;
    server_name _;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name _;

    ssl_certificate /etc/nginx/ssl/origin.pem;
    ssl_certificate_key /etc/nginx/ssl/origin.key;
    ssl_protocols TLSv1.2 TLSv1.3;

    # ... app config (fastcgi_pass app:9000, etc.)
}
```

### 3. Docker Compose (`docker-compose.prod.yml`)

Added port 443 mapping and mounted the SSL certs as a read-only volume:

```yaml
nginx:
  ports:
    - "80:80"
    - "443:443"
  volumes:
    - ./docker/nginx/ssl:/etc/nginx/ssl:ro
```

### 4. Rebuild & Restart

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml build nginx
docker compose --env-file .env.production -f docker-compose.prod.yml up -d nginx
```

## Verification

```bash
# Both ports listening
sudo ss -lntp | egrep ':80|:443'

# HTTPS responds correctly
curl -Ik https://127.0.0.1
# → HTTP/1.1 302 Found, Location: /calendario
```

## Notes

- The SSL cert files are in `.gitignore` (`/docker/nginx/ssl/`)
- The Origin Certificate expires in 2041
- If the certificate needs to be regenerated: Cloudflare dashboard → SSL/TLS → Origin Server
