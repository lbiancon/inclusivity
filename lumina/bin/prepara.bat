@echo off
cd /d "%~dp0.."
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"
echo Creo solo i database lumina e lumina_test e l'utente lumina.
echo Gli altri database restano.
echo.
"%PHP%" "%~dp0xampp.php"
echo.
echo Se sopra non compare un errore, apri http://localhost/lumina/b/atelier-luce
pause
