@echo off
title KanbanDoo - Servidor Local
cd /d "%~dp0"
echo ===================================================
echo           KANBANDOO - SERVIDOR LOCAL
echo ===================================================
echo Iniciando servidor em http://localhost:8000 ...
echo Pressione Ctrl+C para encerrar o servidor.
echo ===================================================

set "PATH=C:\Users\crist\.php\bin;%PATH%"
start http://localhost:8000/
"C:\Users\crist\.php\bin\php.exe" -c "C:\Users\crist\.php\bin\php.ini" -S localhost:8000 router.php
pause
