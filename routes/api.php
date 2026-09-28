<?php

use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FactureController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VenteController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\AnneeController;
use App\Http\Controllers\UserStatusController;
use App\Http\Controllers\LicenceController;
use App\Http\Controllers\{CreditController,ExpenseController,TransfertController};
use App\Http\Controllers\GlobalSearchController;

// Auth Routes
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Licences & Abonnements Routes
Route::post('/licences/activer', [LicenceController::class, 'activer']);
Route::get('/licences/statut/{boutique_id?}', [LicenceController::class, 'statut']);

// User and Boutique Status Check Routes (must be authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/check-status', [UserStatusController::class, 'checkUserStatus']);
    Route::get('/boutique/check-status', [UserStatusController::class, 'checkBoutiqueStatus']);
});

// Protected Routes (Authentication + Active status checks)
Route::middleware(['auth:sanctum', 'check.user.active', 'check.boutique.active'])->group(function () {
    Route::get('/global-search', [GlobalSearchController::class, 'search']);
    Route::get('/user', [AuthController::class, 'index']);
// ... reste des routes ...
        Route::get('/user/{id}', [AuthController::class, 'showUser']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::put('/user/{id}', [AuthController::class, 'updateUser']);
        Route::post('/user/{user}/toggle-status', [AuthController::class, 'toggleUserStatus']);
        Route::delete('/user/{id}', [AuthController::class, 'deleteUser']);

        // Categories Routes
        Route::get('categories/{id}', [CategorieController::class, 'edit']);
        Route::put('categories/{id}', [CategorieController::class, 'update']);
        Route::apiResource('categories', CategorieController::class);

        // Products Routes
        Route::post('produits/import-csv', [ProduitController::class, 'importCSV']);
        Route::get('produits/trashed', [ProduitController::class, 'trashed']);
        Route::post('produits/{id}/restore', [ProduitController::class, 'restore']);
        Route::get('produits/editProd/{produit}', [ProduitController::class, 'editProd']);
        Route::get('produits/stock-inter-filiales/recherche', [ProduitController::class, 'searchStockInterFiliales']);
        Route::get('produits/{id}/disponibilites-filiales', [ProduitController::class, 'disponibilitesFiliales']);
        Route::post('produits/{produit}', [ProduitController::class, 'update']);
        Route::apiResource('produits', ProduitController::class);

        // Profile Routes
        Route::get('profil/{id}', [AuthController::class, 'getProfil']);
        Route::put('profil/{id}', [AuthController::class, 'updateProfil']);

        // Reapprovisionnement
        Route::post('reappro', [ProduitController::class, 'reapproCreate']);
        Route::get('reappro', [ProduitController::class, 'reapproIndex']);
        Route::get('rupture', [ProduitController::class, 'rupture']);
        Route::get('stock', [ProduitController::class, 'stock']);

        // Inventaires
        Route::get('inventaires', [ProduitController::class, 'inventaire']);
        Route::post('inventaires', [ProduitController::class, 'inventaireDate']);
        Route::get('inventaires/{dateDebut}/{dateFin}', [ProduitController::class, 'inventaireDate']);

        // Sales (Ventes)
        Route::post('ventes/sync-offline', [VenteController::class, 'syncOffline']);
        Route::apiResource('ventes', VenteController::class);
        Route::post('retourvente', [VenteController::class, 'modifierVente']);
        Route::get('annulevente/{id}', [VenteController::class, 'annuleVente']);

        // Dashboard / Stats
        Route::get('ventesparjour/{year}/{month}/{day}', [VenteController::class, 'nBventeDateJour']);
        Route::get('ventesparmois/{year}/{month}', [VenteController::class, 'nBventeDateMois']);
        Route::get('ventesparannee/{year}', [VenteController::class, 'nBventeDateAnnee']);
        Route::get('recentvente', [VenteController::class, 'recentVente']);
        Route::get('topvente', [VenteController::class, 'topVente']);
        Route::get('topvente/{limit}', [VenteController::class, 'topVenteByLimit']);
        Route::get('historique', [VenteController::class, 'historiqueVente']);
        Route::get('historique/{id}', [VenteController::class, 'historiqueVenteSelected']);
        // Years (Annees)
        Route::get('anneeVente', [AnneeController::class, 'index']);
        Route::post('anneeVente', [AnneeController::class, 'store']);
        Route::patch('anneeVente/{id}/toggle-status', [AnneeController::class, 'toggleStatus']);
        Route::delete('anneeVente/{id}', [AnneeController::class, 'destroy']);

        // Factures
        Route::get('facturations/{annee?}', [FactureController::class, 'index']);
        Route::get('facture/{id}', [FactureController::class, 'detailFacture']);

        // Clients & CRM (Axe 3)
        Route::get('clients/aging-balance', [ClientController::class, 'agingReport']);
        Route::get('clients/fidele', [ClientController::class, 'clientFidele']);
        Route::get('clientsfidele', [ClientController::class, 'clientFidele']);
        Route::get('clients', [ClientController::class, 'index']);
        Route::post('clients', [ClientController::class, 'store']);
        Route::get('clients/{id}', [ClientController::class, 'show'])->whereNumber('id');
        Route::put('clients/{id}', [ClientController::class, 'update'])->whereNumber('id');
        Route::delete('clients/{id}', [ClientController::class, 'destroy'])->whereNumber('id');
        Route::get('clients/{annee}', [ClientController::class, 'clientAnnee']);

        // Boutiques
        Route::get('summary', [ProduitController::class, 'summary']);
        Route::get('boutiques-reports', [BoutiqueController::class, 'allStats']);
        Route::get('boutiques/{id}/stats', [BoutiqueController::class, 'stats']);

        // CA & Stats
        Route::get('chiffre', [VenteController::class, 'chiffre']);
        Route::get('chiffre/{annee}', [VenteController::class, 'getVenteByAnnee']);
        Route::get('chiffre/{annee}/{mois}', [VenteController::class, 'getVenteByMois']);

        // Credit & Payments
        Route::get('credits', [CreditController::class, 'index']);
        Route::get('credits/debtors', [CreditController::class, 'debtors']);
        Route::post('credits/payments', [CreditController::class, 'addPayment']);
        Route::get('credits/payments/all', [CreditController::class, 'allPayments']);
        Route::get('credits/statement/{id}', [CreditController::class, 'saleStatement']);
        Route::get('credits/statement/{id}/history', [CreditController::class, 'history']);
        Route::get('proformas', [VenteController::class, 'getProformas']);
        Route::post('proformas/{id}/convert', [VenteController::class, 'convertProformaToSale']);

        // Transferts Inter-Boutiques (Axe 2)
        Route::get('transferts/boutiques', [TransfertController::class, 'getAccessibleBoutiques']);
        Route::get('transferts', [TransfertController::class, 'index']);
        Route::get('transferts/{id}', [TransfertController::class, 'show']);
        Route::post('transferts', [TransfertController::class, 'store']);

        // Valorisation du Stock & Régularisation des Écarts (Axe 2)
        Route::get('stocks/valuation', [TransfertController::class, 'stockValuation']);
        Route::post('stocks/adjustment', [TransfertController::class, 'stockAdjustment']);

        // Expenses Routes

        Route::get('/expenses/dashboard', [ExpenseController::class, 'dashboard']);
        Route::apiResource('expenses', ExpenseController::class);
        Route::middleware('role:admin,gestionnaire')->group(function () {
        });

        // PDF Generation Routes
        Route::post('/pdf/generate', [App\Http\Controllers\PdfController::class, 'generatePdf']);
        Route::get('/pdf/preview/{type}/{id}', [App\Http\Controllers\PdfController::class, 'previewPdf']);

        // Daily Reports & Caisse Routes (Axe 4)
        Route::get('/caisse/session-status', [App\Http\Controllers\DailyReportController::class, 'sessionStatus']);
        Route::post('/caisse/cloturer', [App\Http\Controllers\DailyReportController::class, 'cloturerCaisse']);
        Route::get('/caisse/ticket-z/{id}', [App\Http\Controllers\DailyReportController::class, 'ticketZ']);

        Route::prefix('rapports')->group(function () {
            Route::get('/', [App\Http\Controllers\DailyReportController::class, 'index']);
            Route::get('/session-status', [App\Http\Controllers\DailyReportController::class, 'sessionStatus']);
            Route::post('/cloturer', [App\Http\Controllers\DailyReportController::class, 'cloturerCaisse']);
            Route::post('/generer', [App\Http\Controllers\DailyReportController::class, 'generate']);
            Route::get('/{id}', [App\Http\Controllers\DailyReportController::class, 'show']);
            Route::get('/{id}/download', [App\Http\Controllers\DailyReportController::class, 'download']);
            Route::get('/{id}/ticket-z', [App\Http\Controllers\DailyReportController::class, 'ticketZ']);
            Route::post('/{id}/envoyer', [App\Http\Controllers\DailyReportController::class, 'sendEmail']);
            Route::post('/{id}/whatsapp', [App\Http\Controllers\DailyReportController::class, 'sendWhatsApp']);
        });

        Route::post('/boutiques/store-with-manager', [BoutiqueController::class, 'storeWithManager'])->middleware('role:admin');
        Route::apiResource('boutiques', BoutiqueController::class);
        
        // Natures Routes
        Route::apiResource('natures', \App\Http\Controllers\NatureController::class);

        // Comptabilité SYSCOHADA Routes (Accès sécurisé par rôles)
        Route::prefix('comptabilite')->middleware('role:admin,comptable,gestionnaire')->group(function () {
            Route::get('/comptes', [\App\Http\Controllers\ComptabiliteController::class, 'comptes']);
            Route::post('/comptes', [\App\Http\Controllers\ComptabiliteController::class, 'storeCompte'])->middleware('role:admin,comptable');
            Route::get('/journaux', [\App\Http\Controllers\ComptabiliteController::class, 'journaux']);
            Route::get('/ecritures', [\App\Http\Controllers\ComptabiliteController::class, 'ecritures']);
            Route::post('/ecritures', [\App\Http\Controllers\ComptabiliteController::class, 'storeEcriture'])->middleware('role:admin,comptable');
            Route::get('/balance', [\App\Http\Controllers\ComptabiliteController::class, 'balance']);
            Route::get('/grand-livre', [\App\Http\Controllers\ComptabiliteController::class, 'grandLivre']);
            Route::get('/compte-resultat', [\App\Http\Controllers\ComptabiliteController::class, 'compteResultat']);
            Route::get('/bilan', [\App\Http\Controllers\ComptabiliteController::class, 'bilan']);
        });

        // Sauvegarde de la Base de Données (Axe 5)
        Route::prefix('backup')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\BackupController::class, 'stats']);
            Route::get('/download', [\App\Http\Controllers\BackupController::class, 'downloadSql']);
        });

    });
