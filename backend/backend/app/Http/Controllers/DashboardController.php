<?php

/**
 * Contrôleur DashboardController — API du tableau de bord.
 *
 * Retourne les statistiques d'usage, modules actifs et activité récente en JSON.
 */

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Mission;
use App\Models\Module;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'statistiques' => [
                'clients_actifs' => Client::where('statut', 'actif')->count(),
                'missions_en_cours' => Mission::where('statut', 'en_cours')->count(),
                'documents_total' => Document::count(),
                'conversations_actives' => Conversation::where('statut', 'active')->count(),
            ],
            'modules_actifs' => Module::actifs()
                ->orderBy('ordre_affichage')
                ->get(['id', 'slug', 'nom', 'description', 'icone', 'couleur']),
            'agents_disponibles' => Agent::actifs()
                ->with('module:id,nom,slug')
                ->orderBy('ordre_affichage')
                ->get(['id', 'slug', 'nom', 'description', 'icone', 'couleur', 'type', 'module_id']),
            'missions_recentes' => Mission::with('client:id,raison_sociale')
                ->when(! $user->hasPermissionTo('view-all-missions'), function ($query) use ($user) {
                    $query->where('responsable_id', $user->id);
                })
                ->latest()
                ->take(5)
                ->get(['id', 'reference', 'titre', 'statut', 'priorite', 'client_id', 'updated_at']),
        ]);
    }
}
