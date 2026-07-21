<?php

/**
 * Routes web du backend ASC-IA.
 *
 * Le frontend React est servi separement.
 * Ce fichier ne contient que le health check.
 */

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'ASC-IA Plateforme API',
        'version' => '1.0.0',
        'status' => 'ok',
    ]);
});
