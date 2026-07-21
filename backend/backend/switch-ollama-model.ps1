# switch-ollama-model.ps1
#
# Bascule rapide entre llama3.2:3b (qualite, ~180s/ecart, timeouts probables)
# et llama3.2:1b (rapide, ~50-80s/ecart, qualite moindre mais visible).
#
# Usage : .\switch-ollama-model.ps1 1b
#         .\switch-ollama-model.ps1 3b
#
# Modifie OLLAMA_MODEL dans .env et vide le cache config Laravel pour que
# le changement soit pris en compte immediatement.

param(
    [Parameter(Mandatory=$true)]
    [ValidateSet('1b', '3b')]
    [string]$Variant
)

$envPath = Join-Path $PSScriptRoot ".env"
if (-not (Test-Path $envPath)) {
    Write-Host "ERREUR : .env introuvable a $envPath" -ForegroundColor Red
    exit 1
}

$nouveauModele = "llama3.2:$Variant"

Write-Host ""
Write-Host "Bascule OLLAMA_MODEL -> $nouveauModele" -ForegroundColor Cyan

# Verification que le modele est telecharge dans Ollama
$installes = (ollama list 2>$null) -join "`n"
if (-not ($installes -match "llama3.2:$Variant")) {
    Write-Host "ATTENTION : llama3.2:$Variant n'est pas dans Ollama. Telechargez-le d'abord :" -ForegroundColor Yellow
    Write-Host "  ollama pull llama3.2:$Variant" -ForegroundColor Yellow
    Write-Host ""
    $reponse = Read-Host "Continuer quand meme ? (o/N)"
    if ($reponse -ne 'o' -and $reponse -ne 'O') {
        Write-Host "Annule." -ForegroundColor Gray
        exit 0
    }
}

# Modification du .env
$contenu = Get-Content $envPath -Raw
$pattern = '(?m)^OLLAMA_MODEL=.*$'
if ($contenu -match $pattern) {
    $contenu = $contenu -replace $pattern, "OLLAMA_MODEL=$nouveauModele"
} else {
    $contenu += "`nOLLAMA_MODEL=$nouveauModele`n"
}
Set-Content -Path $envPath -Value $contenu -NoNewline -Encoding utf8

Write-Host "OK .env mis a jour" -ForegroundColor Green

# Vidage du cache config Laravel
Write-Host "Vidage du cache Laravel..." -ForegroundColor Cyan
& php artisan config:clear 2>&1 | Out-Null
Write-Host "OK cache vide" -ForegroundColor Green

# Redemarrage Apache si Laragon dispo (sinon, l'user devra le faire manuellement)
$laragonExe = "C:\laragon\laragon.exe"
if (Test-Path $laragonExe) {
    Write-Host ""
    Write-Host "IMPORTANT : Redemarrez Apache via Laragon pour que mod_php prenne le nouveau .env" -ForegroundColor Yellow
    Write-Host "  Laragon > Apache > Stop, puis Start (ou bouton Recharger)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Modele actif : $nouveauModele" -ForegroundColor Cyan

if ($Variant -eq '1b') {
    Write-Host "  -> rapide (~50-80s/ecart), qualite moyenne, visible LLM en pratique" -ForegroundColor Gray
} else {
    Write-Host "  -> qualite (~180s/ecart), timeouts probables -> templates fallback" -ForegroundColor Gray
}
