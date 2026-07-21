<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama — Serveur LLM local
    |--------------------------------------------------------------------------
    */
    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'llama3.2'),
        'embedding_dimensions' => (int) env('PGVECTOR_DIMENSIONS', 3072),
        // Flag pour desactiver les embeddings (utile en dev quand on n'a qu'un
        // modele chat lourd type llama3.2 — chaque embedding prend 5-10s, ce qui
        // bloque l'indexation. Le RAG bascule en fulltext, qui reste fonctionnel).
        'embeddings_enabled' => filter_var(env('OLLAMA_EMBEDDINGS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
        'max_tokens' => (int) env('OLLAMA_MAX_TOKENS', 4096),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pseudonymisation des données personnelles
    |--------------------------------------------------------------------------
    */
    'pseudonymization' => [
        'enabled' => env('PSEUDONYMIZATION_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract OCR — Extraction de texte des images uploadees
    |--------------------------------------------------------------------------
    | Le binaire tesseract doit etre installe sur le serveur. Sous Windows,
    | renseigner TESSERACT_PATH avec le chemin complet vers tesseract.exe.
    | Sous Linux/macOS, laisser vide si tesseract est dans le PATH.
    | Les langues OCR sont separees par + (ex: fra+eng).
    */
    'tesseract' => [
        'path' => env('TESSERACT_PATH'),
        'lang' => env('TESSERACT_LANG', 'fra+eng'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AS Consulting — Coordonnees du portefeuille
    |--------------------------------------------------------------------------
    | Adresse email centrale qui recoit les demandes de prise de rendez-vous
    | apres un audit flash. Les utilisateurs avec le role admin sont notifies
    | en complement.
    */
    'asc' => [
        'contact_email' => env('MAIL_ASC_CONTACT', 'contact@as-consulting.ci'),
    ],

];
