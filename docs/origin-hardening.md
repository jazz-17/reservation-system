# Origin Hardening (Cloudflare + Oracle VPS + Docker)

## Goals

- Only Cloudflare can reach the origin on ports **80/443**
- Nginx and Laravel see the **real visitor IP** (not Cloudflare edge IPs)
- Rate limiting cannot be bypassed by spoofed `X-Forwarded-*` headers

## What this repo provides

- Nginx logs tailored for Cloudflare (`docker/nginx/default.conf`)
- A placeholder real-ip config baked into the nginx image (`docker/nginx/cloudflare-realip.conf`)
- A host script to:
  - update `ipset` allowlists for Cloudflare IP ranges
  - generate the Nginx real-ip config file used by the nginx container
  - reload nginx safely

Script: `scripts/cloudflare/update.sh`

## Production setup (VPS)

1) Install dependencies:

```bash
sudo apt-get update
sudo apt-get install -y ipset ipset-persistent netfilter-persistent
```

2) Run updater once (as root):

```bash
cd /srv/reservation-system
sudo CLOUDFLARE_NGINX_REALIP_PATH=/srv/reservation-system/cloudflare/nginx-realip.conf \
  bash scripts/cloudflare/update.sh
```

3) Install the systemd timer:

```bash
sudo cp scripts/cloudflare/systemd/cloudflare-allowlist-update.* /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now cloudflare-allowlist-update.timer
```

4) Verify origin lock:

From a non-Cloudflare network:

```bash
curl -kI https://<ORIGIN_IP>/healthz -H "Host: reservafisi.org.pe"
```

Expected: blocked / no response / dropped.

Through Cloudflare:

```bash
curl -I https://reservafisi.org.pe/healthz
```

Expected: `200 OK`.

## Cloudflare Pseudo IPv4 setting

Cloudflare can be configured to generate a pseudo IPv4 for IPv6 visitors. If set to **Overwrite Headers**, Cloudflare may overwrite `CF-Connecting-IP` (and `X-Forwarded-For`) with the pseudo IPv4 and keep the real IPv6 in `CF-Connecting-IPv6`.

This project’s Nginx setup restores real IP from `CF-Connecting-IP`, so for “true real IP” behavior:

- Set **Pseudo IPv4** to **Off** or **Add header** (recommended).
- Avoid **Overwrite Headers** unless you are OK with `remote_addr` becoming a pseudo IPv4 for IPv6 visitors.

## Notes

- For Docker-published ports (like `-p 80:80`), the updater attaches the allowlist to the `DOCKER-USER` chain when available (falling back to `INPUT` otherwise).
- Run the updater at least once before the first `docker compose up` in production so the bind-mounted real-ip config file exists.
- The host firewall is the primary protection; Nginx real-ip trusts Cloudflare headers and must not be exposed to direct internet traffic.
- Laravel no longer trusts all proxies (`bootstrap/app.php`) and will rely on Nginx-provided `REMOTE_ADDR`.
