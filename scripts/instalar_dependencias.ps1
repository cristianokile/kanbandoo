<#
.SYNOPSIS
    Instalador e configurador automático do ambiente PHP para o KanbanDoo.
#>

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$userDir = [System.Environment]::GetFolderPath([System.Environment+SpecialFolder]::UserProfile)
$phpBaseDir = Join-Path $userDir ".php"
$binDir = Join-Path $phpBaseDir "bin"
$phpExe = Join-Path $binDir "php.exe"
$phpIni = Join-Path $binDir "php.ini"

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "     KANBANDOO - INSTALADOR DE DEPENDÊNCIAS        " -ForegroundColor Yellow
Write-Host "===================================================" -ForegroundColor Cyan

# 1. Garante a criação da pasta
if (-not (Test-Path $binDir)) {
    New-Item -ItemType Directory -Path $binDir -Force | Out-Null
}

# 2. Baixa e extrai o PHP se não existir
if (-not (Test-Path $phpExe)) {
    Write-Host "[1/3] Identificando versão mais recente do PHP 8.3..." -ForegroundColor White
    $downloadUrl = $null
    
    try {
        $html = (Invoke-WebRequest -Uri "https://windows.php.net/downloads/releases/" -UseBasicParsing -TimeoutSec 15).Content
        $matches = [regex]::Matches($html, 'php-8\.3\.[0-9]+-Win32-vs16-x64\.zip')
        if ($matches.Count -gt 0) {
            $latestZip = $matches[$matches.Count - 1].Value
            $downloadUrl = "https://windows.php.net/downloads/releases/$latestZip"
        }
    } catch {
        Write-Host "  (Não foi possível consultar lista dinâmica, usando versão estável padrão)" -ForegroundColor DarkGray
    }

    if (-not $downloadUrl) {
        $downloadUrl = "https://windows.php.net/downloads/releases/php-8.3.35-Win32-vs16-x64.zip"
    }

    $zipPath = Join-Path $phpBaseDir "php-installer.zip"
    Write-Host "[1/3] Baixando PHP de $downloadUrl ..." -ForegroundColor Cyan
    Invoke-WebRequest -Uri $downloadUrl -OutFile $zipPath -UseBasicParsing

    Write-Host "[2/3] Extraindo arquivos em $binDir ..." -ForegroundColor Cyan
    Expand-Archive -Path $zipPath -DestinationPath $binDir -Force
    Remove-Item -Path $zipPath -Force
    Write-Host "      PHP descompactado com sucesso." -ForegroundColor Green
} else {
    Write-Host "[1/3] Binário do PHP já presente em $phpExe." -ForegroundColor Green
    Write-Host "[2/3] Verificando configuração do php.ini..." -ForegroundColor White
}

# 3. Configura php.ini
$iniDev = Join-Path $binDir "php.ini-development"
$iniProd = Join-Path $binDir "php.ini-production"

$baseIni = if (Test-Path $iniDev) { $iniDev } elseif (Test-Path $iniProd) { $iniProd } else { $null }

if ($baseIni) {
    Write-Host "[3/3] Configurando extensões (pdo_sqlite, sqlite3, curl, mbstring, openssl)..." -ForegroundColor Cyan
    $content = Get-Content -Path $baseIni -Raw -Encoding utf8

    $escapedExtDir = [regex]::Escape($binDir + "\ext")
    $content = $content -replace ';?extension_dir\s*=\s*("ext"|ext|"[^"]+")', "extension_dir = `"$binDir\ext`""
    $content = $content -replace ';extension=curl', 'extension=curl'
    $content = $content -replace ';extension=fileinfo', 'extension=fileinfo'
    $content = $content -replace ';extension=mbstring', 'extension=mbstring'
    $content = $content -replace ';extension=openssl', 'extension=openssl'
    $content = $content -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite'
    $content = $content -replace ';extension=sqlite3', 'extension=sqlite3'
    $content = $content -replace ';extension=pdo_mysql', 'extension=pdo_mysql'
    $content = $content -replace ';extension=mysqli', 'extension=mysqli'
    $content = $content -replace ';date\.timezone\s*=', 'date.timezone = America/Sao_Paulo'
    $content = $content -replace 'upload_max_filesize\s*=\s*[0-9]+[MGmg]', 'upload_max_filesize = 25M'
    $content = $content -replace 'post_max_size\s*=\s*[0-9]+[MGmg]', 'post_max_size = 25M'

    Set-Content -Path $phpIni -Value $content -Encoding utf8
    Write-Host "      php.ini atualizado com sucesso." -ForegroundColor Green
}

# 4. Adiciona ao PATH permanente do Usuário
try {
    $currentPath = [System.Environment]::GetEnvironmentVariable("Path", [System.EnvironmentVariableTarget]::User)
    if ($currentPath -notlike "*$binDir*") {
        $newPath = "$currentPath;$binDir"
        [System.Environment]::SetEnvironmentVariable("Path", $newPath, [System.EnvironmentVariableTarget]::User)
        Write-Host "      Adicionado $binDir ao PATH do usuário." -ForegroundColor Green
    }
} catch {
    Write-Host "  (Aviso: Não foi possível atualizar o PATH persistente no registro, mas o script usará o caminho direto)" -ForegroundColor DarkGray
}

# 5. Validação final
$loadedModules = & $phpExe -c $phpIni -m 2>$null
if ($loadedModules -match "pdo_sqlite") {
    Write-Host "===================================================" -ForegroundColor Green
    Write-Host "  [OK] Dependências instaladas e validadas com sucesso!" -ForegroundColor Green
    Write-Host "===================================================" -ForegroundColor Green
    exit 0
} else {
    Write-Host "===================================================" -ForegroundColor Red
    Write-Host "  [ERRO] A extensão pdo_sqlite não pôde ser ativada." -ForegroundColor Red
    Write-Host "===================================================" -ForegroundColor Red
    exit 1
}
