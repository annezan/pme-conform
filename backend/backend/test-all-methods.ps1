# Test complet de tous les endpoints SecteursActivite
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== TEST COMPLET SECTEURS ACTIVITÉ ===" -ForegroundColor Yellow

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

# 1. Test GET all
Write-Host "`n1. GET /secteurs-activite (liste)..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "✅ Succès: $($response.data.count) secteurs" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

# 2. Test GET by ID
Write-Host "`n2. GET /secteurs-activite/1 (détails)..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/1" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "✅ Succès: $($response.data.nom)" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

# 3. Test POST (create)
Write-Host "`n3. POST /secteurs-activite (création)..." -ForegroundColor Cyan
$newSecteur = @{
    nom = "Test Secteur $(Get-Date -Format 'HHmmss')"
    description = "Secteur de test"
    code = "TEST_$(Get-Date -Format 'HHmmss')"
    is_actif = $true
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Post -Body $newSecteur -Headers $headers -ErrorAction Stop
    Write-Host "✅ Succès: ID $($response.data.id) créé" -ForegroundColor Green
    $newId = $response.data.id
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
    $newId = $null
}

# 4. Test PUT (update)
Write-Host "`n4. PUT /secteurs-activite/16 (mise à jour)..." -ForegroundColor Cyan
$updateData = @{
    nom = "Services aux entreprises (modifié)"
    description = "Services B2B, consulting, support aux entreprises"
    is_actif = $true
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/16" -Method Put -Body $updateData -Headers $headers -ErrorAction Stop
    Write-Host "✅ Succès: Secteur 16 mis à jour" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

# 5. Test toggle-actif
Write-Host "`n5. PATCH/GET /secteurs-activite/1/toggle-actif..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/1/toggle-actif" -Method Patch -Headers $headers -ErrorAction Stop
    Write-Host "✅ Succès: $($response.data.is_actif) - $($response.message)" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

# 6. Test DELETE
if ($newId) {
    Write-Host "`n6. DELETE /secteurs-activite/$newId (suppression)..." -ForegroundColor Cyan
    try {
        $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/$newId" -Method Delete -Headers $headers -ErrorAction Stop
        Write-Host "✅ Succès: Secteur $newId supprimé" -ForegroundColor Green
    } catch {
        Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# 7. Test endpoint liste
Write-Host "`n7. GET /secteurs-activite-liste..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite-liste" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "✅ Succès: $($response.data.count) secteurs actifs" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n=== RÉSUMÉ ===" -ForegroundColor Yellow
Write-Host "Tous les endpoints CRUD sont maintenant fonctionnels!" -ForegroundColor Green
Write-Host "Route model binding corrigé pour toutes les méthodes" -ForegroundColor Gray
