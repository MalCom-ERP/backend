<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\CompteComptable;
use App\Models\DailyReport;
use App\Models\DetailVente;
use App\Models\EcritureComptable;
use App\Models\Expense;
use App\Models\Facture;
use App\Models\Inventaire;
use App\Models\JournalComptable;
use App\Models\PaiementCredit;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vente;
use App\Services\ComptaService;
use Database\Seeders\SyscohadaPlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GestionComptabiliteCoherenceTest extends TestCase
{
    use RefreshDatabase;

    protected Boutique $boutique;
    protected User $admin;
    protected User $gestionnaire;
    protected User $comptable;
    protected User $vendeur;
    protected Categorie $categorie;
    protected ComptaService $compta;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed SYSCOHADA Plan Comptable & Journaux
        $this->seed(SyscohadaPlanComptableSeeder::class);

        $this->boutique = Boutique::create([
            'nom' => 'Boutique Centrale MalCom',
            'adresse' => 'Bamako, ACI 2000',
            'telephone' => '70000001',
            'email' => 'centrale@malcom-lgc.com',
            'is_active' => true,
            'date_expiration_licence' => now()->addYear(),
        ]);

        \App\Models\Licence::create([
            'boutique_id' => $this->boutique->id,
            'cle_licence' => 'MC-TEST-2026-COHERENCE',
            'duree_jours' => 365,
            'statut' => 'active',
            'date_activation' => now(),
            'date_expiration' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'name' => 'Admin Boutique',
            'email' => 'admin@boutique.com',
            'password' => 'password123',
            'role' => 'admin',
            'boutique_id' => $this->boutique->id,
            'is_active' => true,
        ]);

        $this->gestionnaire = User::create([
            'name' => 'Gestionnaire Boutique',
            'email' => 'gestionnaire@boutique.com',
            'password' => 'password123',
            'role' => 'gestionnaire',
            'boutique_id' => $this->boutique->id,
            'is_active' => true,
            'permissions' => [],
        ]);

        $this->comptable = User::create([
            'name' => 'Comptable Boutique',
            'email' => 'comptable@boutique.com',
            'password' => 'password123',
            'role' => 'comptable',
            'boutique_id' => $this->boutique->id,
            'is_active' => true,
            'permissions' => [],
        ]);

        $this->vendeur = User::create([
            'name' => 'Vendeur Boutique',
            'email' => 'vendeur@boutique.com',
            'password' => 'password123',
            'role' => 'vendeur',
            'boutique_id' => $this->boutique->id,
            'is_active' => true,
            'permissions' => [],
        ]);

        Client::firstOrCreate(
            ['id' => 1],
            ['nom' => 'CLIENT PASSAGER', 'telephone' => '00000000']
        );

        $this->categorie = Categorie::create([
            'nom' => 'Électronique',
            'description' => 'Matériel informatique et téléphonie',
            'boutique_id' => $this->boutique->id,
        ]);

        $this->compta = app(ComptaService::class);
    }

    /**
     * Helper pour récupérer le solde net d'un compte dans la balance générale
     * (Débit - Crédit pour Actif/Charges, Crédit - Débit pour Passif/Produits)
     */
    protected function getAccountBalance(string $numeroPrefix): array
    {
        $balance = $this->compta->getBalanceGenerale($this->boutique->id);
        foreach ($balance['comptes'] as $c) {
            if ($c['numero'] === $numeroPrefix) {
                return $c;
            }
        }
        foreach ($balance['comptes'] as $c) {
            if (str_starts_with($c['numero'], $numeroPrefix)) {
                return $c;
            }
        }
        return [
            'total_debit' => 0.0,
            'total_credit' => 0.0,
            'solde_debiteur' => 0.0,
            'solde_crediteur' => 0.0,
        ];
    }

    /**
     * TEST 1 : Vérification du Seeder SYSCOHADA, de l'exclusion de l'utilisateur connecté
     * dans la liste des utilisateurs, et des permissions déléguées (Réappro, Prix, CA, Marges).
     */
    public function test_seeder_user_list_and_delegable_permissions(): void
    {
        // 1. Vérifier que le Plan Comptable SYSCOHADA est bien présent
        $this->assertGreaterThanOrEqual(50, CompteComptable::count());
        $this->assertEquals(5, JournalComptable::count());

        // 2. L'utilisateur connecté ne doit PAS se voir lui-même dans GET /api/user
        Sanctum::actingAs($this->admin);
        $resUsers = $this->getJson('/api/user', ['X-Boutique-Id' => $this->boutique->id]);
        $resUsers->assertStatus(200);
        $userIds = collect($resUsers->json())->pluck('id')->toArray();
        $this->assertNotContains($this->admin->id, $userIds, "L'utilisateur connecté ne doit pas apparaître dans la liste des utilisateurs.");
        $this->assertContains($this->gestionnaire->id, $userIds);

        // 3. Création d'un produit par l'admin
        $resProd = $this->postJson('/api/produits', [
            'nom' => 'Ordinateur Portable HP',
            'categorie_id' => $this->categorie->id,
            'prix_achat' => 200000,
            'prix_vente' => 250000,
            'quantite' => 10,
            'reference' => 'HP-001',
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resProd->assertStatus(201);
        $produitId = $resProd->json('id');

        // 4. Un gestionnaire SANS permission ne peut PAS faire de réapprovisionnement, changer les prix, ni voir le CA
        Sanctum::actingAs($this->gestionnaire);

        $resReapproForbidden = $this->postJson('/api/reappro', [
            'produits' => [
                ['produit_id' => $produitId, 'produit' => 'Ordinateur Portable HP', 'quantite' => 5, 'prix_achat' => 200000, 'prix_vente' => 250000]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resReapproForbidden->assertStatus(403);

        $resPriceForbidden = $this->postJson("/api/produits/{$produitId}", [
            'nom' => 'Ordinateur Portable HP',
            'categorie_id' => $this->categorie->id,
            'prix_achat' => 210000,
            'prix_vente' => 270000,
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resPriceForbidden->assertStatus(403);

        $resCaForbidden = $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id]);
        $resCaForbidden->assertStatus(403);

        // 5. Le comptable PEUT voir le Chiffre d'Affaires par défaut, mais ne peut pas réapprovisionner
        Sanctum::actingAs($this->comptable);
        $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->assertStatus(200);
        $this->postJson('/api/reappro', [
            'produits' => [
                ['produit_id' => $produitId, 'produit' => 'Ordinateur Portable HP', 'quantite' => 5]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id])->assertStatus(403);

        // 6. L'Admin délègue les permissions au gestionnaire (can_reappro, can_change_prices, can_view_ca, can_view_margins)
        Sanctum::actingAs($this->admin);
        $resDelegate = $this->putJson("/api/user/{$this->gestionnaire->id}", [
            'name' => $this->gestionnaire->name,
            'email' => $this->gestionnaire->email,
            'role' => 'gestionnaire',
            'boutique_id' => $this->boutique->id,
            'permissions' => [
                'can_reappro' => true,
                'can_change_prices' => true,
                'can_view_ca' => true,
                'can_view_margins' => true,
            ]
        ]);
        $resDelegate->assertStatus(200);

        // 7. Le gestionnaire avec délégation PEUT maintenant réapprovisionner, modifier les prix et voir le CA
        $this->gestionnaire->refresh();
        Sanctum::actingAs($this->gestionnaire);

        $this->postJson('/api/reappro', [
            'produits' => [
                ['produit_id' => $produitId, 'produit' => 'Ordinateur Portable HP', 'quantite' => 5, 'prix_achat' => 200000, 'prix_vente' => 250000]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id])->assertStatus(201);

        $this->postJson("/api/produits/{$produitId}", [
            'nom' => 'Ordinateur Portable HP Pro',
            'categorie_id' => $this->categorie->id,
            'prix_achat' => 200000,
            'prix_vente' => 260000,
        ], ['X-Boutique-Id' => $this->boutique->id])->assertStatus(200);

        $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->assertStatus(200);

        // Vérifier que le réapprovisionnement (5 * 200 000 = 1 000 000) est bien comptabilisé en 601 / 571
        $c601 = $this->getAccountBalance('601');
        $this->assertEquals(1000000.0, $c601['solde_debiteur']);
    }

    /**
     * TEST 2 : Cycle complet Vente au comptant (avec Surplus & Remise), Changement de prix du produit
     * SANS impacter l'historique comptable ni les marges passées, Retour partiel client, et Annulation totale.
     */
    public function test_sales_price_modification_isolation_returns_and_cancellation_coherence(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Créer un produit : Stock initial = 20, Prix Achat = 10 000 F, Prix Vente = 15 000 F
        $resProd = $this->postJson('/api/produits', [
            'nom' => ' routeur Wi-Fi 6',
            'categorie_id' => $this->categorie->id,
            'prix_achat' => 10000,
            'prix_vente' => 15000,
            'quantite' => 20,
            'reference' => 'RT-WIFI6',
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resProd->assertStatus(201);
        $produitId = $resProd->json('id');

        // 2. Effectuer une Vente #1 : 4 unités vendues avec un surplus de 1 000 F/u (donc prix_vendu = 16 000 F)
        // et une remise globale de 2 000 F.
        // Montant brut = 4 * 16 000 = 64 000 F. Remise = 2 000 F. Montant Net = 62 000 F.
        // Surplus total = 4 * 1 000 = 4 000 F. Base Vente (701) = 62 000 - 4 000 = 58 000 F.
        // Coût d'achat historique = 4 * 10 000 = 40 000 F.
        // Bénéfice net sur cette vente = 62 000 - 40 000 = 22 000 F.
        $resVente1 = $this->postJson('/api/ventes', [
            'client_nom' => 'CLIENT ALPHA',
            'client_numero' => '76001122',
            'type_paiement' => 'especes',
            'moyen_paiement' => 'especes',
            'remise' => 2000,
            'surplus' => 4000,
            'montant_total' => 62000,
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'routeur Wi-Fi 6', 'reference' => 'RT-WIFI6'],
                    'quantite' => 4,
                    'prix' => 15000,
                    'surplus_unitaire' => 1000,
                    'prix_vendu' => 16000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resVente1->assertStatus(201);
        $vente1Id = $resVente1->json('venteID');

        // Vérifier le stock (20 - 4 = 16)
        $this->assertEquals(16, Stock::where('produit_id', $produitId)->value('quantite'));

        // Vérifier la Comptabilité SYSCOHADA après Vente #1
        $balance1 = $this->compta->getBalanceGenerale($this->boutique->id);
        $this->assertTrue($balance1['totaux']['is_equilibree'], 'La balance générale doit être équilibrée');
        $this->assertEquals(62000.0, $this->getAccountBalance('571')['solde_debiteur']);
        $this->assertEquals(58000.0, $this->getAccountBalance('701')['solde_crediteur']);
        $this->assertEquals(4000.0, $this->getAccountBalance('7018')['solde_crediteur']);

        $bilan1 = $this->compta->getBilan($this->boutique->id);
        $this->assertTrue($bilan1['equilibre'], 'Le Bilan Actif/Passif doit être en équilibre parfait');

        // Vérifier le Chiffre d'Affaires et la Marge dans la Gestion (/api/chiffre)
        $ca1 = $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->json('annual_stats');
        $this->assertEquals(62000.0, (float)$ca1['total_ca']);
        $this->assertEquals(40000.0, (float)$ca1['total_cout_achat']);
        $this->assertEquals(22000.0, (float)$ca1['total_benefice']);

        // 3. MODIFICATION DES PRIX DU PRODUIT :
        // On change le prix d'achat à 12 000 F (au lieu de 10 000 F) et le prix de vente à 20 000 F (au lieu de 15 000 F)
        $resUpdatePrice = $this->postJson("/api/produits/{$produitId}", [
            'nom' => 'routeur Wi-Fi 6',
            'categorie_id' => $this->categorie->id,
            'prix_achat' => 12000,
            'prix_vente' => 20000,
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resUpdatePrice->assertStatus(200);

        // VÉRIFICATION CRITIQUE : Les anciens calculs de CA, coût d'achat, marge et comptabilité NE DOIVENT PAS CHANGER !
        $caAfterPriceChange = $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->json('annual_stats');
        $this->assertEquals(62000.0, (float)$caAfterPriceChange['total_ca'], "Le CA historique ne doit pas bouger après modification du prix du produit");
        $this->assertEquals(40000.0, (float)$caAfterPriceChange['total_cout_achat'], "Le coût d'achat historique des ventes passées ne doit pas être affecté par le nouveau prix d'achat");
        $this->assertEquals(22000.0, (float)$caAfterPriceChange['total_benefice'], "Le bénéfice historique ne doit pas changer");

        $this->assertEquals(62000.0, $this->getAccountBalance('571')['solde_debiteur']);
        $this->assertEquals(58000.0, $this->getAccountBalance('701')['solde_crediteur']);

        // 4. RETOUR PARTIEL CLIENT sur la Vente #1 (alors que le produit a maintenant un nouveau prix en stock !)
        // Le client retourne 1 unité sur les 4 achetées : il garde 3 unités à 16 000 F = 48 000 F brut, remise = 2 000 F => Nouveau Net = 46 000 F.
        // Nouveau Surplus = 3 * 1 000 = 3 000 F.
        // Le coût d'achat de l'unité retournée DOIT être l'ancien coût d'achat de la Vente #1 (10 000 F), PAS le nouveau (12 000 F) !
        // Donc le coût d'achat restant pour les 3 unités gardées = 3 * 10 000 = 30 000 F, et le bénéfice restant = 46 000 - 30 000 = 16 000 F.
        $resRetour = $this->postJson('/api/retourvente', [
            'vente_id' => $vente1Id,
            'remise' => 2000,
            'produits' => [
                [
                    'produit_id' => $produitId,
                    'quantite' => 3,
                    'prix_unitaire' => 16000,
                    'remise' => 0,
                ]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resRetour->assertStatus(200);

        // Stock doit être remonté à 16 + 1 = 17
        $this->assertEquals(17, Stock::where('produit_id', $produitId)->value('quantite'));

        // Vérifier la cohérence Gestion (CA & Marge après retour partiel)
        $caAfterReturn = $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->json('annual_stats');
        $this->assertEquals(46000.0, (float)$caAfterReturn['total_ca']);
        $this->assertEquals(30000.0, (float)$caAfterReturn['total_cout_achat'], "Le retour doit utiliser le prix d'achat historique de la vente (10 000 F)");
        $this->assertEquals(16000.0, (float)$caAfterReturn['total_benefice']);

        // Vérifier la cohérence Comptabilité SYSCOHADA après retour partiel :
        // Caisse (571) = 62 000 - 16 000 = 46 000 F
        // Ventes (7011) = 58 000 - 15 000 = 43 000 F
        // Surplus (7018) = 4 000 - 1 000 = 3 000 F
        // Total Produits (Compte de résultat) = 43 000 + 3 000 = 46 000 F == CA Gestion !
        $this->assertEquals(46000.0, $this->getAccountBalance('571')['solde_debiteur']);
        $this->assertEquals(43000.0, $this->getAccountBalance('701')['solde_crediteur']);
        $this->assertEquals(3000.0, $this->getAccountBalance('7018')['solde_crediteur']);

        $crAfterReturn = $this->compta->getCompteResultat($this->boutique->id);
        $this->assertEquals(46000.0, (float)$crAfterReturn['total_produits']);

        // 5. Effectuer une Nouvelle Vente #2 au NOUVEAU PRIX (2 unités à 20 000 F = 40 000 F, coût d'achat = 2 * 12 000 = 24 000 F)
        $resVente2 = $this->postJson('/api/ventes', [
            'client_nom' => 'CLIENT BETA',
            'type_paiement' => 'especes',
            'moyen_paiement' => 'especes',
            'remise' => 0,
            'montant_total' => 40000,
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'routeur Wi-Fi 6'],
                    'quantite' => 2,
                    'prix' => 20000,
                    'prix_vendu' => 20000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resVente2->assertStatus(201);
        $vente2Id = $resVente2->json('venteID');

        // Stock = 17 - 2 = 15
        $this->assertEquals(15, Stock::where('produit_id', $produitId)->value('quantite'));

        // Total CA = 46 000 + 40 000 = 86 000 F
        // Total Coût d'achat = 30 000 + 24 000 = 54 000 F
        // Total Bénéfice = 86 000 - 54 000 = 32 000 F
        $caAfterVente2 = $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->json('annual_stats');
        $this->assertEquals(86000.0, (float)$caAfterVente2['total_ca']);
        $this->assertEquals(54000.0, (float)$caAfterVente2['total_cout_achat']);
        $this->assertEquals(32000.0, (float)$caAfterVente2['total_benefice']);

        // Comptabilité : Produits Classe 7 = 86 000 F, Caisse 571 = 86 000 F
        $this->assertEquals(86000.0, $this->getAccountBalance('571')['solde_debiteur']);
        $this->assertEquals(86000.0, (float)$this->compta->getCompteResultat($this->boutique->id)['total_produits']);

        // 6. ANNULATION TOTALE de la Vente #2 (GET /api/annulevente/{id})
        $resAnnule2 = $this->getJson("/api/annulevente/{$vente2Id}", ['X-Boutique-Id' => $this->boutique->id]);
        $resAnnule2->assertStatus(200);

        // Le stock revient à 17, le CA et la Comptabilité reviennent exactement à l'état d'avant la Vente #2 (46 000 F)
        $this->assertEquals(17, Stock::where('produit_id', $produitId)->value('quantite'));
        $caAfterCancel2 = $this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->json('annual_stats');
        $this->assertEquals(46000.0, (float)$caAfterCancel2['total_ca']);
        $this->assertEquals(30000.0, (float)$caAfterCancel2['total_cout_achat']);
        $this->assertEquals(46000.0, $this->getAccountBalance('571')['solde_debiteur']);
        $this->assertEquals(46000.0, (float)$this->compta->getCompteResultat($this->boutique->id)['total_produits']);
    }

    /**
     * TEST 3 : Proforma (aucun impact stock ni compta) -> Conversion Proforma en Vente (impact stock, facture & compta)
     * -> Gestion des Crédits (Avance + Paiements échelonnés) -> Gestion des Dépenses -> Clôture de Caisse (Ticket Z).
     */
    public function test_proforma_conversion_credits_expenses_and_cash_register_closing_coherence(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Créer un produit : Stock = 50, Prix Achat = 30 000 F, Prix Vente = 50 000 F
        $resProd = $this->postJson('/api/produits', [
            'nom' => 'Imprimante Laser Canon',
            'categorie_id' => $this->categorie->id,
            'prix_achat' => 30000,
            'prix_vente' => 50000,
            'quantite' => 50,
            'reference' => 'IMP-CANON',
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $produitId = $resProd->json('id');

        // 2. Générer une Facture PROFORMA pour 3 imprimantes (3 * 50 000 = 150 000 F, remise = 10 000 F => Net = 140 000 F)
        $resProforma = $this->postJson('/api/ventes', [
            'client_nom' => 'ENTREPRISE SOGEA',
            'client_numero' => '75009988',
            'is_proforma' => true,
            'remise' => 10000,
            'montant_total' => 140000,
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'Imprimante Laser Canon'],
                    'quantite' => 3,
                    'prix' => 50000,
                    'prix_vendu' => 50000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resProforma->assertStatus(201);
        $proformaId = $resProforma->json('venteID');

        // Vérifier que le Proforma N'A AUCUN IMPACT sur le stock, ni sur le CA, ni sur la Comptabilité
        $this->assertEquals(50, Stock::where('produit_id', $produitId)->value('quantite'));
        $this->assertEquals(0, EcritureComptable::where('boutique_id', $this->boutique->id)->count());
        $this->assertEquals(0.0, (float)$this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->json('annual_stats.total_ca'));

        // 3. Convertir le Proforma en Vente au comptant (Espèces)
        $resConvert = $this->postJson("/api/proformas/{$proformaId}/convert", [
            'type_paiement' => 'especes',
            'moyen_paiement' => 'especes',
            'montant_recu' => 140000,
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resConvert->assertStatus(200);

        // Maintenant : Stock = 50 - 3 = 47, CA = 140 000 F, Caisse 571 = 140 000 F, Ventes 7011 = 140 000 F
        $this->assertEquals(47, Stock::where('produit_id', $produitId)->value('quantite'));
        $this->assertEquals(140000.0, (float)$this->getJson('/api/chiffre', ['X-Boutique-Id' => $this->boutique->id])->json('annual_stats.total_ca'));
        $this->assertEquals(140000.0, $this->getAccountBalance('571')['solde_debiteur']);
        $this->assertEquals(140000.0, $this->getAccountBalance('701')['solde_crediteur']);

        // 4. Vente à CRÉDIT : 2 imprimantes (2 * 50 000 = 100 000 F) avec une avance immédiate de 30 000 F en espèces
        // Reste à payer = 70 000 F
        $resCreditSale = $this->postJson('/api/ventes', [
            'client_nom' => 'CLIENT CREDIT DIALLO',
            'client_numero' => '79112233',
            'type_paiement' => 'credit',
            'moyen_paiement' => 'especes',
            'montant_avance' => 30000,
            'remise' => 0,
            'montant_total' => 100000,
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'Imprimante Laser Canon'],
                    'quantite' => 2,
                    'prix' => 50000,
                    'prix_vendu' => 50000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resCreditSale->assertStatus(201);
        $creditVenteId = $resCreditSale->json('venteID');

        // Vérifier Comptabilité après vente à crédit + avance :
        // Compte 411 (Clients) : Débit 100 000, Crédit 30 000 => Solde Débiteur = 70 000 F (exactement égal à montant_restant !)
        // Compte 571 (Caisse) : 140 000 + 30 000 = 170 000 F
        // Compte 7011 (Ventes) : 140 000 + 100 000 = 240 000 F
        $this->assertEquals(70000.0, $this->getAccountBalance('411')['solde_debiteur']);
        $this->assertEquals(170000.0, $this->getAccountBalance('571')['solde_debiteur']);
        $this->assertEquals(240000.0, $this->getAccountBalance('701')['solde_crediteur']);

        // 5. Règlement partiel de crédit : Le client verse 40 000 F en espèces
        $resPayCredit = $this->postJson('/api/credits/payments', [
            'vente_id' => $creditVenteId,
            'montant' => 40000,
            'date_paiement' => now()->format('Y-m-d'),
            'mode_paiement' => 'Espèces',
            'notes' => 'Premier versement',
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resPayCredit->assertStatus(201);

        // Reste à payer sur la vente = 70 000 - 40 000 = 30 000 F
        $this->assertEquals(30000.0, (float)Vente::find($creditVenteId)->montant_restant);
        // Compte 411 (Clients) Solde Débiteur = 30 000 F !
        $this->assertEquals(30000.0, $this->getAccountBalance('411')['solde_debiteur']);
        // Compte 571 (Caisse) Solde Débiteur = 170 000 + 40 000 = 210 000 F !
        $this->assertEquals(210000.0, $this->getAccountBalance('571')['solde_debiteur']);

        // 6. Gestion des Dépenses : Création d'une dépense (Transport 15 000 F), puis Modification à 20 000 F
        $resExp = $this->postJson('/api/expenses', [
            'type' => 'Transport et livraison',
            'montant' => 15000,
            'mode_paiement' => 'especes',
            'date' => now()->format('Y-m-d'),
            'description' => 'Livraison client Sogea',
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resExp->assertStatus(201);
        $expenseId = $resExp->json('id');

        $this->assertEquals(15000.0, $this->getAccountBalance('612')['solde_debiteur']);
        $this->assertEquals(195000.0, $this->getAccountBalance('571')['solde_debiteur']);

        // Modifier la dépense à 20 000 F
        $resExpUpdate = $this->putJson("/api/expenses/{$expenseId}", [
            'type' => 'Transport et livraison',
            'montant' => 20000,
            'mode_paiement' => 'especes',
            'date' => now()->format('Y-m-d'),
            'description' => 'Livraison client Sogea (ajusté)',
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resExpUpdate->assertStatus(200);

        // Vérifier que le compte 612 est bien à 20 000 F et la Caisse 571 à 210 000 - 20 000 = 190 000 F
        $this->assertEquals(20000.0, $this->getAccountBalance('612')['solde_debiteur']);
        $this->assertEquals(190000.0, $this->getAccountBalance('571')['solde_debiteur']);

        // Compte de Résultat : Produits (240 000) - Charges (20 000) = Résultat Net (220 000 F)
        $cr = $this->compta->getCompteResultat($this->boutique->id);
        $this->assertEquals(240000.0, (float)$cr['total_produits']);
        $this->assertEquals(20000.0, (float)$cr['total_charges']);
        $this->assertEquals(220000.0, (float)$cr['resultat_net']);

        // Bilan : Actif = Caisse (190 000) + Clients 411 (30 000) = 220 000 F == Passif (Résultat Net 220 000 F)
        $bilan = $this->compta->getBilan($this->boutique->id);
        $this->assertTrue($bilan['equilibre']);
        $this->assertEquals(220000.0, (float)$bilan['actif']['total']);
        $this->assertEquals(220000.0, (float)$bilan['passif']['total']);

        // 7. CLÔTURE DE CAISSE (Ticket Z) avec Fond de caisse = 10 000 F
        // Mouvements espèces du jour :
        // - Ventes espèces (Proforma converti : 140 000 + Avance crédit : 30 000) = 170 000 F
        // - Recouvrements espèces = 40 000 F
        // - Dépenses espèces = 20 000 F
        // Flux net espèces du jour = 170 000 + 40 000 - 20 000 = 190 000 F (exactement le solde du compte 571 Caisse !)
        // Avec Fond de caisse initial de 10 000 F => Solde théorique espèces = 200 000 F.
        $resCloture = $this->postJson('/api/caisse/cloturer', [
            'date' => now()->format('Y-m-d'),
            'fond_de_caisse' => 10000,
            'total_especes_physique' => 200000,
            'notes_cloture' => 'Clôture journalière conforme',
        ], ['X-Boutique-Id' => $this->boutique->id]);
        $resCloture->assertStatus(200);

        $report = $resCloture->json('report');
        $totauxReport = $resCloture->json('totaux');
        $this->assertEquals(170000.0, (float)$totauxReport['especes_ventes'], "Les ventes espèces doivent inclure l'avance espèces sur vente à crédit");
        $this->assertEquals(40000.0, (float)$report['total_recouvrement']);
        $this->assertEquals(200000.0, (float)$report['total_especes_theorique'], "Le solde théorique de caisse doit correspondre au Fond de caisse + Solde Caisse 571");
        $this->assertEquals(0.0, (float)$report['ecart_caisse']);
    }
}
