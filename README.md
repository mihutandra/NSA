# NSA Project – Docker Infrastructure

A fully containerised web stack satisfying all project requirements.

---

## Architecture

```
                     ┌───────────────────────────────────────┐
Internet / Browser   │  Host machine (Linux VM / bare metal) │
                     │                                       |
  :8080 (HTTP)  ───► │  nginx  ─── redirect to HTTPS ──────► │
  :8443 (HTTPS) ───► │  nginx  ─── load-balance ───────────► │  webapp1 (PHP)
                     │         ├── /phpmyadmin/ ───────────► │  webapp2 (PHP)
                     │         ├── /mail/       ───────────► │  phpMyAdmin
                     │         └── /logs/       (static) ──► │  MailHog
                     │                                       │  GoAccess (log reporter)
                     │  db-master (MySQL, writes)            │
                     │  db-slave  (MySQL, read-only)         │
                     │  redis     (shared session store)     │
                     └───────────────────────────────────────┘
```

| Service | Purpose | Points |
|---------|---------|--------|
| nginx | Reverse proxy, load balancer, TLS termination | 0.5 + 1 + 1 |
| webapp1, webapp2 | PHP web app (IP display, auth, CRUD) – 2 replicas | 0.5 + 1 |
| db-master | MySQL 8 master | 0.5 + 1 |
| db-slave | MySQL 8 slave (GTID replication) | 1 |
| phpmyadmin | phpMyAdmin UI (restricted) | 0.5 |
| redis | Shared session store (preserves sessions across replicas) | 0.5 |
| mailhog | SMTP mail server (email confirmation) | 0.5 |
| goaccess | Centralised web-log analyser | 0.5 |
| TLS on port 8443 | Self-signed HTTPS | 1 |
| Domain `nsa.local` | Custom domain instead of localhost | 0.5 |

---

## Requirements

* A Linux machine (physical or VM)
* Docker Engine ≥ 24 and Docker Compose plugin (`docker compose`)

Install on Debian/Ubuntu:
```bash
sudo apt-get update
sudo apt-get install -y docker.io docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker $USER   # re-login after this
```

---

## Quick Start

### 1. Clone and configure

```bash
git clone https://github.com/mihutandra/NSA.git
cd NSA
cp .env.example .env
# Edit .env to set strong passwords (optional for a local test environment)
```

### 2. Add domain name to /etc/hosts

```bash
echo "127.0.0.1  nsa.local" | sudo tee -a /etc/hosts
```

### 3. Start all services

```bash
docker compose up -d --build
```

The first start takes a few minutes while Docker pulls images and builds
the PHP and nginx containers.

### 4. Access the services

| URL | Service | Access |
|-----|---------|--------|
| <https://nsa.local:8443/> | Web application | public (auth required) |
| <https://nsa.local:8443/phpmyadmin/> | phpMyAdmin | private networks only (auto-login as `root`) |
| <https://nsa.local:8443/mail/> | MailHog web UI | private networks only |
| <https://nsa.local:8443/logs/> | GoAccess log report | private networks only |
| <http://nsa.local:8080/> | HTTP – redirects to HTTPS | — |

> **Browser TLS warning**: The certificate is self-signed. Accept the warning in
> your browser to proceed.

> **phpMyAdmin login note**: phpMyAdmin authenticates against **MySQL users** (for example `root` or `webapp` from `.env`), not web-app accounts created via `/register.php`.

### Scripts

<remarks>
This command creates a fresh project installation and installs all required
dependencies via Composer. The "--fresh" flag ensures a clean installation by removing
any existing vendor directory and node_modules, then reinstalling everything from scratch.
This is useful for ensuring a consistent, dependency-free starting point without any
stale or cached packages.
</remarks>

#### `start.sh`
Starts all containers in detached mode and displays service URLs.
```bash
docker compose up -d --build
echo "Services running at https://nsa.local:8443/"
```

#### `stop.sh`
Stops and removes all running containers (preserves volumes and data).
```bash
docker compose down
echo "All services stopped."
```

Run scripts with:
```bash
chmod +x start.sh stop.sh
./start.sh
./stop.sh
```

---

## Features

### Authentication
- Register with username + email + password (min 8 chars)
- Confirmation email sent via MailHog (check `/mail/`)
- Login / Logout

### CRUD
After logging in, visit the **Dashboard** to Create, Read, Update, and Delete
personal items stored in MySQL.

### Server IP Display
The Dashboard shows the **container IP** of the webapp replica that served the
request, along with the **client IP** forwarded by nginx. Reload the page a few
times to see the two replicas respond in round-robin.

### Session Persistence
Sessions are stored in **Redis**, so logging in on `webapp1` keeps you logged in
even when subsequent requests hit `webapp2`.

### Email Confirmation
Registration sends a confirmation link via **MailHog** (an in-memory SMTP
server). Open `https://nsa.local:8443/mail/` to view captured emails and click
the confirmation link.

### MySQL Master/Slave Replication
- **db-master** handles all writes (schema creation, user data).
- **db-slave** replicates via GTID replication (read-only).
- A one-shot container (`db-slave-init`) configures replication automatically on
  first start.

### Nginx Load Balancer + Access Lists
- Upstream `webapp_backend` load-balances between `webapp1` and `webapp2`
  (round-robin by default).
- `/phpmyadmin/`, `/mail/`, and `/logs/` are protected with IP-based **access
  lists** — only RFC-1918 addresses (10.0.0.0/8, 172.16.0.0/12,
  192.168.0.0/16) and localhost are allowed; all others receive **403 Forbidden**.

### HTTPS on Port 8443
A **self-signed RSA-2048 certificate** is generated automatically by the nginx
container on first start (stored in a Docker volume so it persists across
restarts).

### Centralised Log Analysis (GoAccess)
The `goaccess` container reads the nginx access log (shared volume) and
regenerates an **HTML report** every 30 seconds. View it at
`https://nsa.local:8443/logs/`.

---

## Useful Commands

```bash
# View logs for a service
docker compose logs -f nginx
docker compose logs -f webapp1

# Check replication status
docker compose exec db-slave mysql -u root -p<password> -e "SHOW SLAVE STATUS\G"

# Restart a single service
docker compose restart webapp1

# Stop everything
docker compose down

# Remove everything including volumes (⚠ data loss)
docker compose down -v
```

---

## Environment Variables (`.env`)

| Variable | Description | Default |
|----------|-------------|---------|
| `MYSQL_ROOT_PASSWORD` | MySQL root password | `rootpassword123` |
| `MYSQL_DATABASE` | Application database name | `nsa` |
| `MYSQL_USER` | Application DB user | `webapp` |
| `MYSQL_PASSWORD` | Application DB password | `webapppass123` |
| `REPLICATION_PASSWORD` | MySQL replication user password | `replpass123` |
| `REDIS_PASSWORD` | Redis AUTH password | `redispass123` |
| `APP_URL` | Full public URL (used in confirmation emails) | `https://nsa.local:8443` |
| `DOMAIN` | Server name for nginx | `nsa.local` |
