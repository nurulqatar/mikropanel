#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="${1:-/var/www/mikropanel}"
INSTALLER_FILE="$PROJECT_ROOT/public/install.php"
BOOTSTRAP_JSON="$PROJECT_ROOT/storage/app/installer-bootstrap.json"
LOCK_FILE="$PROJECT_ROOT/storage/app/mikropanel-installed.lock"

if [[ $EUID -ne 0 ]]; then
    echo "ERROR: Run this bootstrap as root (sudo/root)."
    exit 1
fi

if [[ ! -f "$PROJECT_ROOT/artisan" || ! -f "$PROJECT_ROOT/composer.json" ]]; then
    echo "ERROR: MikroPanel project was not found at: $PROJECT_ROOT"
    echo "Upload/clone the project first, then run this script again."
    exit 1
fi

if [[ ! -f "$INSTALLER_FILE" ]]; then
    echo "ERROR: $INSTALLER_FILE is missing."
    echo "Add the MikroPanel web installer files to the project first."
    exit 1
fi

if [[ -f "$LOCK_FILE" ]]; then
    echo "ERROR: MikroPanel is already marked as installed."
    exit 1
fi

if [[ -s "$PROJECT_ROOT/.env" ]]; then
    echo "ERROR: A non-empty .env already exists."
    echo "This bootstrap intentionally refuses to overwrite an existing application configuration."
    echo "For a new GitHub deployment, .env should not exist yet."
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "[1/9] Installing Ubuntu packages..."
apt-get update
apt-get install -y \
    nginx \
    mysql-server \
    mysql-client \
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
    php8.3-soap \
    unzip \
    curl \
    git \
    composer \
    ca-certificates \
    openssl

systemctl enable --now mysql php8.3-fpm nginx

if ! command -v node >/dev/null 2>&1 || ! node -e 'process.exit(Number(process.versions.node.split(".")[0]) >= 22 ? 0 : 1)' >/dev/null 2>&1; then
    echo "[2/9] Installing Node.js 22..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
else
    echo "[2/9] Node.js 22+ already available."
fi

mkdir -p "$PROJECT_ROOT/storage/app/install-uploads" "$PROJECT_ROOT/bootstrap/cache"

if [[ ! -f "$PROJECT_ROOT/vendor/autoload.php" ]]; then
    echo "[3/9] Installing Composer dependencies..."
    cd "$PROJECT_ROOT"
    COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader \
        --no-interaction
else
    echo "[3/9] Composer dependencies already present."
fi

if [[ ! -f "$PROJECT_ROOT/public/build/manifest.json" ]]; then
    echo "[4/9] Building frontend assets..."
    cd "$PROJECT_ROOT"
    if [[ -f package-lock.json ]]; then
        npm ci
    else
        npm install
    fi
    npm run build
else
    echo "[4/9] Frontend build already present."
fi

DB_NAME="mikropanel"
DB_USER="mikropanel"
DB_PASS="$(openssl rand -hex 24)"
INSTALL_TOKEN="$(openssl rand -hex 32)"
PUBLIC_IP="$(curl -4 -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
if [[ -z "$PUBLIC_IP" ]]; then
    PUBLIC_IP="$(hostname -I | awk '{print $1}')"
fi
APP_URL="http://${PUBLIC_IP}"

mysql_escape() {
    printf "%s" "$1" | sed "s/'/''/g"
}

DB_USER_SQL="$(mysql_escape "$DB_USER")"
DB_PASS_SQL="$(mysql_escape "$DB_PASS")"

echo "[5/9] Creating dedicated MySQL database/user..."
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER_SQL'@'127.0.0.1' IDENTIFIED BY '$DB_PASS_SQL';
ALTER USER '$DB_USER_SQL'@'127.0.0.1' IDENTIFIED BY '$DB_PASS_SQL';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER_SQL'@'127.0.0.1';
CREATE USER IF NOT EXISTS '$DB_USER_SQL'@'localhost' IDENTIFIED BY '$DB_PASS_SQL';
ALTER USER '$DB_USER_SQL'@'localhost' IDENTIFIED BY '$DB_PASS_SQL';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER_SQL'@'localhost';
FLUSH PRIVILEGES;
SQL

if [[ -f "$PROJECT_ROOT/.env.example" ]]; then
    cp "$PROJECT_ROOT/.env.example" "$PROJECT_ROOT/.env"
else
    : > "$PROJECT_ROOT/.env"
fi

APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
export PROJECT_ROOT DB_NAME DB_USER DB_PASS APP_URL APP_KEY INSTALL_TOKEN BOOTSTRAP_JSON
python3 <<'PY'
import json
import os
from pathlib import Path

root = Path(os.environ['PROJECT_ROOT'])
env_path = root / '.env'
text = env_path.read_text() if env_path.exists() else ''

def quote(value: str) -> str:
    if value == '':
        return ''
    allowed = set('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.-:/')
    if all(ch in allowed for ch in value):
        return value
    return '"' + value.replace('\\', '\\\\').replace('"', '\\"') + '"'

def set_value(text: str, key: str, value: str) -> str:
    line = f'{key}={quote(value)}'
    lines = text.splitlines()
    found = False
    out = []
    for current in lines:
        if current.startswith(key + '=') and not found:
            out.append(line)
            found = True
        else:
            out.append(current)
    if not found:
        out.append(line)
    return '\n'.join(out).rstrip() + '\n'

values = {
    'APP_NAME': 'MikroPanel',
    'APP_ENV': 'production',
    'APP_KEY': os.environ['APP_KEY'],
    'APP_DEBUG': 'false',
    'APP_URL': os.environ['APP_URL'],
    'APP_TIMEZONE': 'Asia/Qatar',
    'DB_CONNECTION': 'mysql',
    'DB_HOST': '127.0.0.1',
    'DB_PORT': '3306',
    'DB_DATABASE': os.environ['DB_NAME'],
    'DB_USERNAME': os.environ['DB_USER'],
    'DB_PASSWORD': os.environ['DB_PASS'],
}
for key, value in values.items():
    text = set_value(text, key, value)
env_path.write_text(text)

bootstrap = {
    'token': os.environ['INSTALL_TOKEN'],
    'db_host': '127.0.0.1',
    'db_port': '3306',
    'db_database': os.environ['DB_NAME'],
    'db_username': os.environ['DB_USER'],
    'db_password': os.environ['DB_PASS'],
    'app_url': os.environ['APP_URL'],
}
Path(os.environ['BOOTSTRAP_JSON']).write_text(json.dumps(bootstrap, indent=2) + '\n')
PY

chown -R root:root "$PROJECT_ROOT"
chown -R www-data:www-data "$PROJECT_ROOT/storage" "$PROJECT_ROOT/bootstrap/cache"
chmod -R u+rwX,g+rwX "$PROJECT_ROOT/storage" "$PROJECT_ROOT/bootstrap/cache"
chown root:www-data "$PROJECT_ROOT/.env"
chmod 0660 "$PROJECT_ROOT/.env"
chown www-data:www-data "$BOOTSTRAP_JSON"
chmod 0600 "$BOOTSTRAP_JSON"

echo "[6/9] Configuring PHP upload limits and Nginx port 80..."
cat >/etc/php/8.3/fpm/conf.d/99-mikropanel-installer.ini <<'INI'
upload_max_filesize=512M
post_max_size=520M
max_execution_time=0
max_input_time=600
memory_limit=512M
INI

cat >/etc/nginx/sites-available/mikropanel <<NGINX
server {
    listen 80;
    listen [::]:80;

    server_name _;
    root $PROJECT_ROOT/public;
    index index.php index.html;

    client_max_body_size 520M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 900;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sfn /etc/nginx/sites-available/mikropanel /etc/nginx/sites-enabled/mikropanel
nginx -t
systemctl restart php8.3-fpm nginx

echo "[7/9] Installing Laravel scheduler and daily database backup..."
cat >/etc/cron.d/mikropanel-scheduler <<CRON
* * * * * www-data cd $PROJECT_ROOT && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 0644 /etc/cron.d/mikropanel-scheduler

mkdir -p /var/backups/mikropanel
chmod 0700 /var/backups/mikropanel
cat >/usr/local/bin/mikropanel-backup <<BACKUP
#!/usr/bin/env bash
set -Eeuo pipefail
BACKUP_DIR="/var/backups/mikropanel"
STAMP="\$(date +%Y%m%d_%H%M%S)"
FILE="\$BACKUP_DIR/mikropanel_\$STAMP.sql.gz"
mysqldump --single-transaction --quick --routines --triggers --events "$DB_NAME" | gzip > "\$FILE"
test -s "\$FILE"
find "\$BACKUP_DIR" -type f -name 'mikropanel_*.sql.gz' -mtime +14 -delete
BACKUP
chmod 0700 /usr/local/bin/mikropanel-backup
cat >/etc/cron.d/mikropanel-backup <<'CRON'
15 2 * * * root /usr/local/bin/mikropanel-backup >/dev/null 2>&1
CRON
chmod 0644 /etc/cron.d/mikropanel-backup

echo "[8/9] Installing one-time post-install security finalizer..."
cat >/usr/local/sbin/mikropanel-finalize-install <<FINALIZE
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ -f "$LOCK_FILE" ]]; then
    chown root:www-data "$PROJECT_ROOT/.env" 2>/dev/null || true
    chmod 0640 "$PROJECT_ROOT/.env" 2>/dev/null || true
    rm -f "$BOOTSTRAP_JSON"
    chown -R www-data:www-data "$PROJECT_ROOT/storage" "$PROJECT_ROOT/bootstrap/cache"
    chmod -R u+rwX,g+rwX "$PROJECT_ROOT/storage" "$PROJECT_ROOT/bootstrap/cache"
    rm -f /etc/cron.d/mikropanel-installer-finalize
fi
FINALIZE
chmod 0700 /usr/local/sbin/mikropanel-finalize-install
cat >/etc/cron.d/mikropanel-installer-finalize <<'CRON'
* * * * * root /usr/local/sbin/mikropanel-finalize-install >/dev/null 2>&1
CRON
chmod 0644 /etc/cron.d/mikropanel-installer-finalize

systemctl reload nginx

echo "[9/9] Bootstrap complete."
echo
echo "============================================================"
echo "MikroPanel browser installer is ready"
echo "Open this one-time URL:"
echo
echo "  ${APP_URL}/install.php?token=${INSTALL_TOKEN}"
echo
echo "Do not share the URL/token. It becomes unusable after install."
echo "============================================================"
