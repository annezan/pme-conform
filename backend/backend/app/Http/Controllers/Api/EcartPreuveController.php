<?php

/**
 * Controleur EcartPreuveController — Gestion des preuves justificatives
 * attachees a un ecart de conformite.
 *
 * Le consultant ASC et le client connecte peuvent tous deux uploader des
 * preuves : c'est le client qui execute la correction, mais l'ASC peut
 * aussi documenter le suivi en charge.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ecart;
use App\Models\EcartPreuve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcartPreuveController extends Controller
{
    public function index(Request $request, Ecart $ecart): JsonResponse
    {
        $this->verifierAcces($request->user(), $ecart);

        return response()->json([
            'preuves' => $ecart->preuves()->with('uploadeur:id,nom,prenom')->get(),
        ]);
    }

    public function store(Request $request, Ecart $ecart): JsonResponse
    {
        $this->verifierAcces($request->user(), $ecart);

        $data = $request->validate([
            'fichier' => 'required|file|max:25600', // 25 Mo
            'libelle' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store("ecarts/{$ecart->id}", 'local');

        $preuve = EcartPreuve::create([
            'ecart_id' => $ecart->id,
            'uploaded_by' => $request->user()->id,
            'libelle' => $data['libelle'],
            'description' => $data['description'] ?? null,
            'nom_fichier_original' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'mime' => $fichier->getMimeType(),
            'taille_octets' => $fichier->getSize(),
        ]);

        return response()->json([
            'preuve' => $preuve->load('uploadeur:id,nom,prenom'),
            'message' => 'Preuve ajoutee.',
        ], 201);
    }

    public function telecharger(Request $request, EcartPreuve $preuve): BinaryFileResponse
    {
        $this->verifierAcces($request->user(), $preuve->ecart);

        abort_unless(Storage::disk('local')->exists($preuve->chemin), 404, 'Fichier introuvable.');

        return response()->download(
            Storage::disk('local')->path($preuve->chemin),
            $preuve->nom_fichier_original
        );
    }

    public function destroy(Request $request, EcartPreuve $preuve): JsonResponse
    {
        $this->verifierAcces($request->user(), $preuve->ecart);

        // Un user sans permission de gestion des ecarts (typiquement cote client) ne peut
        // supprimer que les preuves qu'il a lui-meme deposees.
        $user = $request->user();
        $aPermissionGestion = $user->hasAnyPermission(['view-all-ecarts', 'update-ecarts']);
        if (! $aPermissionGestion && $preuve->uploaded_by !== $user->id) {
            abort(403, 'Vous ne pouvez supprimer que les preuves que vous avez deposees.');
        }

        if ($preuve->chemin && Storage::disk('local')->exists($preuve->chemin)) {
            Storage::disk('local')->delete($preuve->chemin);
        }
        $preuve->delete();

        return response()->json(['message' => 'Preuve supprimee.']);
    }

    /**
     * Verifie qu'un user sans permission de voir tous les ecarts ne peut acceder
     * qu'aux preuves des ecarts lies a une de ses entreprises rattachees.
     */
    private function verifierAcces($user, Ecart $ecart): void
    {
        if ($user->hasAnyPermission(['view-ecarts', 'view-all-ecarts'])) {
            return;
        }

        $clientIds = $user->clients()->pluck('clients.id');
        $clientId = $ecart->analyse?->mission?->client_id;

        abort_unless($clientId && $clientIds->contains($clientId), 403, 'Acces refuse.');
    }
}
