@echo off
setlocal enabledelayedexpansion

:: 1. Chemins et configuration
set SCRIPT_DIR=%~dp0
set ROOT_DIR=%~dp0..
set BACKUP_DIR=%ROOT_DIR%\backups
set DB_NAME=factlagrange
set CONFIG_FILE=%SCRIPT_DIR%db_config.ini

:: 2. Extraction des identifiants du db_config.ini
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

echo --- RESTAURATION BASE DE DONNEES [%DB_NAME%] ---

:: 3. Lister les backups
set count=0
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
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

:: 4. Choix du fichier
set /p choice="Choisissez le numero (Par defaut [%count%]) : "
if "%choice%"=="" set choice=%count%
set "SELECTED_FILE=!file%choice%!"

:: 5. Exécution de la restauration
echo Restauration de %SELECTED_FILE% en cours...
mysql -u %DB_USER% -p%DB_PASS% %DB_NAME% < "%BACKUP_DIR%\%SELECTED_FILE%"

if %ERRORLEVEL% equ 0 (
    echo [SUCCES] Base de données restauree.
) else (
    echo [ERREUR] Echec de la restauration.
)
pause