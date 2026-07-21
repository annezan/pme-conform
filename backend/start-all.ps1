# start-all.ps1
#
# Lance en parallele les 2 process necessaires au dev local :
#
#   Terminal 1 : php artisan serve              (serveur web HTTP)
#   Terminal 2 : queue:work --queue=analyses    (worker pour mode enrichi IA)
#
# Chaque process tourne dans une fenetre PowerShell separee pour que vous
# voyiez les logs en temps reel et puissiez Ctrl+C l'un ou l'autre.
#
# En prod (serveur OVH Ubuntu), c'est Supervisor qui gere les workers ;
# voir deploy/README.md et deploy/supervisor/pme-conform-worker.conf.

$ErrorActionPreference = 'Stop'
$projetPath = $PSScriptRoot
if (-not $projetPath) { $projetPath = (Get-Location).Path }

Write-Host ""
Write-Host "=== Demarrage PME-CONFORM (dev local) ===" -ForegroundColor Cyan
Write-Host "Projet : $projetPath"
Write-Host ""

# Verification : sommes-nous bien dans le bon dossier ?
if (-not (Test-Path "$projetPath\artisan")) {
    Write-Host "ERREUR : artisan introuvable dans $projetPath" -ForegroundColor Red
    Write-Host "Placez ce script dans le dossier backend\backend\" -ForegroundColor Red
    exit 1
}

# Verification : Ollama tourne-t-il ?
Write-Host "Verification Ollama (http://127.0.0.1:11434)..." -ForegroundColor Yellow
try {
    $r = Invoke-WebRequest -Uri "http://127.0.0.1:11434/api/tags" -TimeoutSec 3 -UseBasicParsing
    if ($r.StatusCode -eq 200) {
        Write-Host "  OK Ollama repond" -ForegroundColor Green
    }
} catch {
    Write-Host "  ATTENTION : Ollama ne repond pas. Le mode enrichi IA ne marchera pas." -ForegroundColor Yellow
    Write-Host "  Lancez Ollama avant de continuer (ou ignorez si vous ne ferez que du mode rapide)." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Lancement des 2 fenetres..." -ForegroundColor Cyan

# Fenetre 1 : serveur web
$cmd1 = "Set-Location '$projetPath'; Write-Host 'SERVEUR WEB (php artisan serve)' -ForegroundColor Cyan; Write-Host 'http://127.0.0.1:8000' -ForegroundColor Cyan; Write-Host ''; php artisan serve"
Start-Process powershell.exe -ArgumentList "-NoExit", "-Command", $cmd1

# Petit delai pour eviter la collision d'affichage
Start-Sleep -Milliseconds 500

# Fenetre 2 : worker
$cmd2 = "Set-Location '$projetPath'; Write-Host 'WORKER QUEUE (queue:work --queue=analyses)' -ForegroundColor Magenta; Write-Host 'Timeout 4h, 1 tentative, memoire 2 Go' -ForegroundColor Magenta; Write-Host ''; php artisan queue:work --queue=analyses --timeout=14400 --tries=1 --memory=2048"
Start-Process powershell.exe -ArgumentList "-NoExit", "-Command", $cmd2

Write-Host ""
Write-Host "OK Les 2 fenetres sont lancees." -ForegroundColor Green
Write-Host ""
Write-Host "Pour arreter :"
Write-Host "  - Ctrl+C dans chaque fenetre, OU"
Write-Host "  - Fermer chaque fenetre"
Write-Host ""
Write-Host "Cette fenetre maitre peut etre fermee, les 2 autres continueront." -ForegroundColor Gray
