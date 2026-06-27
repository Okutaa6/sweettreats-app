#!/usr/bin/env bash
#
# deploy-remote.sh — deploy branch ke server dev/prod via SSH (1 command).
# ----------------------------------------------------------------------
# Pendamping deploy.sh (yang jalan DI server). Script ini dijalanin dari
# LOKAL: dia SSH ke server, pindah ke branch yang diminta, lalu panggil
# deploy.sh di sana.
#
#   bash deploy-remote.sh dev  <branch>           # deploy branch ke dev
#   bash deploy-remote.sh dev  <branch> --quick   # cuma clear cache (CSS/blade; skip composer+rebuild)
#   bash deploy-remote.sh prod main --yes         # deploy ke prod (WAJIB --yes)
#
# Key SSH ada di ~/.ssh/rblas_ga (private, TIDAK di-commit). Script ini cuma
# nyimpen koneksi + path + langkah switch-branch. composer install /
# access:rebuild / clear cache tetap dijalanin deploy.sh DI server.
#
# CATATAN PROD: deploy.sh TIDAK jalanin SQL/migration. Pastikan prasyarat SQL
# manual (mis. kontak-anchored patients) udah jalan di DB prod sebelum deploy.
#
set -euo pipefail

ENV="${1:-}"; BRANCH="${2:-}"
QUICK=0; YES=0
for a in "${@:3}"; do
  case "$a" in
    --quick) QUICK=1 ;;
    --yes)   YES=1 ;;
    *) echo "Flag ga dikenal: $a"; exit 1 ;;
  esac
done

if [[ -z "$ENV" || -z "$BRANCH" ]]; then
  echo "Pemakaian:"
  echo "  bash deploy-remote.sh dev  <branch> [--quick]"
  echo "  bash deploy-remote.sh prod main --yes"
  exit 1
fi

SSH_CMD="ssh -p 65002 -i $HOME/.ssh/rblas_ga -o BatchMode=yes u695655413@93.127.219.164"

case "$ENV" in
  dev)  ROOT="/home/u695655413/domains/dev.sweettreats.id/laravel" ;;
  prod) ROOT="/home/u695655413/domains/app.sweettreats.id/laravel"
        if [[ "$YES" -ne 1 ]]; then
          echo "⚠️  PROD high-stakes. deploy.sh TIDAK jalanin SQL/migration —"
          echo "    pastikan prasyarat SQL manual udah jalan di DB prod dulu."
          echo "    Kalau yakin: bash deploy-remote.sh prod $BRANCH --yes"
          exit 1
        fi ;;
  *) echo "env harus 'dev' atau 'prod' (dapet: '$ENV')"; exit 1 ;;
esac

if [[ "$QUICK" -eq 1 ]]; then
  DEPLOY_CMD="php artisan view:clear && php artisan config:clear && php artisan route:clear"
  MODE="quick — clear cache only"
else
  DEPLOY_CMD="bash deploy.sh $BRANCH"
  MODE="full — deploy.sh (composer + access:rebuild + clear)"
fi

echo "=================================================="
echo " Deploy remote → $ENV"
echo "   root   : $ROOT"
echo "   branch : $BRANCH"
echo "   mode   : $MODE"
echo "=================================================="

$SSH_CMD "bash -s" <<REMOTE
set -e
cd "$ROOT"
echo "==> switch ke branch $BRANCH"
GIT_SSH_COMMAND='ssh -o BatchMode=yes' git fetch origin -q
git checkout -B "$BRANCH" "origin/$BRANCH"
git reset --hard "origin/$BRANCH" -q
echo "==> $MODE"
$DEPLOY_CMD
echo "==> HEAD sekarang: \$(git rev-parse --short HEAD)"
REMOTE

echo "✅ Selesai — $ENV sekarang di branch '$BRANCH'."
