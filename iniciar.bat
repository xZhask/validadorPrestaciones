@echo off
chcp 65001 >nul
title Validador CPMS

:: Verificar si el puerto 8080 ya está en uso
netstat -ano | find ":8080" >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo Puerto 8080 ya en uso. Abriendo el navegador...
    start http://localhost:8080
    goto :EOF
)

:: Verificar que php.exe existe
if not exist "%~dp0php\php.exe" (
    echo ERROR: No se encontro php\php.exe
    echo Asegurate de haber descomprimido PHP portable en la carpeta "php\"
    pause
    goto :EOF
)

echo ============================================
echo   Validador CPMS — Servidor local
echo   http://localhost:8080
echo ============================================
echo.
echo Cierra esta ventana para detener el servidor.
echo.

:: Abrir el navegador con 1 segundo de delay (esperar que arranque PHP)
start "" /b cmd /c "timeout /t 1 >nul && start http://localhost:8080"

:: Iniciar el servidor PHP (bloqueante hasta cerrar la ventana)
"%~dp0php\php.exe" -S localhost:8080 -t "%~dp0"
