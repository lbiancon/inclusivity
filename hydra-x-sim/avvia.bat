@echo off
cd /d "%~dp0"
set "PHP=php"
where php >nul 2>&1
if errorlevel 1 (
  if exist "C:\xampp\php\php.exe" (
    set "PHP=C:\xampp\php\php.exe"
  ) else (
    echo Non trovo php.exe. Avvia Apache da XAMPP e apri http://localhost/hydra-x-sim/controllo.php
    pause
    exit /b 1
  )
)
echo Apri http://localhost:8765/controllo.php
"%PHP%" -S localhost:8765 serve.php
