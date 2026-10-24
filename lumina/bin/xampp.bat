@echo off
setlocal
cd /d "%~dp0.."

set "PHP="
if defined LUMINA_PHP if exist "%LUMINA_PHP%" set "PHP=%LUMINA_PHP%"

if not defined PHP if exist "C:\php85\php.exe" (
  "C:\php85\php.exe" -r "exit(PHP_VERSION_ID>=80512?0:1);"
  if not errorlevel 1 set "PHP=C:\php85\php.exe"
)

if not defined PHP if defined XAMPP_HOME if exist "%XAMPP_HOME%\php\php.exe" (
  "%XAMPP_HOME%\php\php.exe" -r "exit(PHP_VERSION_ID>=80512?0:1);"
  if not errorlevel 1 set "PHP=%XAMPP_HOME%\php\php.exe"
)

if not defined PHP if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" -r "exit(PHP_VERSION_ID>=80512?0:1);"
  if not errorlevel 1 set "PHP=C:\xampp\php\php.exe"
)

if not defined PHP (
  echo Le app di htdocs restano su http://localhost con il PHP di XAMPP.
  echo Lumina vuole PHP 8.5.12 in una cartella a parte, per esempio C:\php85
  echo Scarica lo zip da https://windows.php.net/download/ e non copiarlo dentro C:\xampp.
  echo Poi rilancia questo file. In alternativa: set LUMINA_PHP=C:\php85\php.exe
  exit /b 1
)

echo http://localhost          le app gia' in htdocs
echo http://127.0.0.1:8080     Lumina
"%PHP%" "%~dp0xampp.php"
if errorlevel 1 exit /b 1
echo Lascia questa finestra aperta. Per fermare Lumina chiudila.
"%PHP%" -S 127.0.0.1:8080 -t "%~dp0..\public" "%~dp0..\public\index.php"
