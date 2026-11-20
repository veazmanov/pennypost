#!/usr/bin/env bash
#
# Deploys the latest main. Run it ON the server, from anywhere:
#
#   ssh hetzner /var/www/laravel.vjbe.net/deploy.sh
#
# Checks everything it can before touching the site, then puts the site in
# maintenance mode while the code and database change, so nobody hits new
# code running against an old schema. If any step fails it puts the old code
# and the database back the way they were and brings the site up again.
#
# Build files are committed (`npm run build` can't run on the server), so
# remember to build and commit them before pushing.

set -Eeuo pipefail

cd "$(dirname "$0")"

BRANCH=main
DB=database/database.sqlite
BACKUP_DIR=database/backups
KEEP_BACKUPS=10

say() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nDeploy stopped: %s\n' "$*" >&2; exit 1; }

# --- Checks (nothing has changed yet) ---------------------------------------

say "Checking the working tree"
git diff --quiet && git diff --cached --quiet \
    || fail "there are uncommitted changes to tracked files here (see git status)."

say "Fetching origin/$BRANCH"
git fetch --quiet origin "$BRANCH"
PREV=$(git rev-parse HEAD)
NEXT=$(git rev-parse "origin/$BRANCH")

if [ "$PREV" = "$NEXT" ]; then
    echo "Already on $(git log --oneline -1). Nothing to deploy."
    exit 0
fi

git merge-base --is-ancestor "$PREV" "$NEXT" \
    || fail "origin/$BRANCH doesn't build on what's deployed (was it force-pushed?). Sort it out by hand."

echo "Deploying:"
git log --oneline "$PREV..$NEXT"

say "Checking every file that will change can be written"
# Where the last deploy fell over: a folder git couldn't write to. Git
# replaces files rather than editing them, so it's the folder that counts.
problems=()
while IFS= read -r path; do
    dir=$(dirname "$path")
    while [ ! -e "$dir" ]; do dir=$(dirname "$dir"); done
    if [ ! -w "$dir" ]; then
        problems+=("$dir/ isn't writable (needed for $path)")
    fi
done < <(git diff --name-only "$PREV" "$NEXT")

# Untracked leftovers that git would refuse to overwrite.
while IFS= read -r path; do
    if [ -e "$path" ] && ! git ls-files --error-unmatch "$path" >/dev/null 2>&1; then
        problems+=("$path is untracked here and would be overwritten")
    fi
done < <(git diff --name-only --diff-filter=A "$PREV" "$NEXT")

if [ ${#problems[@]} -gt 0 ]; then
    printf '  %s\n' "${problems[@]}" >&2
    fail "fix the paths above first (for a folder: sudo chmod g+w <folder>)."
fi

# --- Deploy -----------------------------------------------------------------

mkdir -p "$BACKUP_DIR"
BACKUP="$BACKUP_DIR/database-$(date +%Y%m%d-%H%M%S)-${PREV:0:7}.sqlite"

rollback() {
    say "Something failed, rolling back to ${PREV:0:7}"
    git reset --quiet --hard "$PREV"
    composer install --no-dev --optimize-autoloader --no-interaction --quiet || true
    if [ -f "$BACKUP" ]; then
        # The site was down since the backup, so nothing written since is lost.
        cp "$BACKUP" "$DB"
        rm -f "$DB-wal" "$DB-shm"
        echo "Database restored from $BACKUP"
    fi
    php artisan up || true
    fail "rolled back. The site is up on the previous version; see the output above."
}

say "Turning on maintenance mode"
php artisan down --retry=15
trap rollback ERR

say "Backing up the database to $BACKUP"
sqlite3 "$DB" ".backup '$BACKUP'"

say "Pulling ${NEXT:0:7}"
git merge --quiet --ff-only "$NEXT"

say "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

say "Migrating"
php artisan migrate --force

trap - ERR

say "Bringing the site back up"
php artisan up

# Keep the newest few backups.
ls -1t "$BACKUP_DIR"/database-*.sqlite | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm --

say "Deployed $(git log --oneline -1)"
