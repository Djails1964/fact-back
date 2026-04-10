@echo off
setlocal enabledelayedexpansion

:: 1. Chemins et configuration
set SCRIPT_DIR=%~dp0
set ROOT_DIR=%~dp0..
set BACKUP_DIR=%ROOT_DIR%\backups
set DB_NAME=factlagrange
set CONFIG_FILE=%SCRIPT_DIR%db_config.ini

:: 2. Détection dynamique des exécutables (cohérent avec backup_db.bat)
where mariadb >nul 2>nul
if %errorlevel% equ 0 (set MYSQL_EXE=mariadb) else (set MYSQL_EXE="C:\wamp64\bin\mariadb\mariadb11.5.2\bin\mariadb.exe")

:: 3. Extraction des identifiants du db_config.ini
if exist "%CONFIG_FILE%" (
    for /f "tokens=1,2 delims==" %%A in ('findstr /i "user password" "%CONFIG_FILE%"') do (
        if "%%A"=="user" set DB_USER=%%B
        if "%%A"=="password" set DB_PASS=%%B
    )
    echo [INFO] Identifiants charges depuis db_config.ini
) else (
    set DB_USER=root
    set /p DB_PASS="Entrez le mot de passe pour root : "
)

echo.
echo === RESTAURATION BASE DE DONNEES [%DB_NAME%] ===
echo.

:: 4. Lister les backups disponibles (du plus ancien au plus récent)
set count=0
if not exist "%BACKUP_DIR%" (
    echo [ERREUR] Dossier backups introuvable : %BACKUP_DIR%
    pause
    exit /b
)

for /f "delims=" %%f in ('dir /b /o:d /a:-d "%BACKUP_DIR%\*.sql" 2^>nul') do (
    set /a count+=1
    set "file!count!=%%f"
    echo [!count!] %%f
)

if %count%==0 (
    echo [ERREUR] Aucun fichier de backup trouve dans %BACKUP_DIR%
    pause
    exit /b
)

echo.

:: 5. Choix du fichier (défaut = le plus récent = dernier listé)
set /p choice="Choisissez le numero (Par defaut [%count%] = le plus recent) : "
if "%choice%"=="" set choice=%count%
set "SELECTED_FILE=!file%choice%!"

if "%SELECTED_FILE%"=="" (
    echo [ERREUR] Choix invalide.
    pause
    exit /b
)

echo.
echo Fichier selectionne : %SELECTED_FILE%
echo.

:: 6. AVERTISSEMENT — cette opération est destructive
echo =========================================================================
echo  ATTENTION : Cette operation va SUPPRIMER puis RECREER la base [%DB_NAME%]
echo  Toutes les tables et donnees actuelles seront EFFACEES.
echo  La base sera restauree a l'etat du backup selectionne.
echo =========================================================================
echo.
set /p confirm="Confirmer la restauration ? (O pour continuer, toute autre touche pour annuler) : "
if /i not "%confirm%"=="O" (
    echo [ANNULE] Restauration annulee.
    pause
    exit /b
)

echo.
echo [1/3] Suppression et recreation de la base %DB_NAME%...
%MYSQL_EXE% --defaults-extra-file="%CONFIG_FILE%" -e "DROP DATABASE IF EXISTS `%DB_NAME%`; CREATE DATABASE `%DB_NAME%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if %ERRORLEVEL% neq 0 (
    echo [ERREUR] Impossible de dropper/recreer la base. Verifiez les droits utilisateur.
    pause
    exit /b
)
echo [OK] Base %DB_NAME% recreee vide.

echo.
echo [2/3] Restauration depuis %SELECTED_FILE%...
%MYSQL_EXE% --defaults-extra-file="%CONFIG_FILE%" %DB_NAME% < "%BACKUP_DIR%\%SELECTED_FILE%"

if %ERRORLEVEL% neq 0 (
    echo [ERREUR] Echec de la restauration. La base est peut-etre vide ou incomplete.
    echo          Verifiez le fichier : %BACKUP_DIR%\%SELECTED_FILE%
    pause
    exit /b
)

echo.
echo [3/3] Verification post-restauration...
%MYSQL_EXE% --defaults-extra-file="%CONFIG_FILE%" %DB_NAME% -e "SELECT TABLE_NAME AS 'Table', TABLE_ROWS AS 'Lignes_approx' FROM information_schema.TABLES WHERE TABLE_SCHEMA = '%DB_NAME%' ORDER BY TABLE_NAME;"

echo.
echo =========================================================================
echo  [SUCCES] Base restauree a l'etat : %SELECTED_FILE%
echo =========================================================================
echo.
pause