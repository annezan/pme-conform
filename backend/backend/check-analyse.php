<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$a = App\Models\Analyse::find(1);
if (! $a) { echo "Analyse 1 introuvable\n"; exit; }

echo "Statut: {$a->statut}\n";
echo "Etape: " . ($a->etape_courante ?? '-') . "\n";
echo "Progression: " . ($a->progression_pct ?? 0) . "%\n";
echo "Exigences verifiees: {$a->nb_exigences_verifiees}\n";
echo "Ecarts: critique={$a->nb_ecarts_critiques} majeur={$a->nb_ecarts_majeurs} mineur={$a->nb_ecarts_mineurs}\n";
echo "Enrichissement_ia: " . ($a->enrichissement_ia ? 'oui' : 'non') . "\n";
if ($a->erreur_message) echo "ERREUR: {$a->erreur_message}\n";
