# Test de mise à jour avec données en double
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "=== TEST UPDATE DONNÉES EXISTANTES ===" -ForegroundColor Yellow

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

# Test 1: Essayer de mettre à jour avec un nom qui existe déjà
Write-Host "`n1. Test mise à jour avec nom existant (BTP & Immobilier)..." -ForegroundColor Cyan

$updateData = @{
    nom = "BTP & Immobilier"  # Ce nom existe déjà (ID 5)
    description = "Test de duplication"
    code = "TEST_CODE"
    is_actif = $true
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/6" -Method Put -Body $updateData -Headers $headers -ErrorAction Stop
    Write-Host "✅ Mise à jour réussie (étrange!)" -ForegroundColor Yellow
    $response | ConvertTo-Json -Depth 2
} catch {
    Write-Host "❌ Erreur attendue: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        try {
            $errorStream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($errorStream)
            $errorBody = $reader.ReadToEnd()
            $errorJson = $errorBody | ConvertFrom-Json
            Write-Host "Erreurs de validation:" -ForegroundColor Red
            $errorJson.errors.PSObject.Properties | ForEach-Object {
                Write-Host "  - $($_.Name): $($_.Value -join ', ')" -ForegroundColor Gray
            }
        } catch {
            Write-Host "Impossible de parser l'erreur" -ForegroundColor Red
        }
    }
}

# Test 2: Essayer de mettre à jour avec un code qui existe déjà
Write-Host "`n2. Test mise à jour avec code existant..." -ForegroundColor Cyan

$updateData2 = @{
    nom = "Test Unique"
    description = "Test de duplication de code"
    code = "BTP_IMMOBILIER"  # Ce code existe déjà (ID 5)
    is_actif = $true
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/6" -Method Put -Body $updateData2 -Headers $headers -ErrorAction Stop
    Write-Host "✅ Mise à jour réussie (étrange!)" -ForegroundColor Yellow
    $response | ConvertTo-Json -Depth 2
} catch {
    Write-Host "❌ Erreur attendue: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    }
}

# Test 3: Mise à jour valide (avec données uniques)
Write-Host "`n3. Test mise à jour valide..." -ForegroundColor Cyan

$updateData3 = @{
    nom = "Test Unique $(Get-Date -Format 'HHmmss')"
    description = "Secteur de test unique"
    code = "TEST_$(Get-Date -Format 'HHmmss')"
    is_actif = $true
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/6" -Method Put -Body $updateData3 -Headers $headers -ErrorAction Stop
    Write-Host "✅ Mise à jour valide réussie!" -ForegroundColor Green
    Write-Host "Nouveau nom: $($response.data.nom)" -ForegroundColor Gray
    Write-Host "Nouveau code: $($response.data.code)" -ForegroundColor Gray
} catch {
    Write-Host "❌ Erreur inattendue: $($_.Exception.Message)" -ForegroundColor Red
}
