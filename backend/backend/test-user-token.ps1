# Test avec le token de l'utilisateur
$baseUrl = "http://127.0.0.1:8000/api"
$userToken = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== TEST AVEC TOKEN UTILISATEUR ===" -ForegroundColor Yellow
Write-Host "Token: $userToken" -ForegroundColor Gray

# Test avec le token de l'utilisateur
$headers = @{
    "Authorization" = "Bearer $userToken"
    "Content-Type" = "application/json"
}

Write-Host "`n1. Test API secteurs-activite..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "✅ Succès! Nombre de secteurs: $($response.meta.total)" -ForegroundColor Green
    Write-Host "Premier secteur: $($response.data[0].nom)" -ForegroundColor Gray
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        
        # Essayer de lire le corps de l'erreur
        try {
            $errorStream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($errorStream)
            $errorBody = $reader.ReadToEnd()
            Write-Host "Détails erreur: $errorBody" -ForegroundColor Red
        } catch {
            Write-Host "Impossible de lire les détails de l'erreur" -ForegroundColor Red
        }
    }
}

Write-Host "`n2. Test avec filtres..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite?is_actif=true&per_page=3" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "✅ Filtres fonctionnent! Nombre: $($response.meta.total)" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur filtres: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n3. Test endpoint liste (sans auth)..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite-liste" -Method Get -ErrorAction Stop
    Write-Host "✅ Endpoint sans auth fonctionne! Nombre: $($response.count())" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur endpoint liste: $($_.Exception.Message)" -ForegroundColor Red
}
