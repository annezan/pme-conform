# Test de mise à jour avec données uniques
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== TEST UPDATE SECTEUR ===" -ForegroundColor Yellow

# 1. D'abord, vérifier les secteurs existants
Write-Host "`n1. Vérification des secteurs existants..." -ForegroundColor Cyan

try {
    $headers = @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    }
    
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "Secteurs existants:" -ForegroundColor Gray
    $response.data | ForEach-Object {
        Write-Host "  - ID $($_.id): $($_.nom) ($($_.code))" -ForegroundColor White
    }
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

# 2. Test update avec données uniques
Write-Host "`n2. Test mise à jour avec données uniques..." -ForegroundColor Cyan

$updateData = @{
    nom = "BTP & Construction Moderne"
    description = "Bâtiment moderne, travaux publics, construction durable"
    code = "BTP_CONSTRUCT"
    is_actif = $true
} | ConvertTo-Json

try {
    $headers = @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    }
    
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/5" -Method Put -Body $updateData -Headers $headers -ErrorAction Stop
    Write-Host "✅ Mise à jour réussie!" -ForegroundColor Green
    $response.data | ConvertTo-Json -Depth 3
} catch {
    Write-Host "❌ Erreur mise à jour: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        try {
            $errorStream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($errorStream)
            $errorBody = $reader.ReadToEnd()
            Write-Host "Détails: $errorBody" -ForegroundColor Red
        } catch {
            Write-Host "Impossible de lire les détails" -ForegroundColor Red
        }
    }
}

# 3. Test update du même secteur avec son propre ID (devrait fonctionner)
Write-Host "`n3. Test mise à jour du secteur 5 avec ses propres données..." -ForegroundColor Cyan

# D'abord récupérer les données actuelles
try {
    $currentData = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/5" -Method Get -Headers $headers -ErrorAction Stop
    
    $updateSameData = @{
        nom = $currentData.data.nom
        description = "Description mise à jour pour $($currentData.data.nom)"
        code = $currentData.data.code
        is_actif = $true
    } | ConvertTo-Json
    
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/5" -Method Put -Body $updateSameData -Headers $headers -ErrorAction Stop
    Write-Host "✅ Mise à jour avec mêmes données réussie!" -ForegroundColor Green
    $response.data | ConvertTo-Json -Depth 3
    
} catch {
    Write-Host "❌ Erreur mise à jour mêmes données: $($_.Exception.Message)" -ForegroundColor Red
}
