#!/usr/bin/env bash
#
# deploy.sh — deploy SweetTreats 1-klik (git pull + composer + artisan)
# ---------------------------------------------------------------------
# Jalanin dari root laravel (folder tempat file ini berada). Dipakai di
# dev (dev.sweettreats.id) maupun prod (app.sweettreats.id) setelah prod
# di-connect ke git.
#
#   Pemakaian:  bash deploy.sh            # deploy branch main (default)
#               bash deploy.sh <branch>   # deploy branch lain
#
# PENTING:
#  - `php artisan access:rebuild` WAJIB. Struktur akses sidebar disimpan di
#    FILE storage/app/group_access/*.txt (di-generate dari tabel access_path
#    + group_access). Folder storage/ TIDAK ikut git & TIDAK ikut copy DB.
#    Kalau ga di-rebuild, AuthGuard nolak semua -> 404 di SEMUA halaman.
#  - Script ini SENGAJA TIDAK menjalankan `php artisan migrate`. Perubahan
#    schema di proyek ini dilakukan MANUAL via SQL (lihat catatan tim), bukan
#    migrations. Jadi jalankan SQL-nya sendiri sebelum/sesudah deploy.
#  - `.env` bersifat per-environment. JANGAN ketimpa nilai env-specific:
#    APP_URL, SESSION_DOMAIN (salah -> CSRF 419 pas login!), DB_*, APP_ENV,
#    APP_DEBUG. Nilai lain (API keys) boleh sama antar environment.
#
set -euo pipefail

# Pindah ke folder script (= root laravel) supaya bisa dipanggil dari mana aja.
cd "$(dirname "$0")"

PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
BRANCH="${1:-main}"

echo "=================================================="
echo " Deploy SweetTreats"
echo "   dir    : $(pwd)"
echo "   branch : $BRANCH"
echo "=================================================="

echo
echo "==> [0/4] git config core.fileMode false (abaikan drift permission shared-hosting)"
# Shared hosting (Hostinger) suka nge-flip executable-bit file (100644 <-> 100755),
# bikin git nganggep file 'modified' -> git pull bisa GAGAL walau kontennya sama.
# core.fileMode false = git abaikan perubahan mode doang. Idempotent, aman.
git config core.fileMode false

echo
echo "==> [1/4] git pull origin $BRANCH"
git pull origin "$BRANCH"

echo
echo "==> [2/4] composer install (--no-dev --optimize-autoloader)"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

echo
echo "==> [3/4] php artisan access:rebuild  (WAJIB — regen group_access/*.txt)"
"$PHP" artisan access:rebuild

echo
echo "==> [4/4] clear cache (config / route / view)"
"$PHP" artisan config:clear
"$PHP" artisan route:clear
"$PHP" artisan view:clear

echo
echo "=================================================="
echo " ✅ Deploy selesai."
echo "    Reminder: kalau ada perubahan schema, jalankan SQL-nya manual"
echo "    (proyek ini ga pakai migrations)."
echo "=================================================="
