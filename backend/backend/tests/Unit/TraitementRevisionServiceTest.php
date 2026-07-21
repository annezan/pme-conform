<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Traitement;
use App\Models\User;
use App\Services\Traitement\TraitementRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraitementRevisionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TraitementRevisionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->service = new TraitementRevisionService();
    }

    public function test_capturer_cree_une_revision_avec_snapshot_complet(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $traitement = Traitement::factory()->pourClient($client)->create([
            'nom' => 'Test',
            'saisi_par' => $user->id,
        ]);

        $revision = $this->service->capturer($traitement, $user, 'Creation');

        $this->assertNotNull($revision->id);
        $this->assertEquals($traitement->id, $revision->traitement_id);
        $this->assertEquals($user->id, $revision->modifie_par);
        $this->assertEquals('Creation', $revision->commentaire);
        $this->assertIsArray($revision->snapshot);
        $this->assertEquals('Test', $revision->snapshot['nom']);
    }

    public function test_timeline_retourne_revisions_avec_changements_detectes(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $traitement = Traitement::factory()->pourClient($client)->create([
            'nom' => 'V1', 'saisi_par' => $user->id,
        ]);

        $this->service->capturer($traitement, $user, 'Initiale');

        $traitement->update(['nom' => 'V2']);
        $this->service->capturer($traitement, $user, 'Mise a jour nom');

        $traitement->update(['statut' => 'valide']);
        $this->service->capturer($traitement, $user, 'Validation');

        $timeline = $this->service->timeline($traitement);

        $this->assertCount(3, $timeline);
        // La plus recente est en tete
        $this->assertEquals('Validation', $timeline[0]['commentaire']);
        // Un changement sur statut doit etre detecte
        $this->assertNotEmpty($timeline[0]['changements']);
        $this->assertContains('statut', collect($timeline[0]['changements'])->pluck('champ')->toArray());
    }

    public function test_timeline_sans_revisions_retourne_tableau_vide(): void
    {
        $client = Client::factory()->create();
        $traitement = Traitement::factory()->pourClient($client)->create();

        $timeline = $this->service->timeline($traitement);

        $this->assertIsArray($timeline);
        $this->assertEmpty($timeline);
    }
}
