<?php

/**
 * Modele PlanActionItemPreuve — Piece justificative attachee a un item de
 * plan d'action.
 *
 * Le client uploade des preuves sur chaque item du kanban (politique signee,
 * proces-verbal de comite, screenshot d'outil, etc.). Quand il clique
 * "Soumettre au consultant" sur le plan, un job extrait le texte des
 * preuves et appelle un LLM qui compare avec la recommandation de l'ecart
 * lie a l'item, pour produire un verdict (conforme/partielle/non_conforme).
 *
 * Stockage local : storage/app/plan_action_items/{item_id}/.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanActionItemPreuve extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plan_action_item_id',
        'uploaded_by',
        'libelle',
        'description',
        'nom_fichier_original',
        'chemin',
        'mime',
        'taille_octets',
        'contenu_extrait',
    ];

    protected function casts(): array
    {
        return [
            'taille_octets' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PlanActionItem::class, 'plan_action_item_id');
    }

    public function uploadeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
