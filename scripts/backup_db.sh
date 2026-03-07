#!/bin/bash

# 1. Détection dynamique du dossier pour identifier l'environnement
SCRIPT_DIR=$(dirname "$(readlink -f "$0")")
ROOT_DIR="$SCRIPT_DIR/.."
BACKUP_DIR="$ROOT_DIR/backups"

# 2. Logique de nommage automatique de la base de données
if [[ "$SCRIPT_DIR" == *"pp-facturation"* ]]; then
    DB_NAME="pp_factlagrange"
    ENV_LABEL="PRE-PROD"
else
    DB_NAME="facturation_prod"
    ENV_LABEL="PRODUCTION"
fi

echo "--- DÉMARRAGE MAINTENANCE [$ENV_LABEL] ---"
echo "Base cible : $DB_NAME"

# 3. Récupération de la version (utilise ~/.my.cnf automatiquement)
DB_VER=$(mysql -s -N -e "SELECT REPLACE(migration_name, '.sql', '') FROM $DB_NAME.sys_migrations ORDER BY id DESC LIMIT 1;" 2>/dev/null)

if [ -z "$DB_VER" ]; then
    DB_VER="no_version"
fi

# 4. Horodatage et nom du fichier
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILENAME="${DB_NAME}_v${DB_VER}_${TIMESTAMP}.sql"

# 5. Backup [1/3]
mkdir -p "$BACKUP_DIR"
echo "[1/3] Sauvegarde : $FILENAME"
mysqldump "$DB_NAME" > "$BACKUP_DIR/$FILENAME"

# 6. Suite du processus si le backup est OK
if [ $? -eq 0 ]; then
    # 7. Migration PHP [2/3]
    # On passe le nom de la base en argument au script PHP
    echo ""
    echo "[2/3] Migration PHP..."
    php "$SCRIPT_DIR/migrate.php" "$DB_NAME"
    
    # 8. Audit des contraintes [3/3]
    echo ""
    echo "[3/3] Audit des contraintes (FK) sur $DB_NAME :"
    echo "---------------------------------------------------------------------------------"
    mysql "$DB_NAME" -e "SELECT rc.TABLE_NAME AS 'Table', rc.REFERENCED_TABLE_NAME AS 'Cible', rc.DELETE_RULE AS 'Sur_Suppression' FROM information_schema.REFERENTIAL_CONSTRAINTS rc WHERE rc.CONSTRAINT_SCHEMA = DATABASE() ORDER BY rc.TABLE_NAME;"
    echo "---------------------------------------------------------------------------------"
else
    echo "ERREUR : La sauvegarde de $DB_NAME a echoue."
    exit 1
fi