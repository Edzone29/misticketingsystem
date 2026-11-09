$ErrorActionPreference = 'Stop'

$projectPath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpPath = 'C:\xampp\php\php.exe'
$appUrl = 'http://127.0.0.1:8000'
$edgePath = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
$chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'

if (-not (Test-Path $phpPath)) {
    throw "XAMPP PHP was not found at $phpPath. Update the PHP path in this launcher."
}

$serverReady = $false
try {
    $response = Invoke-WebRequest -Uri "$appUrl/login" -UseBasicParsing -TimeoutSec 3
    $serverReady = $response.StatusCode -eq 200
} catch {
    $serverReady = $false
}

if (-not $serverReady) {
    $listener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
    if ($listener) {
        throw 'Port 8000 is occupied by another application. Close it or change the port in scripts\start-desktop.ps1.'
    }

    $previousEnvironment = @{
        APP_DEBUG = $env:APP_DEBUG
        APP_URL = $env:APP_URL
        ASSET_URL = $env:ASSET_URL
    }

    try {
        $env:APP_DEBUG = 'false'
        $env:APP_URL = $appUrl
        $env:ASSET_URL = ''
        Start-Process -FilePath $phpPath `
            -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') `
            -WorkingDirectory $projectPath `
            -WindowStyle Hidden | Out-Null
    } finally {
        $env:APP_DEBUG = $previousEnvironment.APP_DEBUG
        $env:APP_URL = $previousEnvironment.APP_URL
        $env:ASSET_URL = $previousEnvironment.ASSET_URL
    }

    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Seconds 1
        try {
            $response = Invoke-WebRequest -Uri "$appUrl/login" -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) {
                $serverReady = $true
                break
            }
        } catch {
            $serverReady = $false
        }
    }
}

if (-not $serverReady) {
    throw 'The local helpdesk did not start. Check XAMPP PHP and the application logs, then try again.'
}

if (Test-Path $edgePath) {
    Start-Process -FilePath $edgePath -ArgumentList @("--app=$appUrl")
} elseif (Test-Path $chromePath) {
    Start-Process -FilePath $chromePath -ArgumentList @("--app=$appUrl")
} else {
    Start-Process $appUrl
}
