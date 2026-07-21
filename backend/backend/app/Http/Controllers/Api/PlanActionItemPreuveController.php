<?php

/**
 * Controleur PlanActionItemPreuveController — Gestion des preuves
 * justificatives attachees a un item de plan d'action.
 *
 * Le client (client_admin) uploade des preuves sur chaque item du kanban
 * pour documenter sa correction (politique signee, capture d'outil, contrat,
 * proces-verbal). Le consultant ASC peut aussi consulter/uploader.
 *
 * Acces : meme regle que la policy mettreAJourItem de PlanAction
 * (permission manage-plans-actions-items + appartenance au client du plan).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanActionItem;
use App\Models\PlanActionItemPreuve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanActionItemPreuveController extends Controller
{
    public function index(Request $request, PlanActionItem $item): JsonResponse
    {
        $this->autoriserAcces($request, $item);

        return response()->json([
            'preuves' => $item->preuves()->with('uploadeur:id,nom,prenom')->get(),
        ]);
    }

    public function store(Request $request, PlanActionItem $item): JsonResponse
    {
        $this->autoriserAcces($request, $item);

        // Plan deja soumis et verification en cours/terminee : on bloque les
        // nouveaux uploads pour eviter qu'un client n'ajoute des preuves apres
        // verification, ce qui fausserait le verdict.
        $plan = $item->planAction;
        if ($plan->soumis_le && in_array($plan->verification_statut, ['en_cours', 'terminee'], true)) {
            return response()->json([
                'message' => 'Plan deja soumis au consultant — vous ne pouvez plus modifier les preuves. Demandez au consultant de rouvrir le plan.',
            ], 422);
        }

        $data = $request->validate([
            'fichier' => 'required|file|max:25600', // 25 Mo
            'libelle' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store("plan_action_items/{$item->id}", 'local');

        $preuve = PlanActionItemPreuve::create([
            'plan_action_item_id' => $item->id,
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

    public function telecharger(Request $request, PlanActionItemPreuve $preuve): BinaryFileResponse
    {
        $this->autoriserAcces($request, $preuve->item);

        abort_unless(Storage::disk('local')->exists($preuve->chemin), 404, 'Fichier introuvable.');

        return response()->download(
            Storage::disk('local')->path($preuve->chemin),
            $preuve->nom_fichier_original
        );
    }

    public function destroy(Request $request, PlanActionItemPreuve $preuve): JsonResponse
    {
        $this->autoriserAcces($request, $preuve->item);

        $plan = $preuve->item->planAction;
        if ($plan->soumis_le && in_array($plan->verification_statut, ['en_cours', 'terminee'], true)) {
            return response()->json([
                'message' => 'Plan deja soumis au consultant — vous ne pouvez plus modifier les preuves.',
            ], 422);
        }

        $user = $request->user();
        // Un user sans permission de gestion des plans (= client_admin standard)
        // ne peut supprimer que ses propres preuves.
        $aPermissionGestion = $user->hasAnyPermission(['view-all-plans-actions', 'update-plans-actions']);
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
     * Acces conditionne a la policy mettreAJourItem (et a view pour lecture).
     */
    private function autoriserAcces(Request $request, PlanActionItem $item): void
    {
        $plan = $item->planAction;
        $user = $request->user();

        // Lecture : tout user pouvant voir le plan
        if ($request->isMethod('get')) {
            abort_unless($user->can('view', $plan), 403, 'Acces refuse au plan.');
            return;
        }

        // Ecriture : doit pouvoir mettre a jour un item (client_admin du client
        // du plan, ou consultant ASC).
        abort_unless($user->can('mettreAJourItem', $plan), 403, 'Vous ne pouvez pas modifier les preuves de ce plan.');
    }
}
