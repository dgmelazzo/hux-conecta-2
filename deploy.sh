#!/bin/bash
set -e
REPO=/var/www/crm.acicdf.org.br
FRONTEND=/var/www/crm-frontend

echo "=== Deploy Conecta CRM ==="
echo "Timestamp: $(date)"

# 1. Git pull
cd $REPO
echo "Git pull..."
git pull origin main 2>&1

# 2. Copy frontend files
echo "Copiando frontend..."
cp $REPO/web/dashboard.html $FRONTEND/dashboard.html
cp $REPO/web/index.html $FRONTEND/index.html

# 3. Copy associe-se if exists
if [ -d "$REPO/web/associe-se" ]; then
  cp -r $REPO/web/associe-se/* $FRONTEND/associe-se/ 2>/dev/null || true
fi

# 4. Fix permissions
chown -R nginx:nginx $REPO
chown -R nginx:nginx $FRONTEND
chmod 640 $REPO/api/.env
chmod 644 $FRONTEND/*.html

# 5. Status
echo ""
echo "=== Deploy Concluido ==="
echo "Commit: $(git log --oneline -1)"
echo "Dashboard: $(stat -c "%s bytes, modified %y" $FRONTEND/dashboard.html)"
echo "Login: $(stat -c "%s bytes, modified %y" $FRONTEND/index.html)"
