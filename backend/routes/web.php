<?php

/**
 * Routes web du backend ASC-IA.
 *
 * Le frontend React (SPA) est servi depuis public/index.html, sur le MEME
 * domaine que l'API. Laravel sert donc :
 *   - /api/*        -> l'API (routes/api.php)
 *   - /health       -> health check JSON
 *   - tout le reste -> index.html (le routeur React prend le relais cote client)
 *
 * Le fallback ci-dessous est indispensable : sans lui, un rafraichissement sur
 * une route React (ex. /dashboard) renverrait une 404 Laravel.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check dedie (monitoring)
Route::get('/health', function () {
    return response()->json([
        'app' => 'ASC-IA Plateforme API',
        'version' => '1.0.0',
        'status' => 'ok',
    ]);
});

// SPA React : racine + toutes les routes cote client
Route::get('/', fn () => response()->file(public_path('index.html')));

Route::fallback(function (Request $request) {
    // Une URL /api/* non reconnue doit rester une vraie 404 JSON, pas le SPA.
    if ($request->is('api/*')) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    return response()->file(public_path('index.html'));
});
