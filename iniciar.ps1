# KanbanDoo - Iniciar Servidor Local
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "          KANBANDOO - SERVIDOR LOCAL               " -ForegroundColor Yellow
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "[*] Verificando dependencias..." -ForegroundColor DarkGray

$userDir = [System.Environment]::GetFolderPath([System.Environment+SpecialFolder]::UserProfile)
$phpUserDir = Join-Path $userDir ".php\bin"
$phpBin = Join-Path $phpUserDir "php.exe"
$phpIni = Join-Path $phpUserDir "php.ini"

# Adiciona ao PATH da sessão
if (Test-Path $phpUserDir) {
    $env:PATH = "$phpUserDir;$env:PATH"
}

# Verifica se PHP existe no caminho padrão ou no PATH
$needInstall = $false

if (-not (Test-Path $phpBin)) {
    $cmdPhp = Get-Command php -ErrorAction SilentlyContinue
    if ($cmdPhp) {
        $phpBin = $cmdPhp.Source
        $phpIni = $null
    } else {
        $needInstall = $true
    }
}

if (-not $needInstall) {
    $testCmd = if ($phpIni -and (Test-Path $phpIni)) {
        & $phpBin -c $phpIni -m 2>$null
    } else {
        & $phpBin -m 2>$null
    }
    if ($testCmd -notmatch "pdo_sqlite") {
        Write-Host "[!] PHP detectado, porém a extensão pdo_sqlite não está ativa." -ForegroundColor Yellow
        $needInstall = $true
    }
}

# Se faltar dependências, executa a tarefa de instalação automática
if ($needInstall) {
    Write-Host "===================================================" -ForegroundColor Yellow
    Write-Host "[!] Dependências ausentes ou incompletas." -ForegroundColor Yellow
    Write-Host "[*] Tarefa: Instalando dependências automaticamente..." -ForegroundColor Cyan
    Write-Host "===================================================" -ForegroundColor Yellow

    $scriptPath = Join-Path $PSScriptRoot "scripts\instalar_dependencias.ps1"
    & $scriptPath
    if ($LASTEXITCODE -ne 0) {
        Write-Host "`n[ERRO] Falha ao instalar dependências. Pressione qualquer tecla para sair." -ForegroundColor Red
        [Console]::ReadKey($true) | Out-Null
        exit 1
    }
    $phpBin = Join-Path $phpUserDir "php.exe"
    $phpIni = Join-Path $phpUserDir "php.ini"
    $env:PATH = "$phpUserDir;$env:PATH"
}

# Garante diretórios de armazenamento
$storageDir = Join-Path $PSScriptRoot "storage"
$uploadsDir = Join-Path $storageDir "uploads"
if (-not (Test-Path $storageDir)) { New-Item -ItemType Directory -Path $storageDir -Force | Out-Null }
if (-not (Test-Path $uploadsDir)) { New-Item -ItemType Directory -Path $uploadsDir -Force | Out-Null }

Write-Host "[OK] Dependências verificadas com sucesso!" -ForegroundColor Green
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "Servidor ativo em: http://localhost:8000" -ForegroundColor Green
Write-Host "Abrindo navegador..." -ForegroundColor DarkGray

Start-Process "http://localhost:8000/"

if ($phpIni -and (Test-Path $phpIni)) {
    & $phpBin -c $phpIni -S localhost:8000 router.php
} else {
    & $phpBin -S localhost:8000 router.php
}
