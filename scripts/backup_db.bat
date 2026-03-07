@echo off
setlocal enabledelayedexpansion

rem 1. Chemins relatifs
set ROOT_DIR=%~dp0..\
set BACKUP_DIR=%ROOT_DIR%backups
set LOG_DIR=%ROOT_DIR%migrations\logs
set CONFIG_FILE=%~dp0db_config.ini
set TEMP_VER=%~dp0temp_ver.txt

rem 2. Paramètre de la base
set DB_NAME=factlagrange

rem 3. Détection dynamique des exécutables
where mariadb >nul 2>nul
if %errorlevel% equ 0 (set MYSQL_EXE=mariadb) else (set MYSQL_EXE="C:\wamp64\bin\mariadb\mariadb11.5.2\bin\mariadb.exe")

where mariadb-dump >nul 2>nul
if %errorlevel% equ 0 (set DUMP_EXE=mariadb-dump) else (set DUMP_EXE="C:\wamp64\bin\mariadb\mariadb11.5.2\bin\mariadb-dump.exe")

where php >nul 2>nul
if %errorlevel% equ 0 (set PHP_EXE=php) else (set PHP_EXE="C:\wamp64\bin\php\php8.2.13\php.exe")

rem 4. RÉCUPÉRATION VERSION
%MYSQL_EXE% --defaults-extra-file="%CONFIG_FILE%" -s -N -e "SELECT REPLACE(migration_name, '.sql', '') FROM %DB_NAME%.sys_migrations ORDER BY id DESC LIMIT 1" > "%TEMP_VER%"
set /p DB_VER=<"%TEMP_VER%"
del "%TEMP_VER%"
if "!DB_VER!"=="" set DB_VER=no_version

rem 5. HORODATAGE
for /f "tokens=1-6 delims= " %%i in ('powershell -command "get-date -format 'yyyy MM dd HH mm ss'"') do (set TIMESTAMP=%%i%%j%%k_%%l%%m%%n)
set FILENAME=%DB_NAME%_v%DB_VER%_%TIMESTAMP%.sql
set LOG_FILE=%LOG_DIR%\migration_%TIMESTAMP%.log

rem 6. BACKUP
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
echo [1/3] Sauvegarde : %FILENAME%
%DUMP_EXE% --defaults-extra-file="%CONFIG_FILE%" %DB_NAME% --result-file="%BACKUP_DIR%\%FILENAME%"

rem Verification du backup avant de continuer
if %ERRORLEVEL% neq 0 goto :error_backup

rem 7. MIGRATION avec LOG
echo [2/3] Migration PHP...
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
echo Fichier log: %LOG_FILE%
echo.

rem Exécuter migrate.php et capturer la sortie
%PHP_EXE% "%~dp0migrate.php" > "%LOG_FILE%" 2>&1

rem Afficher le contenu du log
type "%LOG_FILE%"
echo.

rem 8. AUDIT et ajout au log
echo [3/3] Audit des contraintes de securite (FK) :
echo --------------------------------------------------------------------------------- 
echo. >> "%LOG_FILE%"
echo ================================================================================= >> "%LOG_FILE%"
echo AUDIT DES CONTRAINTES (FK) >> "%LOG_FILE%"
echo ================================================================================= >> "%LOG_FILE%"
echo. >> "%LOG_FILE%"

%MYSQL_EXE% --defaults-extra-file="%CONFIG_FILE%" %DB_NAME% -e "SELECT rc.TABLE_NAME AS 'Table', rc.REFERENCED_TABLE_NAME AS 'Cible', rc.DELETE_RULE AS 'Sur_Suppression' FROM information_schema.REFERENTIAL_CONSTRAINTS rc WHERE rc.CONSTRAINT_SCHEMA = DATABASE() ORDER BY rc.TABLE_NAME;" >> "%LOG_FILE%" 2>&1

rem Afficher uniquement l'audit (sans répéter tout le log)
%MYSQL_EXE% --defaults-extra-file="%CONFIG_FILE%" %DB_NAME% -e "SELECT rc.TABLE_NAME AS 'Table', rc.REFERENCED_TABLE_NAME AS 'Cible', rc.DELETE_RULE AS 'Sur_Suppression' FROM information_schema.REFERENTIAL_CONSTRAINTS rc WHERE rc.CONSTRAINT_SCHEMA = DATABASE() ORDER BY rc.TABLE_NAME;"

echo --------------------------------------------------------------------------------- 
echo.
echo Log complet enregistre dans: %LOG_FILE%
echo.

goto :end

:error_backup
echo ERREUR : Le backup a echoue. La migration et l'audit ont ete annules.
pause
exit /b

:end
pause