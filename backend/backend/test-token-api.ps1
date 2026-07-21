# Test d'API avec token Sanctum
$baseUrl = "http://127.0.0.1:8000/api"

Write-Host "Test d'authentification et d'API avec token Sanctum..." -ForegroundColor Yellow

# 1. Login pour obtenir le token
$body = @{
    email = "admin@asc-ia.local"
    password = "Admin@2026!"
} | ConvertTo-Json

try {
    Write-Host "1. Tentative de login..." -ForegroundColor Cyan
    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/login" -Method Post -Body $body -ContentType "application/json" -ErrorAction Stop
    Write-Host "✅ Login réussi!" -ForegroundColor Green
    
    if ($loginResponse.token) {
        $token = $loginResponse.token
        Write-Host "Token obtenu: $($token.Substring(0, 20))..." -ForegroundColor Gray
        
        # 2. Test de l'API avec le token
        $headers = @{
            "Authorization" = "Bearer $token"
            "Content-Type" = "application/json"
        }
        
        Write-Host "`n2. Test de l'API secteurs-activite avec token..." -ForegroundColor Cyan
        try {
            $secteursResponse = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers -ErrorAction Stop
            Write-Host "✅ API fonctionne avec token!" -ForegroundColor Green
            $secteursResponse | ConvertTo-Json -Depth 5
        } catch {
            Write-Host "❌ Erreur API: $($_.Exception.Message)" -ForegroundColor Red
            if ($_.Exception.Response) {
                Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
            }
        }
        
        # 3. Test avec filtres
        Write-Host "`n3. Test avec filtres..." -ForegroundColor Cyan
        try {
            $filtresResponse = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite?is_actif=true&per_page=5" -Method Get -Headers $headers -ErrorAction Stop
            Write-Host "✅ Filtres fonctionnent!" -ForegroundColor Green
            $filtresResponse | ConvertTo-Json -Depth 3
        } catch {
            Write-Host "❌ Erreur filtres: $($_.Exception.Message)" -ForegroundColor Red
        }
        
    } else {
        Write-Host "❌ Aucun token retourné par le login" -ForegroundColor Red
        $loginResponse | ConvertTo-Json -Depth 3
    }
    
} catch {
    Write-Host "❌ Erreur login: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        $errorStream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($errorStream)
        $errorBody = $reader.ReadToEnd()
        Write-Host "Détails: $errorBody" -ForegroundColor Red
    }
}
