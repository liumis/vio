#!/usr/bin/env bash
# Laravel Cloud → Environment → Deploy commands
# Runs ONLY schema migrate + operators/settings/authorities seed.
# Does NOT import fines / process logs / activity.
#
# Cloud UI paste (one line is enough):
#   php artisan vio:cloud-deploy
#
# Or this script:
#   bash scripts/cloud-deploy.sh
#
# Required env (Cloud Variables):
#   VIO_BOOTSTRAP_PASSWORD=...   # needed to CREATE missing users
# Optional:
#   VIO_BOOTSTRAP_RESET_PASSWORDS=true  # force-reset those users' passwords
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> VIO Cloud deploy: migrate + bootstrap (users/settings/authorities)"
php artisan vio:cloud-deploy
echo "==> Done"
