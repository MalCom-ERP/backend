<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\CommandeAchat;
use App\Models\CommandeAchatDetail;
use App\Models\Licence;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\SyscohadaPlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GestionnaireProductReapproRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected Boutique $boutique;
    protected User $admin;
    protected User $admin1;
    protected User $gestionnaire;
    protected Categorie $categorie;
    protected Produit $produit;
    protected CommandeAchat $commandeEnAttente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SyscohadaPlanComptableSeeder::class);

        $this->boutique = Boutique::create([
            'nom' => 'Boutique Restriction Test',
            'adresse' => 'Bamako ACI 2000',
            'telephone' => '70000099',
            'email' => 'test-rest@malcom-lgc.com',
            'is_active' => true,
            'date_expiration_licence' => now()->addYear(),
        ]);

        Licence::create([
            'boutique_id' => $this->boutique->id,
            'cle_licence' => 'MC-TEST-2026-GEST-REST',
            'duree_jours' => 365,
            'statut' => 'active',
            'date_activation' => now(),
            'date_expiration' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'name' => 'Admin Principal',
            'email' => 'admin-rest@malcom-lgc.com',
            'password' => 'password123',
            'role' => 'admin',
            'boutique_id' => $this->boutique->id,
            'is_active' => true,
        ]);

        $this->admin1 = User::create([
            'name' => 'Admin Secondaire',
            'email' => 'admin1-rest@malcom-lgc.com',
            'password' => 'password123',
            'role' => 'admin1',
            'boutique_id' => $this->boutique->id,
            'is_active' => true,
        ]);

        // Even with legacy delegated permissions set to true in DB, gestionnaire must be blocked.
        $this->gestionnaire = User::create([
            'name' => 'Gestionnaire Bloqué',
            'email' => 'gest-rest@malcom-lgc.com',
            'password' => 'password123',
            'role' => 'gestionnaire',
            'boutique_id' => $this->boutique->id,
            'is_active' => true,
            'permissions' => [
                'can_reappro' => true,
                'can_change_prices' => true,
                'can_view_ca' => true,
            ],
        ]);

        $this->categorie = Categorie::create([
            'nom' => 'Divers',
            'description' => 'Catégorie test',
            'boutique_id' => $this->boutique->id,
        ]);

        $this->produit = Produit::create([
            'nom' => 'Produit Test',
            'reference' => 'TST-001',
            'categorie_id' => $this->categorie->id,
            'prix_detail' => 1500,
            'prix_master' => 1200,
        ]);

        Stock::create([
            'produit_id' => $this->produit->id,
            'boutique_id' => $this->boutique->id,
            'quantite' => 10,
            'prix_vente' => 1500,
            'prix_achat' => 1000,
            'date_reapprovisionnement' => now()->format('Y-m-d'),
        ]);

        $this->commandeEnAttente = CommandeAchat::create([
            'reference' => 'ACH-TEST-001',
            'boutique_id' => $this->boutique->id,
            'user_id' => $this->admin->id,
            'statut' => 'en_commande',
            'type_paiement' => 'comptant',
            'moyen_paiement' => 'especes',
            'sous_total_ht' => 5000,
            'frais_transport' => 0,
            'frais_douane' => 0,
            'frais_manutention' => 0,
            'remise' => 0,
            'montant_total' => 5000,
            'montant_paye' => 5000,
            'montant_restant' => 0,
            'date_commande' => now()->format('Y-m-d'),
        ]);

        CommandeAchatDetail::create([
            'commande_achat_id' => $this->commandeEnAttente->id,
            'produit_id' => $this->produit->id,
            'unite_achat' => 'Unité',
            'quantite_conditionnement' => 1,
            'quantite_commandee' => 5,
            'quantite_recue' => 0,
            'prix_achat_unitaire' => 1000,
            'frais_approche_unitaire' => 0,
            'cout_revient_unitaire' => 1000,
            'montant_ligne' => 5000,
        ]);
    }

    public function test_gestionnaire_cannot_edit_delete_restore_import_or_reappro_products(): void
    {
        Sanctum::actingAs($this->gestionnaire);
        $headers = ['X-Boutique-Id' => $this->boutique->id];

        $this->postJson("/api/produits/{$this->produit->id}", ['nom' => 'Modif Interdite'], $headers)->assertStatus(403);
        $this->putJson("/api/produits/{$this->produit->id}", ['nom' => 'Modif Interdite'], $headers)->assertStatus(403);
        $this->deleteJson("/api/produits/{$this->produit->id}", [], $headers)->assertStatus(403);
        $this->postJson("/api/produits/{$this->produit->id}/restore", [], $headers)->assertStatus(403);

        $csvFile = UploadedFile::fake()->createWithContent('import.csv', "nom;prix_achat;prix_vente\nTest;100;200");
        $this->post('/api/produits/import-csv', ['file' => $csvFile], array_merge($headers, ['Accept' => 'application/json']))
            ->assertStatus(403);

        $this->getJson('/api/reappro', $headers)->assertStatus(403);
        $this->postJson('/api/reappro', [
            'produits' => [['produit_id' => $this->produit->id, 'quantite' => 5, 'prix_achat' => 1000, 'prix_vente' => 1500]],
        ], $headers)->assertStatus(403);

        $this->getJson('/api/fournisseurs/achats', $headers)->assertStatus(403);
        $this->postJson('/api/fournisseurs/achats', [
            'statut' => 'recu',
            'lignes' => [['produit_id' => $this->produit->id, 'quantite' => 2, 'prix_achat_unitaire' => 1000]],
        ], $headers)->assertStatus(403);
        $this->postJson("/api/fournisseurs/achats/{$this->commandeEnAttente->id}/receptionner", [], $headers)->assertStatus(403);

        $this->postJson('/api/stocks/adjustment', [
            'produit_id' => $this->produit->id,
            'quantite_physique' => 25,
            'motif' => 'Test',
        ], $headers)->assertStatus(403);
    }

    public function test_gestionnaire_can_read_and_create_new_products(): void
    {
        Sanctum::actingAs($this->gestionnaire);
        $headers = ['X-Boutique-Id' => $this->boutique->id];

        $this->getJson('/api/produits', $headers)->assertStatus(200);
        $this->getJson("/api/produits/editProd/{$this->produit->id}", $headers)->assertStatus(200);

        $this->postJson('/api/produits', [
            'nom' => 'Nouveau Produit Autorisé',
            'categorie_id' => $this->categorie->id,
            'prix_achat' => 1200,
            'prix_vente' => 1800,
            'quantite' => 5,
        ], $headers)->assertStatus(201);
    }

    public function test_permissions_are_sanitized_for_gestionnaire(): void
    {
        Sanctum::actingAs($this->gestionnaire);
        $statusRes = $this->getJson('/api/user/check-status')->assertStatus(200);
        $this->assertFalse((bool) $statusRes->json('permissions.can_reappro'));
        $this->assertFalse((bool) $statusRes->json('permissions.can_change_prices'));

        Sanctum::actingAs($this->admin);
        $updateRes = $this->putJson("/api/user/{$this->gestionnaire->id}", [
            'name' => $this->gestionnaire->name,
            'email' => $this->gestionnaire->email,
            'role' => 'gestionnaire',
            'boutique_id' => $this->boutique->id,
            'permissions' => [
                'can_reappro' => true,
                'can_change_prices' => true,
                'can_view_ca' => true,
            ],
        ])->assertStatus(200);

        $this->assertFalse((bool) $updateRes->json('permissions.can_reappro'));
        $this->assertFalse((bool) $updateRes->json('permissions.can_change_prices'));
        $this->assertTrue((bool) $updateRes->json('permissions.can_view_ca'));
    }

    public function test_admin_and_admin1_keep_full_product_and_reappro_access(): void
    {
        foreach ([$this->admin, $this->admin1] as $idx => $privilegedUser) {
            Sanctum::actingAs($privilegedUser);
            $headers = ['X-Boutique-Id' => $this->boutique->id];

            $this->postJson("/api/produits/{$this->produit->id}", [
                'nom' => 'Produit Maj Admin ' . $idx,
                'categorie_id' => $this->categorie->id,
                'prix_vente' => 1600,
                'prix_achat' => 1000,
            ], $headers)->assertStatus(200);

            $this->getJson('/api/reappro', $headers)->assertStatus(200);
            $this->postJson('/api/reappro', [
                'produits' => [['produit_id' => $this->produit->id, 'produit' => $this->produit->nom, 'quantite' => 2, 'prix_achat' => 1000, 'prix_vente' => 1600]],
            ], $headers)->assertStatus(201);

            $this->postJson('/api/fournisseurs/achats', [
                'statut' => 'recu',
                'type_paiement' => 'comptant',
                'lignes' => [['produit_id' => $this->produit->id, 'quantite' => 3, 'prix_achat_unitaire' => 1000]],
            ], $headers)->assertStatus(201);

            $this->postJson('/api/stocks/adjustment', [
                'produit_id' => $this->produit->id,
                'quantite_physique' => 20,
                'motif' => 'Contrôle admin',
            ], $headers)->assertStatus(200);
        }

        Sanctum::actingAs($this->admin);
        $headers = ['X-Boutique-Id' => $this->boutique->id];
        $this->postJson("/api/fournisseurs/achats/{$this->commandeEnAttente->id}/receptionner", [], $headers)->assertStatus(200);
        $this->deleteJson("/api/produits/{$this->produit->id}", [], $headers)->assertStatus(200);
        $this->postJson("/api/produits/{$this->produit->id}/restore", ['quantite' => 15], $headers)->assertStatus(200);
    }
}
