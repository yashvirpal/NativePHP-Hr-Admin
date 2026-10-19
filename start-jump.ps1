<#
.SYNOPSIS
  Starts the backend and NativePHP Jump using this PC's CURRENT network address.

.DESCRIPTION
  The QR code must contain the address your phone can reach (your PC's Wi-Fi / hotspot address).
  That address changes whenever you switch networks, so this script detects it every time instead of
  relying on a hard-coded one.

  Usage:   .\start-jump.ps1
  Override (if detection picks the wrong adapter):   .\start-jump.ps1 -Ip 192.168.1.20
#>
param([string]$Ip)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not $Ip) {
    # The adapter that owns the default route is the one on the real network (Wi-Fi / hotspot / Ethernet).
    # Virtual adapters (Docker, WSL, Hyper-V, VirtualBox) never carry the default route.
    $cfg = Get-NetIPConfiguration |
        Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' -and $_.InterfaceAlias -notmatch 'vEthernet|VirtualBox|Docker|WSL|Loopback' } |
        Select-Object -First 1

    if (-not $cfg) { throw 'Could not find an active network connection. Connect to Wi-Fi (or the phone hotspot) and run again, or pass -Ip.' }

    $Ip = ($cfg.IPv4Address | Select-Object -First 1).IPAddress
    $Alias = $cfg.InterfaceAlias
    $Category = $cfg.NetProfile.NetworkCategory
    Write-Host "Network : $($cfg.InterfaceAlias) ($($cfg.NetProfile.Name))" -ForegroundColor DarkGray
}

Write-Host "PC address: $Ip" -ForegroundColor Cyan
$env:JUMP_IP = $Ip

docker compose up -d | Out-Null
docker compose --profile jump up -d --force-recreate jump | Out-Null

Write-Host 'Waiting for Jump to start...' -ForegroundColor DarkGray
$qr = $null
foreach ($i in 1..30) {
    Start-Sleep -Seconds 1
    $log = docker compose --profile jump logs jump --tail 60 2>&1 | ForEach-Object { $_ -replace '\x1b\[[0-9;]*m', '' }
    $qr = $log | Select-String -Pattern 'jump://connect\?host=[^&\s]+&port=\d+' | Select-Object -Last 1
    if ($qr) { break }
}

if (-not $qr) { throw 'Jump did not start. Check:  docker compose --profile jump logs jump' }

Write-Host ''
Write-Host "Jump is ready and advertising: $($qr.Matches.Value)" -ForegroundColor Green
Write-Host ''
Write-Host '1. On the PC, open     http://localhost:3000/jump/qr'
Write-Host "2. On the phone, test  http://${Ip}:3000/jump/qr   in its normal browser (must show the QR page)"
Write-Host '3. Scan the QR code from the Jump app'
Write-Host ''
if ($Category -eq 'Public') {
    Write-Host "WARNING: Windows treats this network ('$Alias') as PUBLIC, which blocks every inbound connection," -ForegroundColor Yellow
    Write-Host '         so the phone cannot reach this PC. If this is a network you trust (home Wi-Fi), run this in an' -ForegroundColor Yellow
    Write-Host '         Administrator PowerShell, then try step 2 again:' -ForegroundColor Yellow
    Write-Host "  Set-NetConnectionProfile -InterfaceAlias '$Alias' -NetworkCategory Private" -ForegroundColor White
} else {
    Write-Host 'If step 2 does not load, allow the ports in Windows Firewall (Administrator PowerShell):' -ForegroundColor Yellow
}
Write-Host '  New-NetFirewallRule -DisplayName "HR dev (Jump + API)" -Direction Inbound -Protocol TCP -LocalPort 3000,3001,3003,8000 -Action Allow -Profile Private,Domain' -ForegroundColor White
