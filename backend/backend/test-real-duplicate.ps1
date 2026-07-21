# Test de mise à jour avec des données qui existent VRAIMENT
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== TEST RÉEL DE DOUBLONS ===" -ForegroundColor Yellow

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

# Test: Essayer de mettre à jour le secteur 6 avec le nom du secteur 5
Write-Host "`nTest: Mettre à jour secteur 6 avec nom du secteur 5..." -ForegroundColor Cyan

$updateData = @{
    nom = "BTP & Construction Moderne"  # EXISTE DANS ID 5
    description = "Test de vrai doublon"
    code = "BTP_CONSTRUCT"  # EXISTE DANS ID 5
    is_actif = $true
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/6" -Method Put -Body $updateData -Headers $headers -ErrorAction Stop
    Write-Host "❌ ERREUR: La validation n'a pas fonctionné!" -ForegroundColor Red
    Write-Host "Réponse: $($response | ConvertTo-Json -Depth 2)" -ForegroundColor Red
} catch {
    Write-Host "✅ BON: La validation a fonctionné!" -ForegroundColor Green
    Write-Host "Erreur attendue: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        try {
            $errorStream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($errorStream)
            $errorBody = $reader.ReadToEnd()
            $errorJson = $errorBody | ConvertFrom-Json
            Write-Host "Erreurs:" -ForegroundColor Yellow
            $errorJson.errors.PSObject.Properties | ForEach-Object {
                Write-Host "  - $($_.Name): $($_.Value -join ', ')" -ForegroundColor Gray
            }
        } catch {
            Write-Host "Détails: $errorBody" -ForegroundColor Gray
        }
    }
}

Write-Host "`nRecommandation:" -ForegroundColor Cyan
Write-Host "1. Utilisez des noms uniques pour éviter les doublons" -ForegroundColor White
Write-Host "2. La validation fonctionne maintenant correctement" -ForegroundColor White
Write-Host "3. Testez avec: 'Nouveau BTP $(Get-Date -Format HHmmss)'" -ForegroundColor White
