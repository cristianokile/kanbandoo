@echo off
setlocal enabledelayedexpansion
title KanbanDoo - Servidor Local
cd /d "%~dp0"

echo ===================================================
echo           KANBANDOO - SERVIDOR LOCAL
echo ===================================================
echo [*] Verificando dependencias...

rem 1. Define caminhos do PHP local
set "PHP_USER_DIR=%USERPROFILE%\.php\bin"
set "PHP_BIN=%PHP_USER_DIR%\php.exe"
set "PHP_INI=%PHP_USER_DIR%\php.ini"

rem Adiciona ao PATH da sessao
if exist "%PHP_USER_DIR%" (
    set "PATH=%PHP_USER_DIR%;%PATH%"
)

rem 2. Verifica se o PHP existe e tem pdo_sqlite ativo
set "NEED_INSTALL=0"

if not exist "%PHP_BIN%" (
    where php >nul 2>&1
    if errorlevel 1 (
        set "NEED_INSTALL=1"
    ) else (
        for /f "delims=" %%I in ('where php') do (
            if not defined DETECTED_PHP set "DETECTED_PHP=%%I"
        )
        set "PHP_BIN=!DETECTED_PHP!"
    )
)

if "!NEED_INSTALL!"=="0" (
    "%PHP_BIN%" -m 2>nul | findstr /i "pdo_sqlite" >nul
    if errorlevel 1 (
        echo [!] Extensao pdo_sqlite ausente ou desativada no PHP atual.
        set "NEED_INSTALL=1"
    )
)

rem 3. Se faltar dependencias, executa a tarefa de instalacao automatica
if "!NEED_INSTALL!"=="1" (
    echo ===================================================
    echo [!] Dependencias ausentes ou incompletas.
    echo [*] Tarefa: Baixando e configurando PHP automaticamente...
    echo ===================================================
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\instalar_dependencias.ps1"
    if errorlevel 1 (
        echo.
        echo [ERRO] Falha ao instalar dependencias automaticamente.
        echo Verifique sua conexao com a internet e tente novamente.
        echo.
        pause
        exit /b 1
    )
    set "PHP_BIN=%PHP_USER_DIR%\php.exe"
    set "PHP_INI=%PHP_USER_DIR%\php.ini"
    set "PATH=%PHP_USER_DIR%;%PATH%"
)

rem 4. Garante pastas de armazenamento
if not exist "storage" mkdir "storage"
if not exist "storage\uploads" mkdir "storage\uploads"

echo [OK] Dependencias verificadas com sucesso!
echo ===================================================
echo Iniciando servidor em http://localhost:8000 ...
echo Pressione Ctrl+C para encerrar o servidor.
echo ===================================================

start http://localhost:8000/
if exist "%PHP_INI%" (
    "%PHP_BIN%" -c "%PHP_INI%" -S localhost:8000 router.php
) else (
    "%PHP_BIN%" -S localhost:8000 router.php
)
pause
