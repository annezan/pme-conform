# Debug de l'endpoint show
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== DEBUG ENDPOINT SHOW ===" -ForegroundColor Yellow

# Test avec différents IDs
$ids = @(1, 2, 5, 10)

foreach ($id in $ids) {
    Write-Host "`nTest ID: $id" -ForegroundColor Cyan
    
    try {
        $headers = @{
            "Authorization" = "Bearer $token"
            "Content-Type" = "application/json"
        }
        
        $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/$id" -Method Get -Headers $headers -ErrorAction Stop
        Write-Host "✅ Succès ID $id" -ForegroundColor Green
        
        # Afficher les propriétés de l'objet
        $data = $response.data
        Write-Host "Propriétés trouvées:" -ForegroundColor Gray
        
        if ($data.PSObject.Properties.Name -contains "id") {
            Write-Host "  - ID: $($data.id)" -ForegroundColor White
        }
        if ($data.PSObject.Properties.Name -contains "nom") {
            Write-Host "  - Nom: $($data.nom)" -ForegroundColor White
        }
        if ($data.PSObject.Properties.Name -contains "code") {
            Write-Host "  - Code: $($data.code)" -ForegroundColor White
        }
        if ($data.PSObject.Properties.Name -contains "is_actif") {
            Write-Host "  - Actif: $($data.is_actif)" -ForegroundColor White
        }
        
        # Vérifier les relations
        if ($data.PSObject.Properties.Name -contains "clients") {
            Write-Host "  - Clients: $($data.clients.count) élément(s)" -ForegroundColor Gray
        }
        if ($data.PSObject.Properties.Name -contains "referentiels") {
            Write-Host "  - Référentiels: $($data.referentiels.count) élément(s)" -ForegroundColor Gray
        }
        
    } catch {
        Write-Host "❌ Erreur ID $id: $($_.Exception.Message)" -ForegroundColor Red
        if ($_.Exception.Response) {
            Write-Host "  Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        }
    }
}

Write-Host "`n=== Test de données brutes ===" -ForegroundColor Yellow
try {
    $secteur = php artisan tinker --execute "echo App\Models\SecteurActivite::find(1)->toJson(JSON_PRETTY_PRINT);"
    Write-Host "Données brutes du secteur 1:" -ForegroundColor Gray
    Write-Host $secteur
} catch {
    Write-Host "Impossible d'obtenir les données brutes" -ForegroundColor Red
}
