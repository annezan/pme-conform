# Test de suppression de secteur
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== TEST DELETE SECTEUR ===" -ForegroundColor Yellow

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

# D'abord, vérifier les secteurs existants
Write-Host "`n1. Secteurs existants avant suppression..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "Liste des secteurs:" -ForegroundColor Gray
    $response.data | ForEach-Object {
        Write-Host "  - ID $($_.id): $($_.nom) (actif: $($_.is_actif))" -ForegroundColor White
    }
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

# Test de suppression d'un secteur qui n'est probablement pas utilisé
Write-Host "`n2. Test suppression du secteur 18 (Tourisme)..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/18" -Method Delete -Headers $headers -ErrorAction Stop
    Write-Host "✅ Suppression réussie!" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 3
} catch {
    Write-Host "❌ Erreur suppression: $($_.Exception.Message)" -ForegroundColor Red
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

# Vérifier que le secteur est bien soft-deleted
Write-Host "`n3. Vérification après suppression..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/18" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "❌ Erreur: Le secteur 18 est encore accessible!" -ForegroundColor Yellow
    $response.data | ConvertTo-Json -Depth 2
} catch {
    Write-Host "✅ Bon: Le secteur 18 n'est plus accessible (404 attendu)" -ForegroundColor Green
}

# Vérifier la liste mise à jour
Write-Host "`n4. Liste des secteurs après suppression..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "Secteurs restants: $($response.data.count)" -ForegroundColor Gray
    $response.data | Where-Object { $_.id -eq 18 } | ForEach-Object {
        Write-Host "❌ Le secteur 18 est encore dans la liste!" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}
