<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Licence;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalSearchBoutiqueIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Boutique $boutiqueA;
    protected Boutique $boutiqueB;
    protected User $admin;
    protected User $vendeurA;
    protected Categorie $categorie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->boutiqueA = Boutique::create([
            'nom' => 'Boutique Principale A',
            'adresse' => 'Bamako Centre',
            'telephone' => '70000010',
            'email' => 'boutique-a@malcom-lgc.com',
            'is_active' => true,
            'date_expiration_licence' => now()->addYear(),
        ]);

        Licence::create([
            'boutique_id' => $this->boutiqueA->id,
            'cle_licence' => 'MC-TEST-2026-SEARCH-ISO',
            'duree_jours' => 365,
            'statut' => 'active',
            'date_activation' => now(),
            'date_expiration' => now()->addYear(),
        ]);

        $this->boutiqueB = Boutique::create([
            'nom' => 'Filiale B',
            'adresse' => 'Bamako Badalabougou',
            'telephone' => '70000020',
            'email' => 'filiale-b@malcom-lgc.com',
            'is_active' => true,
            'parent_id' => $this->boutiqueA->id,
            'date_expiration_licence' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'name' => 'Admin Groupe',
            'email' => 'admin-iso@malcom-lgc.com',
            'password' => 'password123',
            'role' => 'admin',
            'boutique_id' => $this->boutiqueA->id,
            'is_active' => true,
        ]);

        $this->vendeurA = User::create([
            'name' => 'Vendeur Boutique A',
            'email' => 'vendeur-a@malcom-lgc.com',
            'password' => 'password123',
            'role' => 'vendeur',
            'boutique_id' => $this->boutiqueA->id,
            'is_active' => true,
        ]);

        $this->categorie = Categorie::create([
            'nom' => 'Outillage',
            'description' => 'Matériel de quincaillerie',
            'boutique_id' => $this->boutiqueA->id,
        ]);
    }

    public function test_products_from_other_filiales_are_never_returned_or_leaked(): void
    {
        $prodExclusifA = Produit::create([
            'nom' => 'Marteau Exclusif A',
            'reference' => 'ART-EX-A',
            'categorie_id' => $this->categorie->id,
            'prix_detail' => 5000,
            'prix_master' => 4500,
        ]);
        Stock::create([
            'produit_id' => $prodExclusifA->id,
            'boutique_id' => $this->boutiqueA->id,
            'quantite' => 12,
            'prix_vente' => 5500,
            'prix_achat' => 3500,
            'date_reapprovisionnement' => now(),
        ]);

        $prodExclusifB = Produit::create([
            'nom' => 'Perceuse Exclusif B',
            'reference' => 'ART-EX-B',
            'categorie_id' => $this->categorie->id,
            'prix_detail' => 35000,
            'prix_master' => 32000,
        ]);
        Stock::create([
            'produit_id' => $prodExclusifB->id,
            'boutique_id' => $this->boutiqueB->id,
            'quantite' => 8,
            'prix_vente' => 36000,
            'prix_achat' => 25000,
            'date_reapprovisionnement' => now(),
        ]);

        $prodRuptureADispoB = Produit::create([
            'nom' => 'Accessoire Rupture A',
            'reference' => 'AKRT-RUPT',
            'categorie_id' => $this->categorie->id,
            'prix_detail' => 15000,
            'prix_master' => 14000,
        ]);
        Stock::create([
            'produit_id' => $prodRuptureADispoB->id,
            'boutique_id' => $this->boutiqueA->id,
            'quantite' => 0,
            'prix_vente' => 15000,
            'prix_achat' => 10000,
            'date_reapprovisionnement' => now(),
        ]);
        Stock::create([
            'produit_id' => $prodRuptureADispoB->id,
            'boutique_id' => $this->boutiqueB->id,
            'quantite' => 5,
            'prix_vente' => 17000,
            'prix_achat' => 10000,
            'date_reapprovisionnement' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        // Recherche en mode 'all' sur la boutique A
        $resA = $this->getJson('/api/global-search?q=', ['X-Boutique-Id' => $this->boutiqueA->id]);
        $resA->assertStatus(200);
        $idsA = collect($resA->json('produits'))->pluck('id')->all();
        $this->assertContains($prodExclusifA->id, $idsA);
        $this->assertContains($prodRuptureADispoB->id, $idsA);
        $this->assertNotContains($prodExclusifB->id, $idsA);

        // Vérifier le filtre explicite #produits pour le produit exclusif B depuis A
        $resSearchBFromA = $this->getJson('/api/global-search?q=' . urlencode('#Perceuse Exclusif B'), [
            'X-Boutique-Id' => $this->boutiqueA->id,
        ]);
        $resSearchBFromA->assertStatus(200);
        $this->assertCount(0, $resSearchBFromA->json('produits'));

        // Produit en rupture dans A mais dispo dans B : aucune info filiale B ne doit fuiter
        $resRuptureA = $this->getJson('/api/global-search?q=' . urlencode('#Accessoire Rupture A'), [
            'X-Boutique-Id' => $this->boutiqueA->id,
        ]);
        $resRuptureA->assertStatus(200);
        $itemRupture = collect($resRuptureA->json('produits'))->firstWhere('id', $prodRuptureADispoB->id);
        $this->assertNotNull($itemRupture);
        $this->assertSame(0, $itemRupture['stock_restant']);
        $this->assertSame('rupture', $itemRupture['stock_status']);
        $this->assertSame([], $itemRupture['filiales_disponibles']);
        $this->assertSame(0, $itemRupture['total_stock_filiales']);

        // Inversement : depuis la filiale B, le produit exclusif A ne doit pas apparaître
        $resB = $this->getJson('/api/global-search?q=' . urlencode('#Exclusif'), [
            'X-Boutique-Id' => $this->boutiqueB->id,
        ]);
        $resB->assertStatus(200);
        $idsB = collect($resB->json('produits'))->pluck('id')->all();
        $this->assertContains($prodExclusifB->id, $idsB);
        $this->assertNotContains($prodExclusifA->id, $idsB);
    }

    public function test_shared_product_uses_connected_boutique_stock_and_price(): void
    {
        $sharedProd = Produit::create([
            'nom' => 'Câble Électrique Partagé',
            'reference' => 'CAB-PART',
            'categorie_id' => $this->categorie->id,
            'prix_detail' => 9000,
            'prix_master' => 8000,
        ]);

        Stock::create([
            'produit_id' => $sharedProd->id,
            'boutique_id' => $this->boutiqueA->id,
            'quantite' => 14,
            'prix_vente' => 10500,
            'prix_achat' => 7000,
            'date_reapprovisionnement' => now(),
        ]);

        Stock::create([
            'produit_id' => $sharedProd->id,
            'boutique_id' => $this->boutiqueB->id,
            'quantite' => 27,
            'prix_vente' => 12000,
            'prix_achat' => 7000,
            'date_reapprovisionnement' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $resA = $this->getJson('/api/global-search?q=' . urlencode('#CAB-PART'), [
            'X-Boutique-Id' => $this->boutiqueA->id,
        ]);
        $resA->assertStatus(200);
        $prodInA = collect($resA->json('produits'))->firstWhere('id', $sharedProd->id);
        $this->assertEquals(14.0, $prodInA['stock_restant']);
        $this->assertEquals(10500.0, $prodInA['prix_detail']);

        $resB = $this->getJson('/api/global-search?q=' . urlencode('#CAB-PART'), [
            'X-Boutique-Id' => $this->boutiqueB->id,
        ]);
        $resB->assertStatus(200);
        $prodInB = collect($resB->json('produits'))->firstWhere('id', $sharedProd->id);
        $this->assertEquals(27.0, $prodInB['stock_restant']);
        $this->assertEquals(12000.0, $prodInB['prix_detail']);
    }

    public function test_clients_and_factures_are_isolated_per_boutique_and_non_admin_cannot_switch(): void
    {
        $clientA = Client::create([
            'nom' => 'CLIENT BOUTIQUE A',
            'telephone' => '71000001',
            'adresse' => 'Quartier A',
        ]);
        $clientB = Client::create([
            'nom' => 'CLIENT FILIALE B',
            'telephone' => '72000002',
            'adresse' => 'Quartier B',
        ]);

        $venteA = Vente::create([
            'client_id' => $clientA->id,
            'boutique_id' => $this->boutiqueA->id,
            'montant_total' => 25000,
            'type_paiement' => 'credit',
            'montant_avance' => 5000,
            'montant_restant' => 20000,
            'date_vente' => now()->format('Y-m-d'),
        ]);

        $venteB = Vente::create([
            'client_id' => $clientB->id,
            'boutique_id' => $this->boutiqueB->id,
            'montant_total' => 45000,
            'type_paiement' => 'credit',
            'montant_avance' => 0,
            'montant_restant' => 45000,
            'date_vente' => now()->format('Y-m-d'),
        ]);

        // 1. Admin connecté sur Boutique A : ne doit voir ni CLIENT FILIALE B ni la facture B
        Sanctum::actingAs($this->admin);

        $resAllA = $this->getJson('/api/global-search?q=', ['X-Boutique-Id' => $this->boutiqueA->id]);
        $resAllA->assertStatus(200);
        $this->assertContains($clientA->id, collect($resAllA->json('clients'))->pluck('id')->all());
        $this->assertNotContains($clientB->id, collect($resAllA->json('clients'))->pluck('id')->all());
        $this->assertContains($venteA->id, collect($resAllA->json('factures'))->pluck('id')->all());
        $this->assertNotContains($venteB->id, collect($resAllA->json('factures'))->pluck('id')->all());

        $this->assertCount(
            0,
            $this->getJson('/api/global-search?q=' . urlencode('@CLIENT FILIALE B'), ['X-Boutique-Id' => $this->boutiqueA->id])->json('clients')
        );
        $this->assertCount(
            0,
            $this->getJson('/api/global-search?q=' . urlencode('!' . $venteB->id), ['X-Boutique-Id' => $this->boutiqueA->id])->json('factures')
        );

        // 2. Admin basculé sur Filiale B : voit uniquement client B et facture B
        $resAllB = $this->getJson('/api/global-search?q=', ['X-Boutique-Id' => $this->boutiqueB->id]);
        $resAllB->assertStatus(200);
        $this->assertContains($clientB->id, collect($resAllB->json('clients'))->pluck('id')->all());
        $this->assertNotContains($clientA->id, collect($resAllB->json('clients'))->pluck('id')->all());
        $this->assertContains($venteB->id, collect($resAllB->json('factures'))->pluck('id')->all());
        $this->assertNotContains($venteA->id, collect($resAllB->json('factures'))->pluck('id')->all());

        // 3. Utilisateur non-admin (vendeur rattaché à A) ne peut pas consulter Filiale B via X-Boutique-Id
        Sanctum::actingAs($this->vendeurA);
        $resVendeur = $this->getJson('/api/global-search?q=', ['X-Boutique-Id' => $this->boutiqueB->id]);
        $resVendeur->assertStatus(200);
        $this->assertContains($clientA->id, collect($resVendeur->json('clients'))->pluck('id')->all());
        $this->assertNotContains($clientB->id, collect($resVendeur->json('clients'))->pluck('id')->all());
        $this->assertContains($venteA->id, collect($resVendeur->json('factures'))->pluck('id')->all());
        $this->assertNotContains($venteB->id, collect($resVendeur->json('factures'))->pluck('id')->all());
    }
}
