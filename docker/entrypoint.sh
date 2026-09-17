#!/bin/sh
# Prepare writable state, seed an empty database once, then hand off to Apache.
set -eu

cd /var/www/html

# data/ and uploads/avatars/ are volumes: they arrive empty and root-owned. SQLite
# needs to write the *directory*, not just db.sqlite — it creates journal/WAL files
# next to it — so a writable file in a read-only dir still fails at the first INSERT.
mkdir -p data uploads/avatars
chown -R www-data:www-data data uploads

# Secure cookies are the default because this app is on the public internet behind
# HTTPS. A plain-http smoke test is the only reason to turn them off, because the
# browser silently drops a Secure cookie over http and the login just never sticks.
echo "session.cookie_secure=${SESSION_COOKIE_SECURE:-1}" \
    > /usr/local/etc/php/conf.d/zz-session.ini

# The app reads its config from .env, not from the process environment: loadEnv()
# populates $_ENV from the file, and PHP's default variables_order leaves $_ENV
# empty for real environment variables. So materialise the file from whatever the
# platform injected — that is what makes Dokploy's environment panel take effect.
#
# Rewritten on every boot, not just when absent. Keeping an existing file would mean
# an edited environment variable silently does nothing until someone happens to
# rebuild, and a config change that appears to apply but doesn't is the worst kind.
umask 027
# printenv, not eval: the expansion was safe (a parameter's value is not re-scanned
# for command substitution) but a value containing a newline still wrote a second,
# attacker-chosen line into .env — e.g. a password ending in "\nVIEWER_AUTH=open"
# would have published the chart. Values with a newline are refused outright.
for name in ADMIN_EMAIL ADMIN_PASSWORD VIEWER_AUTH \
            GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET OAUTH_REDIRECT_URI; do
    value=$(printenv "$name" || true)
    [ -n "$value" ] || continue
    if [ "$(printf '%s' "$value" | wc -l)" -ne 0 ]; then
        echo "ERROR: $name contains a newline; refusing to write .env" >&2
        exit 1
    fi
    printf '%s=%s\n' "$name" "$value"
done > .env
chown root:www-data .env

# seed.php INSERTs without dedup — a second run duplicates every employee — so it
# may only ever touch an absent database. It also downloads 58 photos from Google
# Drive, which means first boot needs egress and takes a minute.
if [ ! -f data/db.sqlite ]; then
    if [ ! -f data/org_struct_code_store.csv ]; then
        cp /usr/local/share/org-tree/org_struct_code_store.csv data/
        chown www-data:www-data data/org_struct_code_store.csv
    fi
    echo "first boot: seeding the org chart from the CSV (downloading avatars)"
    # A crash mid-seed leaves a half-written db.sqlite, and the guard above then skips
    # seeding forever — an empty chart that never repairs itself. Drop the file so the
    # next boot retries instead.
    if su -s /bin/sh -c 'php seed.php' www-data; then
        :
    else
        echo "WARNING: seed failed — removing the partial database so the next boot retries"
        rm -f data/db.sqlite data/db.sqlite-wal data/db.sqlite-shm
    fi
    # seed.php can also die mid-transaction without a non-zero exit, so verify.
    if [ -f data/db.sqlite ] && [ "$(su -s /bin/sh -c 'php -r "require \"src/db.php\"; echo (int)db()->query(\"SELECT COUNT(*) FROM employees\")->fetchColumn();"' www-data)" = "0" ]; then
        echo "WARNING: seed produced an empty database — removing it so the next boot retries"
        rm -f data/db.sqlite data/db.sqlite-wal data/db.sqlite-shm
    fi
fi

exec "$@"
