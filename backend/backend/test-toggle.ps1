# Test de l'endpoint toggle-actif
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== TEST TOGGLE ACTIF ===" -ForegroundColor Yellow

# Test avec PATCH
Write-Host "`n1. Test avec PATCH sur secteur ID 1..." -ForegroundColor Cyan

try {
    $headers = @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    }
    
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/1/toggle-actif" -Method Patch -Headers $headers -ErrorAction Stop
    Write-Host "✅ PATCH fonctionne!" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 3
    
} catch {
    Write-Host "❌ Erreur PATCH: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    }
}

# Vérifier l'état du secteur après toggle
Write-Host "`n2. Vérification état du secteur 1..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/1" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "✅ État actuel: is_actif = $($response.data.is_actif)" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur vérification: $($_.Exception.Message)" -ForegroundColor Red
}

# Test avec GET (alternative)
Write-Host "`n3. Test avec GET (alternative)..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/2/toggle-actif" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "✅ GET fonctionne aussi!" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 3
} catch {
    Write-Host "❌ Erreur GET: $($_.Exception.Message)" -ForegroundColor Red
}
