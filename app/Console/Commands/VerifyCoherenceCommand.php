<?php

namespace App\Console\Commands;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\JournalComptable;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vente;
use App\Services\ComptaService;
use Database\Seeders\SyscohadaPlanComptableSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class VerifyCoherenceCommand extends Command
{
    protected $signature = 'malcom:verify-coherence';
    protected $description = 'Exécute la suite complète de tests de cohérence Gestion <-> Comptabilité SYSCOHADA et Permissions';

    protected int $passed = 0;
    protected int $failed = 0;

    public function handle(): int
    {
        $this->info('=========================================================================');
        $this->info('  AUDIT & TESTS AUTOMATISÉS : GESTION <-> COMPTABILITÉ SYSCOHADA MALCOM');
        $this->info('=========================================================================');

        // Basculer sur une base SQLite en mémoire isolée pour ne jamais toucher à la BDD réelle
        Config::set('database.connections.testing_memory', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.default', 'testing_memory');
        DB::purge('testing_memory');
        DB::reconnect('testing_memory');

        $this->info("\n[0/9] Initialisation de la base de test en mémoire et migrations...");
        Artisan::call('migrate:fresh', ['--database' => 'testing_memory', '--force' => true]);
        app(SyscohadaPlanComptableSeeder::class)->run();

        try {
            $this->runSuite();
        } catch (\Throwable $e) {
            $this->error("Exception fatale durant les tests : " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }

        $this->newLine();
        $this->info('=========================================================================');
        if ($this->failed === 0) {
            $this->info("  RÉSULTAT FINAL : {$this->passed} ASSERTIONS RÉUSSIES / 0 ÉCHEC (100% COHÉRENT)");
            $this->info('=========================================================================');
            return 0;
        } else {
            $this->error("  RÉSULTAT FINAL : {$this->passed} RÉUSSIES / {$this->failed} ÉCHECS");
            $this->info('=========================================================================');
            return 1;
        }
    }

    protected function assertCheck(bool $condition, string $label, $actual = null, $expected = null): void
    {
        if ($condition) {
            $this->passed++;
            $this->line("  <info>✓ PASS</info> : {$label}");
        } else {
            $this->failed++;
            $detail = ($actual !== null || $expected !== null)
                ? " (Attendu: " . json_encode($expected) . " | Obtenu: " . json_encode($actual) . ")"
                : "";
            $this->error("  ✗ FAIL : {$label}{$detail}");
        }
    }

    protected function assertFloatEquals(float $expected, float $actual, string $label): void
    {
        $this->assertCheck(abs($expected - $actual) < 0.01, $label, $actual, $expected);
    }

    protected function dispatchApi(string $method, string $uri, array $data = [], ?User $user = null, ?int $boutiqueId = null): array
    {
        if ($user) {
            Auth::guard('web')->setUser($user);
            Auth::setUser($user);
        }

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        if ($boutiqueId) {
            $server['HTTP_X_BOUTIQUE_ID'] = (string) $boutiqueId;
        }

        $request = Request::create($uri, strtoupper($method), $data, [], [], $server, json_encode($data));
        if ($user) {
            $request->setUserResolver(fn() => $user);
        }

        $response = app()->handle($request);
        $status = $response->getStatusCode();
        $json = json_decode($response->getContent(), true) ?? [];

        return ['status' => $status, 'json' => $json, 'raw' => $response->getContent()];
    }

    protected function getAccountBalance(ComptaService $compta, int $boutiqueId, string $numeroPrefix): array
    {
        $balance = $compta->getBalanceGenerale($boutiqueId);
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

    protected function runSuite(): void
    {
        $compta = app(ComptaService::class);

        $boutique = Boutique::create([
            'nom' => 'Boutique Centrale MalCom',
            'adresse' => 'Bamako, ACI 2000',
            'telephone' => '70000001',
            'email' => 'centrale@malcom-lgc.com',
            'is_active' => true,
            'date_expiration_licence' => now()->addYear(),
        ]);

        \App\Models\Licence::create([
            'boutique_id' => $boutique->id,
            'cle_licence' => 'MC-TEST-2026-COHERENCE',
            'duree_jours' => 365,
            'statut' => 'active',
            'date_activation' => now(),
            'date_expiration' => now()->addYear(),
        ]);

        $admin = User::create([
            'name' => 'Admin Boutique',
            'email' => 'admin@boutique.com',
            'password' => 'password123',
            'role' => 'admin',
            'boutique_id' => $boutique->id,
            'is_active' => true,
        ]);

        $gestionnaire = User::create([
            'name' => 'Gestionnaire Boutique',
            'email' => 'gestionnaire@boutique.com',
            'password' => 'password123',
            'role' => 'gestionnaire',
            'boutique_id' => $boutique->id,
            'is_active' => true,
            'permissions' => [],
        ]);

        $comptable = User::create([
            'name' => 'Comptable Boutique',
            'email' => 'comptable@boutique.com',
            'password' => 'password123',
            'role' => 'comptable',
            'boutique_id' => $boutique->id,
            'is_active' => true,
            'permissions' => [],
        ]);

        Client::firstOrCreate(
            ['id' => 1],
            ['nom' => 'CLIENT PASSAGER', 'telephone' => '00000000']
        );

        $categorie = Categorie::create([
            'nom' => 'Électronique',
            'description' => 'Matériel informatique et téléphonie',
            'boutique_id' => $boutique->id,
        ]);

        // =========================================================================
        // 1. SEEDER SYSCOHADA, LISTE UTILISATEURS & PERMISSIONS DÉLÉGUÉES
        // =========================================================================
        $this->info("\n[1/9] Vérification Plan Comptable SYSCOHADA, Liste Utilisateurs & Permissions...");
        $this->assertCheck(CompteComptable::count() >= 50, "Plan Comptable SYSCOHADA initialisé (>= 50 comptes)", CompteComptable::count(), ">=50");
        $this->assertCheck(JournalComptable::count() === 5, "5 Journaux SYSCOHADA (VT, AC, CA, BQ, OD) initialisés", JournalComptable::count(), 5);

        $resUsers = $this->dispatchApi('GET', '/api/user', [], $admin, $boutique->id);
        $userIds = collect($resUsers['json'])->pluck('id')->toArray();
        $this->assertCheck(!in_array($admin->id, $userIds), "L'utilisateur connecté (Admin) ne se voit pas lui-même dans la liste des utilisateurs");
        $this->assertCheck(in_array($gestionnaire->id, $userIds), "Les autres utilisateurs de la boutique sont bien visibles");

        // Création produit #1 par l'admin
        $resProd1 = $this->dispatchApi('POST', '/api/produits', [
            'nom' => 'Ordinateur Portable HP',
            'categorie_id' => $categorie->id,
            'prix_achat' => 200000,
            'prix_vente' => 250000,
            'quantite' => 10,
            'reference' => 'HP-001',
        ], $admin, $boutique->id);
        $this->assertCheck($resProd1['status'] === 201, "Création de produit par l'Admin réussie", $resProd1['status'], 201);
        $prod1Id = $resProd1['json']['id'];

        // Vérifier blocage par défaut pour le Gestionnaire sans permission
        $resReapproDenied = $this->dispatchApi('POST', '/api/reappro', [
            'produits' => [['produit_id' => $prod1Id, 'produit' => 'Ordinateur Portable HP', 'quantite' => 5]]
        ], $gestionnaire, $boutique->id);
        $this->assertCheck($resReapproDenied['status'] === 403, "Gestionnaire SANS permission bloqué sur le réapprovisionnement (403)", $resReapproDenied['status'], 403);

        $resPriceDenied = $this->dispatchApi('POST', "/api/produits/{$prod1Id}", [
            'nom' => 'Ordinateur Portable HP',
            'categorie_id' => $categorie->id,
            'prix_achat' => 210000,
            'prix_vente' => 270000,
        ], $gestionnaire, $boutique->id);
        $this->assertCheck($resPriceDenied['status'] === 403, "Gestionnaire SANS permission bloqué sur le changement de prix (403)", $resPriceDenied['status'], 403);

        $resCaDenied = $this->dispatchApi('GET', '/api/chiffre', [], $gestionnaire, $boutique->id);
        $this->assertCheck($resCaDenied['status'] === 403, "Gestionnaire SANS permission bloqué sur le Chiffre d'Affaires (403)", $resCaDenied['status'], 403);

        // Comptable autorisé par défaut sur le CA
        $resCaComptable = $this->dispatchApi('GET', '/api/chiffre', [], $comptable, $boutique->id);
        $this->assertCheck($resCaComptable['status'] === 200, "Comptable autorisé par défaut à voir le Chiffre d'Affaires (200)", $resCaComptable['status'], 200);

        // Délégation des permissions par l'Admin au Gestionnaire
        $resDelegate = $this->dispatchApi('PUT', "/api/user/{$gestionnaire->id}", [
            'name' => $gestionnaire->name,
            'email' => $gestionnaire->email,
            'role' => 'gestionnaire',
            'boutique_id' => $boutique->id,
            'permissions' => [
                'can_reappro' => true,
                'can_change_prices' => true,
                'can_view_ca' => true,
                'can_view_margins' => true,
            ]
        ], $admin, $boutique->id);
        $this->assertCheck($resDelegate['status'] === 200, "Délégation des permissions par l'Admin au Gestionnaire enregistrée", $resDelegate['status'], 200);

        $gestionnaire->refresh();
        $resReapproAllowed = $this->dispatchApi('POST', '/api/reappro', [
            'produits' => [['produit_id' => $prod1Id, 'produit' => 'Ordinateur Portable HP', 'quantite' => 5, 'prix_achat' => 200000, 'prix_vente' => 250000]]
        ], $gestionnaire, $boutique->id);
        $this->assertCheck($resReapproAllowed['status'] === 201, "Gestionnaire AVEC délégation peut réapprovisionner (201)", $resReapproAllowed['status'], 201);
        $this->assertFloatEquals(1000000.0, $this->getAccountBalance($compta, $boutique->id, '601')['solde_debiteur'], "Réapprovisionnement (5 x 200 000 = 1 000 000 F) inscrit au Débit 601 Achats");

        // Nettoyer les écritures du test 1 pour tester les flux suivants de manière chirurgicale
        DB::table('lignes_ecritures')->delete();
        DB::table('ecritures_comptables')->delete();
        DB::table('inventaires')->delete();
        DB::table('stocks')->delete();
        DB::table('produits')->delete();

        // =========================================================================
        // 2. CRÉATION PRODUIT & VENTE AU COMPTANT (AVEC SURPLUS & REMISE)
        // =========================================================================
        $this->info("\n[2/9] Test Création Produit & Vente au Comptant (avec Surplus et Remise)...");
        $resProd = $this->dispatchApi('POST', '/api/produits', [
            'nom' => 'Routeur Wi-Fi 6',
            'categorie_id' => $categorie->id,
            'prix_achat' => 10000,
            'prix_vente' => 15000,
            'quantite' => 20,
            'reference' => 'RT-WIFI6',
        ], $admin, $boutique->id);
        $produitId = $resProd['json']['id'];

        // Vente #1 : 4 unités à 15 000 F + Surplus 1 000 F/u (prix vendu = 16 000 F) - Remise globale 2 000 F = 62 000 F Net
        $resVente1 = $this->dispatchApi('POST', '/api/ventes', [
            'client_nom' => 'CLIENT ALPHA',
            'client_numero' => '76001122',
            'type_paiement' => 'especes',
            'moyen_paiement' => 'especes',
            'remise' => 2000,
            'surplus' => 4000,
            'montant_total' => 62000,
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'Routeur Wi-Fi 6', 'reference' => 'RT-WIFI6'],
                    'quantite' => 4,
                    'prix' => 15000,
                    'surplus_unitaire' => 1000,
                    'prix_vendu' => 16000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], $admin, $boutique->id);
        $this->assertCheck($resVente1['status'] === 201, "Vente #1 créée avec succès", $resVente1['json'], 201);
        $vente1Id = $resVente1['json']['venteID'];

        $this->assertCheck((int)Stock::where('produit_id', $produitId)->value('quantite') === 16, "Stock déduit exactement (20 - 4 = 16)");
        $this->assertFloatEquals(62000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Compte 571 Caisse débité de 62 000 F");
        $this->assertFloatEquals(58000.0, $this->getAccountBalance($compta, $boutique->id, '701')['solde_crediteur'], "Compte 701 Ventes crédité de 58 000 F (Base hors surplus)");
        $this->assertFloatEquals(4000.0, $this->getAccountBalance($compta, $boutique->id, '7018')['solde_crediteur'], "Compte 7018 Surplus crédité de 4 000 F");

        $caStats1 = $this->dispatchApi('GET', '/api/chiffre', [], $admin, $boutique->id)['json']['annual_stats'];
        $this->assertFloatEquals(62000.0, (float)$caStats1['total_ca'], "CA Gestion = 62 000 F (égal au Total Produits Compta)");
        $this->assertFloatEquals(40000.0, (float)$caStats1['total_cout_achat'], "Coût d'achat Gestion = 40 000 F (4 x 10 000 F)");
        $this->assertFloatEquals(22000.0, (float)$caStats1['total_benefice'], "Bénéfice Gestion = 22 000 F");

        // =========================================================================
        // 3. MODIFICATION DES PRIX DU PRODUIT SANS AFFECTER LES ANCIENS CALCULS
        // =========================================================================
        $this->info("\n[3/9] Test Modification des Prix (Achat & Vente) sans altérer l'historique...");
        $resModifPrix = $this->dispatchApi('POST', "/api/produits/{$produitId}", [
            'nom' => 'Routeur Wi-Fi 6',
            'categorie_id' => $categorie->id,
            'prix_achat' => 12000, // Ancien : 10 000
            'prix_vente' => 20000, // Ancien : 15 000
        ], $admin, $boutique->id);
        $this->assertCheck($resModifPrix['status'] === 200, "Modification des prix du produit effectuée (Achat: 12 000, Vente: 20 000)");

        $caAfterPriceMod = $this->dispatchApi('GET', '/api/chiffre', [], $admin, $boutique->id)['json']['annual_stats'];
        $this->assertFloatEquals(62000.0, (float)$caAfterPriceMod['total_ca'], "CA historique intact après modification du prix de vente (62 000 F)");
        $this->assertFloatEquals(40000.0, (float)$caAfterPriceMod['total_cout_achat'], "Coût d'achat historique intact après modification du prix d'achat (40 000 F)");
        $this->assertFloatEquals(22000.0, (float)$caAfterPriceMod['total_benefice'], "Marge historique intacte (22 000 F)");

        // =========================================================================
        // 4. RETOUR PARTIEL CLIENT (APRÈS CHANGEMENT DE PRIX DU PRODUIT)
        // =========================================================================
        $this->info("\n[4/9] Test Retour Client Partiel & Régularisation Comptable...");
        // Le client retourne 1 unité sur 4 : il en garde 3 à 16 000 F = 48 000 F brut - 2 000 F remise = 46 000 F Net
        $resRetour = $this->dispatchApi('POST', '/api/retourvente', [
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
        ], $admin, $boutique->id);
        $this->assertCheck($resRetour['status'] === 200, "Retour client d'1 unité traité avec succès", $resRetour['status'], 200);
        $this->assertCheck((int)Stock::where('produit_id', $produitId)->value('quantite') === 17, "Stock réintégré (+1 => 17 en stock)");

        $caAfterReturn = $this->dispatchApi('GET', '/api/chiffre', [], $admin, $boutique->id)['json']['annual_stats'];
        $this->assertFloatEquals(46000.0, (float)$caAfterReturn['total_ca'], "CA Gestion ramené à 46 000 F après retour");
        $this->assertFloatEquals(30000.0, (float)$caAfterReturn['total_cout_achat'], "Coût d'achat calculé avec le prix d'achat HISTORIQUE de la vente (3 x 10 000 = 30 000 F)");
        $this->assertFloatEquals(16000.0, (float)$caAfterReturn['total_benefice'], "Bénéfice mis à jour à 16 000 F");

        $this->assertFloatEquals(46000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Caisse 571 ramenée à 46 000 F");
        $this->assertFloatEquals(43000.0, $this->getAccountBalance($compta, $boutique->id, '701')['solde_crediteur'], "Ventes 701 ramenées à 43 000 F");
        $this->assertFloatEquals(3000.0, $this->getAccountBalance($compta, $boutique->id, '7018')['solde_crediteur'], "Surplus 7018 ramené à 3 000 F");

        // =========================================================================
        // 5. NOUVELLE VENTE AU NOUVEAU PRIX PUIS ANNULATION TOTALE
        // =========================================================================
        $this->info("\n[5/9] Test Nouvelle Vente au Nouveau Prix puis Annulation Totale...");
        $resVente2 = $this->dispatchApi('POST', '/api/ventes', [
            'client_nom' => 'CLIENT BETA',
            'type_paiement' => 'especes',
            'moyen_paiement' => 'especes',
            'remise' => 0,
            'montant_total' => 40000,
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'Routeur Wi-Fi 6'],
                    'quantite' => 2,
                    'prix' => 20000,
                    'prix_vendu' => 20000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], $admin, $boutique->id);
        $vente2Id = $resVente2['json']['venteID'];

        $caWithVente2 = $this->dispatchApi('GET', '/api/chiffre', [], $admin, $boutique->id)['json']['annual_stats'];
        $this->assertFloatEquals(86000.0, (float)$caWithVente2['total_ca'], "CA cumulé avec Vente #2 au nouveau prix = 86 000 F");
        $this->assertFloatEquals(54000.0, (float)$caWithVente2['total_cout_achat'], "Coût d'achat cumulé (30 000 ancien + 24 000 nouveau) = 54 000 F");

        // Annulation totale de la Vente #2
        $resAnnule2 = $this->dispatchApi('GET', "/api/annulevente/{$vente2Id}", [], $admin, $boutique->id);
        $this->assertCheck($resAnnule2['status'] === 200, "Annulation totale de la Vente #2 exécutée");
        $this->assertCheck((int)Stock::where('produit_id', $produitId)->value('quantite') === 17, "Stock restauré à 17 après annulation");
        $this->assertFloatEquals(46000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Contre-passation comptable exacte : Caisse 571 revenue à 46 000 F");
        $this->assertFloatEquals(46000.0, (float)$compta->getCompteResultat($boutique->id)['total_produits'], "Total Produits Compte de Résultat revenu à 46 000 F");

        // =========================================================================
        // 6. GÉNÉRATION DE PROFORMA & CONVERSION EN VENTE
        // =========================================================================
        $this->info("\n[6/9] Test Proforma (0 impact) & Conversion de Proforma en Vente...");
        $resProforma = $this->dispatchApi('POST', '/api/ventes', [
            'client_nom' => 'ENTREPRISE SOGEA',
            'is_proforma' => true,
            'remise' => 5000,
            'montant_total' => 35000, // 2 * 20 000 - 5 000 = 35 000 F
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'Routeur Wi-Fi 6'],
                    'quantite' => 2,
                    'prix' => 20000,
                    'prix_vendu' => 20000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], $admin, $boutique->id);
        $proformaId = $resProforma['json']['venteID'];

        $this->assertCheck((int)Stock::where('produit_id', $produitId)->value('quantite') === 17, "Génération Proforma : Stock inchangé (17)");
        $this->assertFloatEquals(46000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Génération Proforma : Comptabilité inchangée (46 000 F)");

        // Conversion du Proforma en Vente espèces
        $resConvert = $this->dispatchApi('POST', "/api/proformas/{$proformaId}/convert", [
            'type_paiement' => 'especes',
            'moyen_paiement' => 'especes',
            'montant_recu' => 35000,
        ], $admin, $boutique->id);
        $this->assertCheck($resConvert['status'] === 200, "Conversion du Proforma en Vente réussie", $resConvert['status'], 200);
        $this->assertCheck((int)Stock::where('produit_id', $produitId)->value('quantite') === 15, "Stock déduit après conversion Proforma (17 - 2 = 15)");
        $this->assertFloatEquals(81000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Caisse 571 augmentée du montant net du Proforma converti (46 000 + 35 000 = 81 000 F)");

        // =========================================================================
        // 7. GESTION DES CRÉDITS (AVANCE + PAIEMENT ULTÉRIEUR)
        // =========================================================================
        $this->info("\n[7/9] Test Vente à Crédit (avec Avance) & Recouvrement Client...");
        // Vente à crédit de 3 unités à 20 000 F = 60 000 F, Avance immédiate = 20 000 F, Reste à payer = 40 000 F
        $resCredit = $this->dispatchApi('POST', '/api/ventes', [
            'client_nom' => 'CLIENT CREDIT DIALLO',
            'client_numero' => '79112233',
            'type_paiement' => 'credit',
            'moyen_paiement' => 'especes',
            'montant_avance' => 20000,
            'remise' => 0,
            'montant_total' => 60000,
            'produits' => [
                [
                    'produits' => ['id' => $produitId, 'nom' => 'Routeur Wi-Fi 6'],
                    'quantite' => 3,
                    'prix' => 20000,
                    'prix_vendu' => 20000,
                    'remise_unitaire' => 0,
                ]
            ]
        ], $admin, $boutique->id);
        $creditVenteId = $resCredit['json']['venteID'];

        $this->assertFloatEquals(40000.0, $this->getAccountBalance($compta, $boutique->id, '411')['solde_debiteur'], "Créance Client (411) = 40 000 F (60 000 - 20 000 d'avance)");
        $this->assertFloatEquals(101000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Caisse (571) = 101 000 F (81 000 + 20 000 d'avance)");

        // Paiement partiel de 25 000 F sur le crédit
        $resPayCredit = $this->dispatchApi('POST', '/api/credits/payments', [
            'vente_id' => $creditVenteId,
            'montant' => 25000,
            'date_paiement' => now()->format('Y-m-d'),
            'mode_paiement' => 'Espèces',
        ], $admin, $boutique->id);
        $this->assertCheck($resPayCredit['status'] === 201, "Paiement crédit de 25 000 F enregistré");
        $this->assertFloatEquals(15000.0, (float)Vente::find($creditVenteId)->montant_restant, "Reste à payer Gestion = 15 000 F");
        $this->assertFloatEquals(15000.0, $this->getAccountBalance($compta, $boutique->id, '411')['solde_debiteur'], "Solde Compte 411 Clients = 15 000 F (100% cohérent avec Gestion)");
        $this->assertFloatEquals(126000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Solde Compte 571 Caisse = 126 000 F (101 000 + 25 000)");

        // =========================================================================
        // 8. GESTION DES DÉPENSES (CRÉATION, MODIFICATION)
        // =========================================================================
        $this->info("\n[8/9] Test Gestion des Dépenses (Création & Modification)...");
        $resExp = $this->dispatchApi('POST', '/api/expenses', [
            'type' => 'Électricité et Eau',
            'montant' => 10000,
            'mode_paiement' => 'especes',
            'date' => now()->format('Y-m-d'),
            'description' => 'Facture EDM',
        ], $admin, $boutique->id);
        $expenseId = $resExp['json']['id'];
        $this->assertFloatEquals(10000.0, $this->getAccountBalance($compta, $boutique->id, '605')['solde_debiteur'], "Dépense enregistrée au Débit 605 (10 000 F)");
        $this->assertFloatEquals(116000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Caisse 571 diminuée à 116 000 F");

        // Modification de la dépense à 16 000 F
        $this->dispatchApi('PUT', "/api/expenses/{$expenseId}", [
            'type' => 'Électricité et Eau',
            'montant' => 16000,
            'mode_paiement' => 'especes',
            'date' => now()->format('Y-m-d'),
            'description' => 'Facture EDM rectifiée',
        ], $admin, $boutique->id);
        $this->assertFloatEquals(16000.0, $this->getAccountBalance($compta, $boutique->id, '605')['solde_debiteur'], "Dépense rectifiée au Débit 605 (16 000 F sans doublon)");
        $this->assertFloatEquals(110000.0, $this->getAccountBalance($compta, $boutique->id, '571')['solde_debiteur'], "Caisse 571 rectifiée à 110 000 F (126 000 - 16 000)");

        // =========================================================================
        // 9. CLÔTURE DE CAISSE (TICKET Z) & ÉQUILIBRE DU BILAN SYSCOHADA
        // =========================================================================
        $this->info("\n[9/9] Test Clôture de Caisse (Ticket Z) & Équilibre Général SYSCOHADA...");
        // Fond de caisse = 5 000 F + Solde net espèces du jour (110 000 F) = Solde théorique de caisse 115 000 F
        $resCloture = $this->dispatchApi('POST', '/api/caisse/cloturer', [
            'date' => now()->format('Y-m-d'),
            'fond_de_caisse' => 5000,
            'total_especes_physique' => 115000,
            'notes_cloture' => 'Clôture journalière exacte',
        ], $admin, $boutique->id);
        $this->assertCheck($resCloture['status'] === 200, "Clôture de caisse journalière validée", $resCloture['status'], 200);
        $report = $resCloture['json']['report'];
        $totauxReport = $resCloture['json']['totaux'];

        // Ventes espèces du jour = 46 000 (Vente 1 après retour) + 35 000 (Proforma converti) + 20 000 (Avance Vente crédit) = 101 000 F
        $this->assertFloatEquals(101000.0, (float)$totauxReport['especes_ventes'], "Ticket Z : Ventes espèces = 101 000 F (inclut l'avance crédit)");
        $this->assertFloatEquals(25000.0, (float)$report['total_recouvrement'], "Ticket Z : Recouvrements espèces = 25 000 F");
        $this->assertFloatEquals(115000.0, (float)$report['total_especes_theorique'], "Ticket Z : Solde théorique espèces = Fond de caisse (5 000) + Solde Caisse 571 (110 000) = 115 000 F");
        $this->assertFloatEquals(0.0, (float)$report['ecart_caisse'], "Ticket Z : Écart de caisse = 0 F");

        $balanceFinale = $compta->getBalanceGenerale($boutique->id);
        $bilanFinal = $compta->getBilan($boutique->id);
        $crFinal = $compta->getCompteResultat($boutique->id);
        $caFinal = $this->dispatchApi('GET', '/api/chiffre', [], $admin, $boutique->id)['json']['annual_stats'];

        $this->assertCheck($balanceFinale['totaux']['is_equilibree'], "Balance Générale SYSCOHADA finale équilibrée (Débit == Crédit)");
        $this->assertCheck($bilanFinal['equilibre'], "Bilan SYSCOHADA final équilibré (Total Actif == Total Passif)");
        $this->assertFloatEquals((float)$caFinal['total_ca'], (float)$crFinal['total_produits'], "COHÉRENCE PARFAITE : Chiffre d'Affaires Gestion (141 000 F) == Total Produits Compte de Résultat SYSCOHADA (141 000 F)");
    }
}
