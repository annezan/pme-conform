# Debug du token et de l'API
$baseUrl = "http://127.0.0.1:8000/api"

Write-Host "=== DEBUG TOKEN ET API ===" -ForegroundColor Yellow

# 1. Obtenir un token frais
Write-Host "`n1. Obtention d'un token frais..." -ForegroundColor Cyan
$body = @{
    email = "admin@asc-ia.local"
    password = "Admin@2026!"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/login" -Method Post -Body $body -ContentType "application/json" -ErrorAction Stop
    $token = $loginResponse.token
    Write-Host "✅ Token obtenu: $token" -ForegroundColor Green
    
    # 2. Test avec différents formats de headers
    Write-Host "`n2. Test différents formats d'authentification..." -ForegroundColor Cyan
    
    # Format 1: Authorization: Bearer token
    Write-Host "Test 1: Authorization: Bearer token" -ForegroundColor Gray
    $headers1 = @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    }
    
    try {
        $response1 = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers1 -ErrorAction Stop
        Write-Host "✅ Format 1 fonctionne: $($response1.meta.total) secteurs" -ForegroundColor Green
    } catch {
        Write-Host "❌ Format 1 échoue: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    # Format 2: X-API-TOKEN (alternative)
    Write-Host "Test 2: X-API-TOKEN" -ForegroundColor Gray
    $headers2 = @{
        "X-API-TOKEN" = $token
        "Content-Type" = "application/json"
    }
    
    try {
        $response2 = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -Headers $headers2 -ErrorAction Stop
        Write-Host "✅ Format 2 fonctionne: $($response2.meta.total) secteurs" -ForegroundColor Green
    } catch {
        Write-Host "❌ Format 2 échoue: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    # 3. Test sans authentification (pour comparer l'erreur)
    Write-Host "`n3. Test sans authentification..." -ForegroundColor Cyan
    try {
        $response3 = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite" -Method Get -ErrorAction Stop
        Write-Host "✅ Sans auth fonctionne: $($response3.meta.total) secteurs" -ForegroundColor Green
    } catch {
        Write-Host "❌ Sans auth échoue: $($_.Exception.Message)" -ForegroundColor Red
        if ($_.Exception.Response) {
            Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        }
    }
    
    # 4. Test endpoint sans auth (secteurs-activite-liste)
    Write-Host "`n4. Test endpoint sans authentification..." -ForegroundColor Cyan
    try {
        $response4 = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite-liste" -Method Get -ErrorAction Stop
        Write-Host "✅ Endpoint sans auth fonctionne: $($response4.count()) secteurs" -ForegroundColor Green
    } catch {
        Write-Host "❌ Endpoint sans auth échoue: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    # 5. Vérification du token dans la base
    Write-Host "`n5. Vérification du token en base..." -ForegroundColor Cyan
    $tokenParts = $token.Split('|')
    if ($tokenParts.Length -ge 2) {
        $tokenId = $tokenParts[0]
        Write-Host "Token ID: $tokenId" -ForegroundColor Gray
        Write-Host "Token hash: $($tokenParts[1].Substring(0, 20))..." -ForegroundColor Gray
    }
    
} catch {
    Write-Host "❌ Erreur login: $($_.Exception.Message)" -ForegroundColor Red
}
