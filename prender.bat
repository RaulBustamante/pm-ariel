@echo off
title pm-ariel
cd /d "%~dp0"

set "PHP=C:\laragon\bin\php\php-8.4.24-Win32-vs17-x64\php.exe"
set "LARAGON=C:\laragon\laragon.exe"

if not exist "%PHP%" (
  echo.
  echo  No encuentro PHP 8.4 en:
  echo    %PHP%
  echo  Corrige la linea "set PHP=" de este archivo.
  echo.
  pause
  exit /b 1
)

echo.
echo  Revisando la base de datos...

netstat -ano | findstr ":3307" | findstr "LISTENING" >nul
if not errorlevel 1 goto :db_lista

echo  Apagada. Prendiendo Laragon...
start "" "%LARAGON%" start

for /l %%i in (1,1,20) do (
  ping -n 2 127.0.0.1 >nul
  netstat -ano | findstr ":3307" | findstr "LISTENING" >nul
  if not errorlevel 1 goto :db_lista
)

echo.
echo  La base de datos no prendio sola.
echo  Abre Laragon a mano, dale "Start All", y vuelve a correr este archivo.
echo.
pause
exit /b 1

:db_lista
echo  Base de datos lista.
echo.
echo  Prendiendo la aplicacion. Esta ventana se queda abierta:
echo  cierrala cuando quieras apagar.
echo.

start "" http://127.0.0.1:8000
"%PHP%" artisan serve --port=8000

pause
