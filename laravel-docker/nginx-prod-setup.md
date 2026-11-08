# Nginx Production Setup

Use this setup with `laravel-docker/docker-compose.prod.yml`.

The production compose file binds Laravel to localhost through `APP_PORT`. In the current VPS env reference, `APP_PORT=98`, so Nginx should proxy to:

```text
http://127.0.0.1:98
```

## 1. Create The Nginx Site

```bash
sudo nano /etc/nginx/sites-available/tapedb.zabeer.online
```

Paste this config:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tapedb.zabeer.online www.tapedb.zabeer.online;

    client_max_body_size 64M;

    location / {
        proxy_pass http://127.0.0.1:98;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }
}
```

## 2. Enable The Site

```bash
sudo ln -s /etc/nginx/sites-available/tapedb.zabeer.online /etc/nginx/sites-enabled/tapedb.zabeer.online
sudo nginx -t
sudo systemctl reload nginx
```

## 3. Add SSL

```bash
sudo certbot --nginx -d tapedb.zabeer.online -d www.tapedb.zabeer.online
```

## 4. Start Production Containers

From the `laravel-docker` folder:

```bash
cp "vps env" .env
docker compose -f docker-compose.prod.yml up -d
```

## Notes

- Keep MySQL private; do not expose it publicly.
- If `APP_PORT` changes in `.env`, update the Nginx `proxy_pass` port to match.
- phpMyAdmin is local-only. Use SSH tunneling if you need to access it.
