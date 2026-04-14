#!/bin/bash
# deploy.sh  Conecta 2.0 (conecta.acicdf.org.br)
# Uso: bash deploy.sh
set -e
REPO="/var/www/conecta"
BRANCH="main"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando deploy Conecta 2.0..."
cd "$REPO"
git fetch origin
git reset --hard origin/$BRANCH
nginx -t && systemctl reload nginx
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deploy Conecta 2.0 concluido. Branch: $BRANCH"
