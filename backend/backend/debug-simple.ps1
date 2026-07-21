# Test simple de l'endpoint show
$baseUrl = "http://127.0.0.1:8000/api"
$token = "4|ZfYQp1QCA6nut6NSsYGBnmDinuZh2taAr9kL3InV7d79aabc"

Write-Host "Test endpoint show avec ID 1" -ForegroundColor Yellow

try {
    $headers = @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    }
    
    $response = Invoke-RestMethod -Uri "$baseUrl/secteurs-activite/1" -Method Get -Headers $headers -ErrorAction Stop
    Write-Host "Succes!" -ForegroundColor Green
    $response.data | ConvertTo-Json -Depth 5
    
} catch {
    Write-Host "Erreur: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    }
}
