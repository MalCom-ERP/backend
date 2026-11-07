#!/usr/bin/env bash
# ==============================================================================
# 🚀 SCRIPT D'INSTALLATION & CONFIGURATION PRODUCTION — MALCOM ERP (OVH VPS)
# OS Cible : Ubuntu 22.04 LTS
# Serveur IP : 167.114.66.95
# Domaines :
#   - malcom-lgc.com / www.malcom-lgc.com  (Site Vitrine)
#   - app.malcom-lgc.com                   (Application Angular Cloud)
#   - admin.malcom-lgc.com                 (Backend API Laravel + SuperAdmin)
# ==============================================================================

set -e

# Couleurs pour l'affichage
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}=====================================================================${NC}"
echo -e "${GREEN}🚀 Démarrage de l'installation du serveur de production MalCom ERP...${NC}"
echo -e "${BLUE}=====================================================================${NC}"

# Variables configurables
DB_NAME="malcom_prod"
DB_USER="malcom_user"
DB_PASS=$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c 20)
CURRENT_USER=$(whoami)

# 1. Mise à jour du système & Outils de base
echo -e "\n${YELLOW}[1/8] Mise à jour du système et installation des paquets requis...${NC}"
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip zip rsync ufw fail2ban acl certbot python3-certbot-nginx nginx mysql-server redis-server

# 2. Installation de PHP 8.2 & Extensions Laravel
echo -e "\n${YELLOW}[2/8] Installation de PHP 8.2 et des extensions Laravel...${NC}"
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-common php8.2-mysql php8.2-sqlite3 \
  php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath php8.2-intl php8.2-redis

# Optimisation PHP-FPM pour 8 Go de RAM
sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 64M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/post_max_size = .*/post_max_size = 64M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
sudo systemctl restart php8.2-fpm

# 3. Installation de Composer
echo -e "\n${YELLOW}[3/8] Installation de Composer...${NC}"
if ! command -v composer &> /dev/null; then
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  sudo chmod +x /usr/local/bin/composer
fi

# 4. Configuration de MySQL
echo -e "\n${YELLOW}[4/8] Création de la base de données MySQL (${DB_NAME})...${NC}"
sudo systemctl enable --now mysql
sudo mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# 5. Création de l'arborescence Web & Permissions
echo -e "\n${YELLOW}[5/8] Création des répertoires /var/www/malcom/{vitrine,frontend,backend}...${NC}"
sudo mkdir -p /var/www/malcom/vitrine
sudo mkdir -p /var/www/malcom/frontend
sudo mkdir -p /var/www/malcom/backend

sudo chown -R ${CURRENT_USER}:www-data /var/www/malcom
sudo chmod -R 775 /var/www/malcom

# Autoriser l'utilisateur courant à recharger php8.2-fpm et ajuster les permissions sans mot de passe lors du CI/CD
echo "${CURRENT_USER} ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.2-fpm, /bin/chown, /bin/chmod" | sudo tee /etc/sudoers.d/malcom-deploy > /dev/null
sudo chmod 0440 /etc/sudoers.d/malcom-deploy

# S'assurer que la clé SSH de déploiement GitHub Actions est autorisée
mkdir -p ~/.ssh
chmod 700 ~/.ssh
touch ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
if ! grep -q "AAAAC3NzaC1lZDI1NTE5AAAAIMQ9c1gPT2L8pBEAoL8eMW3s5DDEWjCQYJsdReEVQaWB" ~/.ssh/authorized_keys; then
  echo "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMQ9c1gPT2L8pBEAoL8eMW3s5DDEWjCQYJsdReEVQaWB github-actions-malcom-vps" >> ~/.ssh/authorized_keys
fi

# 6. Configuration des VirtualHosts Nginx
echo -e "\n${YELLOW}[6/8] Configuration des VirtualHosts Nginx...${NC}"

# 6a. Site Vitrine : malcom-lgc.com & www.malcom-lgc.com
sudo tee /etc/nginx/sites-available/vitrine.malcom-lgc.com > /dev/null << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name malcom-lgc.com www.malcom-lgc.com;

    root /var/www/malcom/vitrine;
    index index.html;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|webp|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
EOF

# 6b. Application Angular : app.malcom-lgc.com
sudo tee /etc/nginx/sites-available/app.malcom-lgc.com > /dev/null << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name app.malcom-lgc.com;

    root /var/www/malcom/frontend;
    index index.html;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|webp|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

# 6c. Backend API Laravel + SuperAdmin : admin.malcom-lgc.com
sudo tee /etc/nginx/sites-available/admin.malcom-lgc.com > /dev/null << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name admin.malcom-lgc.com;

    root /var/www/malcom/backend/public;
    index index.php index.html;

    client_max_body_size 64M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Activer les sites et désactiver le site par défaut
sudo ln -sf /etc/nginx/sites-available/vitrine.malcom-lgc.com /etc/nginx/sites-enabled/
sudo ln -sf /etc/nginx/sites-available/app.malcom-lgc.com /etc/nginx/sites-enabled/
sudo ln -sf /etc/nginx/sites-available/admin.malcom-lgc.com /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

sudo nginx -t
sudo systemctl restart nginx

# 7. Pare-feu (UFW)
echo -e "\n${YELLOW}[7/8] Configuration du pare-feu UFW (SSH, HTTP, HTTPS)...${NC}"
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable

# 8. Sauvegarde automatique quotidienne MySQL
echo -e "\n${YELLOW}[8/8] Mise en place de la sauvegarde automatique quotidienne MySQL...${NC}"
sudo mkdir -p /var/backups/malcom-db
sudo tee /usr/local/bin/backup-malcom-db.sh > /dev/null << EOF
#!/usr/bin/env bash
TIMESTAMP=\$(date +"%Y-%m-%d_%H-%M")
mysqldump -u ${DB_USER} -p'${DB_PASS}' ${DB_NAME} | gzip > /var/backups/malcom-db/${DB_NAME}_\${TIMESTAMP}.sql.gz
find /var/backups/malcom-db/ -type f -name "*.sql.gz" -mtime +14 -delete
EOF
sudo chmod +x /usr/local/bin/backup-malcom-db.sh
(crontab -l 2>/dev/null | grep -v "backup-malcom-db.sh"; echo "0 2 * * * /usr/local/bin/backup-malcom-db.sh") | crontab -

# Création d'un modèle .env prêt à l'emploi pour le backend
cat << EOF > /var/www/malcom/.env.production.template
APP_NAME=MalCom
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://admin.malcom-lgc.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=app.malcom-lgc.com,admin.malcom-lgc.com
SESSION_DOMAIN=.malcom-lgc.com
EOF

echo -e "\n${BLUE}=====================================================================${NC}"
echo -e "${GREEN}✅ INSTALLATION DU SERVEUR TERMINÉE AVEC SUCCÈS !${NC}"
echo -e "${BLUE}=====================================================================${NC}"
echo -e "📌 Identifiants MySQL générés (également copiés dans /var/www/malcom/.env.production.template) :"
echo -e "   - Base de données : ${GREEN}${DB_NAME}${NC}"
echo -e "   - Utilisateur     : ${GREEN}${DB_USER}${NC}"
echo -e "   - Mot de passe    : ${GREEN}${DB_PASS}${NC}"
echo -e "${BLUE}=====================================================================${NC}"

