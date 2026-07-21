# Lance le worker Laravel pour traiter les jobs d'analyse enrichie IA.
#
# Usage : ouvrir un terminal PowerShell SEPARE de celui qui execute
#         "php artisan serve" puis lancer ce script.
#
# Le worker reste en ecoute en permanence et traite chaque job d'analyse
# enrichie IA dispose sur la queue "analyses". Le timeout est porte a 4h
# pour absorber une analyse complete (~65 ecarts * 1-3 min = 1-3h sur Ollama CPU).
#
# Si Ollama est tres lent, certains appels timeoutent : le job continue
# en fallback templates, ne plante pas. Le worker reste en vie.
#
# Pour arreter proprement : Ctrl+C dans ce terminal.

Write-Host ""
Write-Host "=== Worker analyses Laravel ===" -ForegroundColor Cyan
Write-Host "Ecoute la queue: analyses"
Write-Host "Timeout par job: 4h (14400s)"
Write-Host "Tentatives    : 1 (pas de retry sur echec)"
Write-Host ""
Write-Host "Laissez ce terminal ouvert tant que vous utilisez le mode enrichi IA."
Write-Host "Ctrl+C pour arreter."
Write-Host ""

php artisan queue:work --queue=analyses --timeout=14400 --tries=1 --memory=2048
