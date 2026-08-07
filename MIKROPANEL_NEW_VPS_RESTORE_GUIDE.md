# MikroPanel - GitHub থেকে নতুন VPS-এ Full Restore Guide

**Project:** MikroPanel (Laravel + React/Inertia + MikroTik API)  
**GitHub Repository:** `git@github.com:nurulqatar/mikropanel.git`  
**Target OS:** Ubuntu Server 24.04  
**Web Server:** Nginx, Port 80  
**PHP:** 8.3  
**Database:** MySQL  
**Prepared:** 2026-08-07

---

## 1. এই গাইডের উদ্দেশ্য

এই ডকুমেন্ট ব্যবহার করে পুরোনো VPS নষ্ট হয়ে গেলে নতুন Ubuntu VPS-এ MikroPanel পুনরায় চালু করা যাবে। সবচেয়ে নিরাপদ recovery পদ্ধতি হলো:

**GitHub code + পুরোনো `.env` + latest MySQL backup + প্রয়োজন হলে WireGuard configuration**

GitHub থেকে শুধু application code পাওয়া যাবে। Client, Invoice, Payment, Accounting, Router credentials এবং runtime data GitHub-এ থাকার কথা নয়।

## 2. কোন জিনিস কোথায় থাকবে

| জিনিস | কোথায় রাখবেন | GitHub-এ যাবে? |
|---|---|---|
| Laravel/React source code | Private GitHub repo | হ্যাঁ |
| `.env` / `APP_KEY` | PC / encrypted backup / DR archive | না |
| MySQL database | Daily DB backup + PC/cloud copy | না |
| Full DR archive | PC / secure cloud / external disk | না |
| WireGuard private config | Secure PC backup / encrypted archive | না |
| Nginx example/config | DR archive বা repo-র docs/deploy folder | secret না থাকলে হ্যাঁ |

> **Security:** `.env`, database dump, WireGuard private key বা router password কখনো public GitHub repository-তে push করবেন না। Repository Private রাখুন।

## 3. Disaster হওয়ার আগেই যা নিশ্চিত করবেন

### 3.1 GitHub sync check

```bash
cd /var/www/mikropanel || exit 1

git status
git remote -v
git log -1 --oneline
git push
```

Expected: `working tree clean` এবং `origin/main` up-to-date।

### 3.2 `.env` GitHub-এ নেই কিনা

```bash
cd /var/www/mikropanel || exit 1

git check-ignore .env
git ls-files .env
git log --all --oneline -- .env
```

- `git check-ignore .env` -> `.env` দেখাতে পারে।
- `git ls-files .env` -> **blank** হতে হবে।
- `.env` history-তেও না থাকাই নিরাপদ।

### 3.3 Daily database backup

```bash
sudo ls -lh /var/backups/mikropanel | tail -20
```

### 3.4 Full DR backup validate

```bash
LATEST="$(sudo find /var/backups/mikropanel \
  -maxdepth 1 -type f -name 'mikropanel-DR-*.tar.gz' \
  -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)"

echo "$LATEST"
sudo tar -tzf "$LATEST" >/dev/null && echo "BACKUP_ARCHIVE=OK"
cd /var/backups/mikropanel
sudo sha256sum -c "$(basename "$LATEST").sha256"
```

### 3.5 WireGuard backup - গুরুত্বপূর্ণ

MikroTik API যদি WireGuard tunnel দিয়ে চলে, GitHub code restore করলেই tunnel ফিরবে না। Current server alive থাকলে আলাদা backup নিন:

```bash
sudo mkdir -p /var/backups/mikropanel
STAMP="$(date +%Y%m%d_%H%M%S)"

sudo tar -czf \
  "/var/backups/mikropanel/wireguard-${STAMP}.tar.gz" \
  /etc/wireguard

sudo chmod 600 \
  "/var/backups/mikropanel/wireguard-${STAMP}.tar.gz"
```

তারপর file-টি PC/secure cloud-এ copy করুন।

---

# PART A - নতুন VPS-এ GitHub থেকে Full Restore

## 4. নতুন VPS প্রস্তুত করুন

SSH দিয়ে নতুন Ubuntu 24.04 VPS-এ root হিসেবে login করুন।

```bash
apt update

apt install -y \
  nginx \
  mysql-server \
  php8.3-fpm \
  php8.3-cli \
  php8.3-mysql \
  php8.3-curl \
  php8.3-mbstring \
  php8.3-xml \
  php8.3-zip \
  php8.3-bcmath \
  php8.3-gd \
  php8.3-intl \
  unzip \
  curl \
  git \
  composer

systemctl enable --now mysql nginx php8.3-fpm
```

Check:

```bash
systemctl is-active mysql
systemctl is-active nginx
systemctl is-active php8.3-fpm
```

তিনটাতেই `active` আসা উচিত।

### 4.1 Node.js 22 install

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs

node -v
npm -v
```

## 5. নতুন VPS-এর GitHub SSH key তৈরি করুন

```bash
mkdir -p /root/.ssh
chmod 700 /root/.ssh

ssh-keygen \
  -t ed25519 \
  -C "mikropanel-new-vps" \
  -f /root/.ssh/id_ed25519 \
  -N ""

cat /root/.ssh/id_ed25519.pub
```

Output-এর পুরো `ssh-ed25519 ...` public-key line GitHub -> Settings -> SSH and GPG keys -> New SSH key-এ add করুন। Private key `/root/.ssh/id_ed25519` share করবেন না।

```bash
ssh -T git@github.com
```

প্রথমবার confirmation চাইলে `yes` লিখুন।

## 6. GitHub থেকে MikroPanel clone করুন

```bash
mkdir -p /var/www
cd /var/www

git clone git@github.com:nurulqatar/mikropanel.git mikropanel

cd /var/www/mikropanel || exit 1
git branch -vv
git log -1 --oneline
```

## 7. PHP এবং frontend dependencies install করুন

```bash
cd /var/www/mikropanel || exit 1

COMPOSER_ALLOW_SUPERUSER=1 composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction

if [ -f package-lock.json ]; then
    npm ci
else
    npm install
fi

npm run build
```

Build error হলে পরবর্তী step-এ যাবেন না।

## 8. পুরোনো `.env` restore করুন

### Method A - আলাদা `.env` backup থাকলে

PC PowerShell:

```powershell
scp .\mikropanel.env.backup root@NEW_VPS_IP:/var/www/mikropanel/.env
```

VPS:

```bash
chmod 600 /var/www/mikropanel/.env
```

### Method B - Full DR archive থেকে `.env` বের করা

PC PowerShell:

```powershell
scp .\mikropanel-DR-YYYYMMDD_HHMMSS.tar.gz root@NEW_VPS_IP:/root/
```

VPS:

```bash
mkdir -p /root/mikropanel-dr /root/mikropanel-env

DR_FILE="$(find /root -maxdepth 1 -type f \
  -name 'mikropanel-DR-*.tar.gz' \
  -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)"

tar -xzf "$DR_FILE" -C /root/mikropanel-dr

PROJECT_TAR="$(find /root/mikropanel-dr \
  -type f -name project.tar.gz | head -1)"

tar -xzf "$PROJECT_TAR" \
  -C /root/mikropanel-env \
  var/www/mikropanel/.env

cp /root/mikropanel-env/var/www/mikropanel/.env \
  /var/www/mikropanel/.env

chmod 600 /var/www/mikropanel/.env
```

Check (secret value share করবেন না):

```bash
cd /var/www/mikropanel || exit 1
grep -E '^APP_(ENV|DEBUG|URL)=' .env
grep -E '^DB_(HOST|PORT|DATABASE|USERNAME)=' .env
```

> **Important:** পুরোনো `.env` restore করার পরে `php artisan key:generate` চালাবেন না। পুরোনো `APP_KEY` রাখতে হবে।

## 9. MySQL database এবং Laravel DB user তৈরি করুন

```bash
cd /var/www/mikropanel || exit 1

python3 <<'PYDB' > /root/mikropanel-db-setup.sql
from pathlib import Path

env = {}
for raw in Path('.env').read_text().splitlines():
    raw = raw.strip()
    if not raw or raw.startswith('#') or '=' not in raw:
        continue
    key, value = raw.split('=', 1)
    value = value.strip()
    if len(value) >= 2 and value[0] == value[-1] and value[0] in "\"'":
        value = value[1:-1]
    env[key] = value

def esc(value):
    return value.replace('\\', '\\\\').replace("'", "''")

db = env.get('DB_DATABASE', 'mikropanel')
user = env.get('DB_USERNAME', 'mikropanel')
password = env.get('DB_PASSWORD', '')

print(f"CREATE DATABASE IF NOT EXISTS `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
for host in ('127.0.0.1', 'localhost'):
    print(f"CREATE USER IF NOT EXISTS '{esc(user)}'@'{host}' IDENTIFIED BY '{esc(password)}';")
    print(f"ALTER USER '{esc(user)}'@'{host}' IDENTIFIED BY '{esc(password)}';")
    print(f"GRANT ALL PRIVILEGES ON `{db}`.* TO '{esc(user)}'@'{host}';")
print('FLUSH PRIVILEGES;')
PYDB

mysql < /root/mikropanel-db-setup.sql
rm -f /root/mikropanel-db-setup.sql
```

```bash
mysql -e "SHOW DATABASES LIKE 'mikropanel';"
```

## 10. MySQL data restore করুন

### Method A - আলাদা latest DB backup

```powershell
scp .\mikropanel-latest.sql.gz root@NEW_VPS_IP:/root/
```

```bash
gunzip -c /root/mikropanel-latest.sql.gz | mysql mikropanel
```

### Method B - Full DR archive থেকে database restore

```bash
DB_DUMP="$(find /root/mikropanel-dr \
  -type f -name database.sql.gz | head -1)"

echo "$DB_DUMP"
gunzip -c "$DB_DUMP" | mysql mikropanel
```

Data check:

```bash
mysql mikropanel -e "
SHOW TABLES;
SELECT COUNT(*) AS users FROM users;
SELECT COUNT(*) AS routers FROM routers;
SELECT COUNT(*) AS packages FROM packages;
SELECT COUNT(*) AS clients FROM clients;
SELECT COUNT(*) AS invoices FROM invoices;
SELECT COUNT(*) AS payments FROM payments;
"
```

> **Danger:** restore-এর সময় `php artisan migrate:fresh` বা `php artisan db:wipe` চালাবেন না।

## 11. Laravel permissions এবং migration finalize করুন

```bash
cd /var/www/mikropanel || exit 1

chown -R root:root /var/www/mikropanel
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --force
```

## 12. Production `.env` settings ঠিক করুন

```bash
cd /var/www/mikropanel || exit 1

sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i 's|^APP_URL=.*|APP_URL=http://NEW_PUBLIC_IP|' .env

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan about | head -30
```

`NEW_PUBLIC_IP` নতুন VPS public IP/domain দিয়ে replace করুন। Expected: Environment `production`, Debug Mode `OFF`।

## 13. Nginx Port 80 setup

### Method A - DR archive-এর Nginx config

```bash
NGINX_BACKUP="$(find /root/mikropanel-dr \
  -type f -name nginx-mikropanel.conf | head -1)"

cp "$NGINX_BACKUP" /etc/nginx/sites-available/mikropanel
```

### Method B - নতুন config

```bash
cat >/etc/nginx/sites-available/mikropanel <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /var/www/mikropanel/public;
    index index.php index.html;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
```

Enable:

```bash
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/mikropanel /etc/nginx/sites-enabled/mikropanel
nginx -t
systemctl restart php8.3-fpm nginx
ss -ltnp | grep ':80 '
```

Nginx test fail হলে restart করার আগে error fix করুন।

## 14. Laravel scheduler/cron চালু করুন

```bash
(
  crontab -l 2>/dev/null | grep -v 'artisan schedule:run'
  echo '* * * * * cd /var/www/mikropanel && /usr/bin/php artisan schedule:run >> /dev/null 2>&1'
) | crontab -

crontab -l
cd /var/www/mikropanel
php artisan schedule:list
```

Expected:

```text
clients:suspend-expired
clients:sync-connection
clients:sync-usage
```

Manual test:

```bash
php artisan clients:sync-usage
echo "USAGE_EXIT=$?"
php artisan clients:sync-connection
echo "CONNECTION_EXIT=$?"
php artisan clients:suspend-expired
echo "SUSPEND_EXIT=$?"
```

তিনটাতেই exit code `0` হওয়া উচিত।

## 15. WireGuard এবং MikroTik reconnect

GitHub/database restore সফল হলেও MikroTik API connection fail করতে পারে যদি management WireGuard-এর উপর নির্ভর করে। WireGuard backup থাকলে:

```bash
apt install -y wireguard
mkdir -p /root/wg-restore

tar -xzf /root/wireguard-YYYYMMDD_HHMMSS.tar.gz \
  -C /root/wg-restore

cp -a /root/wg-restore/etc/wireguard/. /etc/wireguard/
chmod 600 /etc/wireguard/*.conf 2>/dev/null || true

systemctl enable --now wg-quick@wg0
wg show
```

Config filename `wg0.conf` না হলে service name পরিবর্তন করুন। তারপর panel-এর Router -> Ping/Sync test করুন। MikroTik-side peer-ও নতুন VPS অনুযায়ী update লাগতে পারে।

## 16. Daily database backup নতুন VPS-এ বসান

```bash
mkdir -p /var/backups/mikropanel
chmod 700 /var/backups/mikropanel

cat >/usr/local/bin/mikropanel-backup <<'BASHBACKUP'
#!/bin/bash
set -euo pipefail

BACKUP_DIR="/var/backups/mikropanel"
STAMP="$(date +%Y%m%d_%H%M%S)"
FILE="$BACKUP_DIR/mikropanel_$STAMP.sql.gz"

mysqldump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  mikropanel \
  | gzip > "$FILE"

test -s "$FILE"
find "$BACKUP_DIR" -type f -name 'mikropanel_*.sql.gz' -mtime +14 -delete
BASHBACKUP

chmod 700 /usr/local/bin/mikropanel-backup
/usr/local/bin/mikropanel-backup
```

Cron:

```bash
(
  crontab -l 2>/dev/null | grep -v '/usr/local/bin/mikropanel-backup'
  echo '15 2 * * * /usr/local/bin/mikropanel-backup >/dev/null 2>&1'
) | crontab -
```

> **Important:** VPS-এর ভিতরের backup একাই যথেষ্ট নয়। নিয়মিত PC/cloud-এ copy করুন।

## 17. Final health check

```bash
cd /var/www/mikropanel || exit 1

echo '===== APP ====='
php artisan about | head -30

echo '===== SERVICES ====='
systemctl is-active nginx
systemctl is-active php8.3-fpm
systemctl is-active mysql

echo '===== PORT ====='
ss -ltnp | grep ':80 '

echo '===== DATABASE ====='
php artisan tinker --execute='
dump([
    "database" => DB::connection()->getDatabaseName(),
    "users" => App\Models\User::count(),
    "routers" => App\Models\Router::count(),
    "packages" => App\Models\Package::count(),
    "clients" => App\Models\Client::count(),
    "archived_clients" => App\Models\Client::onlyTrashed()->count(),
    "invoices" => App\Models\Invoice::count(),
    "payments" => App\Models\Payment::count(),
]);
'

echo '===== SCHEDULER ====='
php artisan schedule:list
```

Browser: `http://NEW_PUBLIC_IP`

Login হওয়ার পর: Dashboard -> Router Ping -> Router Sync -> Client/Invoice/Payment counts -> Accounting -> Invoice Print/PDF test।

---

# PART B - GitHub দিয়ে Normal Update Workflow

## 18. বর্তমান server থেকে code push

```bash
cd /var/www/mikropanel || exit 1

git status
git add .
git commit -m "Update MikroPanel"
git push
```

`.env` check:

```bash
git ls-files .env
```

Output blank হতে হবে।

## 19. অন্য server-এ latest code pull

```bash
cd /var/www/mikropanel || exit 1

git pull --ff-only

COMPOSER_ALLOW_SUPERUSER=1 composer install \
  --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [ -f package-lock.json ]; then npm ci; else npm install; fi
npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

# PART C - Emergency Rules

## 20. Recovery-এর সময় এই commandগুলো চালাবেন না

```text
php artisan migrate:fresh
php artisan db:wipe
php artisan key:generate      (পুরোনো .env/APP_KEY restore করার পরে)
DROP DATABASE mikropanel
TRUNCATE clients
TRUNCATE invoices
TRUNCATE payments
```

## 21. Common সমস্যা

### 21.1 GitHub `Permission denied (publickey)`

```bash
ls -la /root/.ssh
cat /root/.ssh/id_ed25519.pub
ssh -T git@github.com
```

Public key GitHub account-এ add করুন।

### 21.2 Nginx `502 Bad Gateway`

```bash
systemctl status php8.3-fpm --no-pager
ls -l /run/php/php8.3-fpm.sock
nginx -t
```

### 21.3 Laravel `500`

```bash
cd /var/www/mikropanel
php artisan optimize:clear
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
tail -n 100 storage/logs/laravel.log
```

### 21.4 Database `Access denied`

```bash
cd /var/www/mikropanel
php artisan tinker --execute='dump([
 "db" => config("database.connections.mysql.database"),
 "host" => config("database.connections.mysql.host"),
 "user" => config("database.connections.mysql.username"),
]);'
```

Password print/share করবেন না।

### 21.5 Panel চলে কিন্তু MikroTik connect হয় না

```bash
wg show 2>/dev/null || true
ip route
ping -c 3 MIKROTIK_TUNNEL_IP
```

তারপর Router Ping/Sync test করুন।

## 22. Recommended backup policy

- প্রতিদিন MySQL backup।
- বড় code change-এর পর Git commit + push।
- সপ্তাহে অন্তত ১ বার Full DR backup PC/cloud-এ copy।
- WireGuard পরিবর্তন হলে `/etc/wireguard` secure backup।
- GitHub repository Private।
- `.env`, database, private key GitHub-এ নয়।

## 23. Recovery quick map

```text
NEW VPS
  |
  +-- Install Nginx + MySQL + PHP 8.3 + Composer + Node 22
  +-- Create GitHub SSH key
  +-- git clone git@github.com:nurulqatar/mikropanel.git
  +-- composer install + npm build
  +-- Restore old .env (KEEP OLD APP_KEY)
  +-- Create MySQL DB/user
  +-- Import latest database.sql.gz
  +-- php artisan migrate --force
  +-- Permissions + cache
  +-- Nginx port 80
  +-- Cron / Laravel Scheduler
  +-- WireGuard restore/update if required
  +-- Router Ping/Sync
  +-- Final health check
  `-- PANEL LIVE
```

## 24. GitHub-এ guide কোথায় রাখবেন

Recommended structure:

```text
mikropanel/
└── docs/
    ├── MIKROPANEL_NEW_VPS_RESTORE_GUIDE.md
    └── MikroPanel_New_VPS_Restore_Guide.pdf
```

Files server-এর `/var/www/mikropanel/docs/`-এ রাখার পরে:

```bash
cd /var/www/mikropanel || exit 1

git add docs/
git commit -m "Add MikroPanel VPS restore documentation"
git push
```

এই documentation-এ কোনো password, `.env`, APP_KEY, private key বা database dump রাখা হবে না।
