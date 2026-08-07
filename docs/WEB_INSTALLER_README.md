# MikroPanel Web Installer

This installer gives MikroPanel a WordPress-style browser setup for the **application layer** and a one-command VPS bootstrap for a blank Ubuntu 24.04 server.

## Important technical boundary

A completely blank VPS cannot be configured by a browser alone: before a browser can execute PHP, the server must already have a web server and PHP, and root-only tasks such as installing Ubuntu packages, configuring Nginx port 80, MySQL system access and cron cannot safely be performed by `www-data`.

For a blank Ubuntu VPS the safe flow is therefore:

1. Upload/clone the MikroPanel project.
2. Run **one root bootstrap command**.
3. Do the remaining application setup in the browser.

On a hosting server where Nginx/Apache + PHP + MySQL are already prepared and the document root already points to `public/`, only the browser installer portion may be needed.

## Files

- `public/install.php` — browser installer.
- `deploy/bootstrap-vps.sh` — Ubuntu 24.04 bootstrap.
- `storage/app/installer-bootstrap.json` — temporary one-time credentials/token; never commit.
- `storage/app/mikropanel-installed.lock` — local installation lock; never commit.

## Add installer to the current project

From the extracted installer kit:

```bash
sudo bash add-to-project.sh /var/www/mikropanel
```

On an already running production server this creates a local installer lock immediately, so adding the installer code does **not** reopen setup on the live panel.

Then commit only the installer source/docs:

```bash
cd /var/www/mikropanel
git status
git add public/install.php deploy/bootstrap-vps.sh docs/WEB_INSTALLER_README.md .gitignore
git commit -m "Add MikroPanel web installer"
git push
```

The `.env`, installer token, installer lock and database files must remain outside GitHub.

## New blank VPS installation

Get the project onto the new VPS first, then:

```bash
sudo bash /var/www/mikropanel/deploy/bootstrap-vps.sh /var/www/mikropanel
```

The bootstrap performs these root-level tasks:

- Installs Nginx, MySQL, PHP 8.3 extensions, Composer and Node.js 22.
- Installs Composer dependencies and builds Vite assets when missing.
- Creates a dedicated `mikropanel` MySQL database/user with a random password.
- Generates a temporary production `.env` and APP_KEY.
- Configures Nginx on port 80.
- Raises installer upload limit to 512 MB.
- Installs the Laravel scheduler cron.
- Installs a daily MySQL backup cron.
- Generates a one-time installer token.
- Prints the browser URL.

Example output:

```text
http://NEW_VPS_IP/install.php?token=ONE_TIME_TOKEN
```

Do not share that URL/token.

## Browser flow

### Fresh installation

Choose **Fresh installation**, set:

- Application URL
- Timezone
- Administrator name
- Administrator email
- Administrator password

The installer then:

- Tests the temporary database credentials.
- Writes production `.env` values.
- Runs `php artisan migrate --force` — it does not run `migrate:fresh`.
- Creates the admin user with role `admin`.
- Creates the storage link.
- Clears/creates Laravel production caches.
- Creates the local installed lock.

### Restore an existing MikroPanel

Choose **Restore existing MikroPanel database** and upload:

1. `database.sql.gz` or `.sql`
2. The old `.env` backup **recommended**

The old `.env` is used only to recover the old `APP_KEY`; the new VPS database credentials remain the newly generated credentials.

Preserving the old APP_KEY is important if any application data is encrypted with Laravel encryption.

After importing the database the installer runs only pending migrations with `migrate --force`.

Admin fields are optional in restore mode. Fill them only when you want to create/reset an administrator account.

## Security after completion

After successful installation:

- `storage/app/mikropanel-installed.lock` blocks the installer.
- The temporary bootstrap credential file is deleted.
- A root finalizer changes `.env` to `root:www-data` mode `0640` within about one minute.
- The one-time installer URL stops working.

The installer source can remain in GitHub safely because the lock/token model prevents re-running it on an installed server. You can also remove `public/install.php` from a specific deployment after installation if desired.

## Backups

The VPS bootstrap installs:

```text
15 2 * * * /usr/local/bin/mikropanel-backup
```

Database backups are kept under:

```text
/var/backups/mikropanel/
```

Keep an additional copy outside the VPS.

## Not automated by the browser installer

These are intentionally separate because they are network/root infrastructure, not Laravel application setup:

- Restoring `/etc/wireguard` and changing MikroTik WireGuard peers.
- DNS changes.
- TLS/Let's Encrypt certificate issuance.
- Provider firewall/security-group rules.

After a restore, verify MikroTik Router Ping/Sync before serving real users.

## Never use during recovery

Do not use:

```bash
php artisan migrate:fresh
php artisan db:wipe
```

When restoring an old `.env`/APP_KEY, do not replace that key with `php artisan key:generate`.
