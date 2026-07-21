<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Secteurs avec nom BTP:\n";
$btpSecteurs = App\Models\SecteurActivite::where('nom', 'like', '%BTP%')->get(['id', 'nom', 'code']);

foreach ($btpSecteurs as $s) {
    echo "ID {$s->id}: {$s->nom} ({$s->code})\n";
}

echo "\nTous les secteurs:\n";
$allSecteurs = App\Models\SecteurActivite::orderBy('id')->get(['id', 'nom', 'code']);

foreach ($allSecteurs as $s) {
    echo "ID {$s->id}: {$s->nom} ({$s->code})\n";
}
