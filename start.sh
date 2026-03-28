#!/bin/bash

# Script de démarrage du serveur Laravel avec les bonnes configurations PHP
# Usage: ./start.sh

echo "🚀 Démarrage du serveur Laravel MSG-Link..."
echo ""

# Couleurs pour les messages
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Erreur: Le fichier artisan n'existe pas dans ce répertoire${NC}"
    echo "Assurez-vous d'être dans le répertoire racine de Laravel"
    exit 1
fi

# Vérifier les permissions du dossier storage
echo -e "${YELLOW}📁 Vérification des permissions...${NC}"
if [ -d "storage" ]; then
    chmod -R 775 storage bootstrap/cache
    echo -e "${GREEN}✅ Permissions configurées${NC}"
else
    echo -e "${RED}❌ Dossier storage introuvable${NC}"
    exit 1
fi

# Créer le lien symbolique storage si nécessaire
if [ ! -L "public/storage" ]; then
    echo -e "${YELLOW}🔗 Création du lien symbolique storage...${NC}"
    php artisan storage:link
    echo -e "${GREEN}✅ Lien symbolique créé${NC}"
else
    echo -e "${GREEN}✅ Lien symbolique existe déjà${NC}"
fi

# Nettoyer le cache
echo -e "${YELLOW}🧹 Nettoyage du cache...${NC}"
php artisan config:clear > /dev/null 2>&1
php artisan cache:clear > /dev/null 2>&1
php artisan route:clear > /dev/null 2>&1
echo -e "${GREEN}✅ Cache nettoyé${NC}"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Configuration PHP :${NC}"
echo -e "  • upload_max_filesize : ${YELLOW}50M${NC}"
echo -e "  • post_max_size       : ${YELLOW}50M${NC}"
echo -e "  • max_execution_time  : ${YELLOW}300s${NC}"
echo -e "  • memory_limit        : ${YELLOW}256M${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${GREEN}🌐 Démarrage du serveur sur http://10.255.74.28:8000${NC}"
echo -e "${YELLOW}📊 Vérifier les limites: http://10.255.74.28:8000/check-limits.php${NC}"
echo ""
echo -e "${YELLOW}Appuyez sur Ctrl+C pour arrêter le serveur${NC}"
echo ""

# Démarrer le serveur avec les bonnes configurations PHP
php -c php.ini artisan serve --host=10.255.74.28 --port=8000
