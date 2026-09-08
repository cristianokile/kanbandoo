# KanbanDoo - Iniciar Servidor Local
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "          KANBANDOO - SERVIDOR LOCAL               " -ForegroundColor Yellow
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "Servidor ativo em: http://localhost:8000" -ForegroundColor Green
Write-Host "Abrindo navegador..." -ForegroundColor DarkGray

Start-Process "http://localhost:8000/"
& "C:\Users\crist\.php\bin\php.exe" -c "C:\Users\crist\.php\bin\php.ini" -S localhost:8000 router.php
