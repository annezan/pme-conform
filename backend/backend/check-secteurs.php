<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Secteurs existants:\n";
$secteurs = App\Models\SecteurActivite::orderBy('id')->get(['id', 'nom']);

foreach ($secteurs as $s) {
    echo "ID {$s->id}: {$s->nom}\n";
}
