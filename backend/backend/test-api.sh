#!/bin/bash

# Script de test pour l'API DCP
BASE_URL="http://localhost:8000/api"

echo "🧪 Tests API DCP"
echo "================"

# Test 1: Health check
echo "1. Test Health Check..."
curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/health/ping"
echo " - Health check"

# Test 2: Login (à adapter avec vos identifiants)
echo "2. Test Login..."
TOKEN=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}' | \
  grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
    echo "✅ Login réussi"
    
    # Test 3: Secteurs d'activité
    echo "3. Test Secteurs d'activité..."
    curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/secteurs-activite" \
      -H "Authorization: Bearer $TOKEN"
    echo " - Secteurs d'activité"
    
    # Test 4: Clients
    echo "4. Test Clients..."
    curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/clients" \
      -H "Authorization: Bearer $TOKEN"
    echo " - Clients"
    
    # Test 5: Missions
    echo "5. Test Missions..."
    curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/missions" \
      -H "Authorization: Bearer $TOKEN"
    echo " - Missions"
    
else
    echo "❌ Login échoué"
fi

echo ""
echo "✨ Tests terminés !"
