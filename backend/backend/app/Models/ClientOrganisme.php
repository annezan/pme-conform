<?php

/**
 * ClientOrganisme — Informations generales du client/organisme pour
 * le registre MOBISOFT : responsable de traitement (RT) et DPO.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientOrganisme extends Model
{
    protected $table = 'clients_organismes';

    protected $fillable = [
        'client_id',
        'rt_nom', 'rt_fonction', 'rt_adresse', 'rt_code_postal', 'rt_ville', 'rt_pays', 'rt_telephone', 'rt_email',
        'dpo_nom', 'dpo_adresse', 'dpo_code_postal', 'dpo_ville', 'dpo_pays', 'dpo_telephone', 'dpo_email',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
