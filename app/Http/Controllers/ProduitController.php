<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\Inventaire;
use App\Models\inventaires;
use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProduitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // la liste des produits avec a la fois les categories et les stocks lorsque la quantite est superieur a 0:

        $boutique_id = $this->getBoutiqueId();
        $produits = Produit::with(['categorie', 'stock' => function ($query) use ($boutique_id) {
            $query->where('boutique_id', $boutique_id);
        }])
            ->whereHas('stock', function ($query) use ($boutique_id) {
                $query->where('boutique_id', $boutique_id);
            })
            ->orderBy('id', 'desc')->get();
        return response()->json($produits, 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function reapproIndex()
    {
        // Pour la liste des produits a reapprovisionner:
        $boutique_id = $this->getBoutiqueId();
        $produits = Produit::with(['categorie', 'stock' => function ($query) use ($boutique_id) {
            $query->where('boutique_id', $boutique_id);
        }])
            ->whereHas('stock', function ($query) use ($boutique_id) {
                $query->where('boutique_id', $boutique_id);
            })->get();

        // Sort by quantity manually if needed or via query
        $produits = $produits->sortBy(fn($p) => $p->stock ? $p->stock->quantite : 0)->values();

        return response()->json($produits, 200);
    }

    public function reapproCreate(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->hasPermission('can_reappro')) {
            return response()->json([
                'error' => "Accès refusé. Seul l'administrateur de la boutique (ou un utilisateur disposant d'une autorisation déléguée) peut effectuer un réapprovisionnement."
            ], 403);
        }

        // Pour le reaprovisionnement des produits:
        $request->validate([
            'produits' => 'Array'
        ]);

        $boutique_id = $this->getBoutiqueId();
        $reapproProcessed = [];

        foreach ($request->produits as $produit) {
            $produitId = $produit['produit_id'];
            $quantite = isset($produit['quantite']) ? floatval($produit['quantite']) : 0;
            if ($quantite <= 0) {
                continue;
            }

            $prod = $produit['produit'] ?? '';
            $produitModel = Produit::find($produitId);

            // Pour la mise a jour dans la table stock :
            $stock = Stock::where('produit_id', $produitId)->where('boutique_id', $boutique_id)->first();

            $prix_achat = (isset($produit['prix_achat']) && floatval($produit['prix_achat']) > 0)
                ? floatval($produit['prix_achat'])
                : ($stock ? floatval($stock->prix_achat) : 0);

            $prix_vente = (isset($produit['prix_vente']) && floatval($produit['prix_vente']) > 0)
                ? floatval($produit['prix_vente'])
                : ($stock ? floatval($stock->prix_vente) : 0);

            // Si les prix sont différents du stock actuel, vérifier la permission de changement de prix
            if ($stock && !$user->hasPermission('can_change_prices')) {
                $prix_achat = (float) $stock->prix_achat;
                $prix_vente = (float) $stock->prix_vente;
            }

            $condSuffix = '';
            if ($produitModel && (int) ($produitModel->quantite_conditionnement ?? 1) > 1 && !empty($produitModel->unite_conditionnement)) {
                $qCond = (int) $produitModel->quantite_conditionnement;
                $nbColis = intdiv((int) $quantite, $qCond);
                $resteUnites = (int) $quantite % $qCond;
                if ($nbColis > 0) {
                    $condSuffix = " (soit {$nbColis} {$produitModel->unite_conditionnement}"
                        . ($resteUnites > 0 ? " + {$resteUnites} " . ($produitModel->unite_base ?: 'Unité') : '')
                        . ')';
                }
            }

            // L'enregistrement dans la table Inventaire :
            Inventaire::create([
                'produit_id' => $produitId,
                'boutique_id' => $boutique_id,
                'user_id' => $user->id,
                'quantite' => $quantite,
                'type' => 'ajout',
                'prix_achat' => $prix_achat,
                'prix_vente' => $prix_vente,
                'remise' => 0,
                'description' => 'Reapprovisionnement du produit ' . $prod . $condSuffix,
                'date' => now()->format('Y-m-d')
            ]);

            $lotReappro = array_key_exists('numero_lot', $produit)
                ? (filled($produit['numero_lot']) ? trim((string) $produit['numero_lot']) : null)
                : $stock?->numero_lot;
            $peremptionReappro = array_key_exists('date_peremption', $produit)
                ? (filled($produit['date_peremption']) ? $produit['date_peremption'] : null)
                : $stock?->date_peremption;

            if ($produitModel && (array_key_exists('numero_lot', $produit) || array_key_exists('date_peremption', $produit))) {
                $produitModel->update([
                    'numero_lot' => $lotReappro ?? $produitModel->numero_lot,
                    'date_peremption' => $peremptionReappro ?? $produitModel->date_peremption,
                ]);
            }

            if ($stock) {
                $stock->update([
                    'quantite' => $stock->quantite + $quantite,
                    'date_reapprovisionnement' => now()->format('Y-m-d'),
                    'prix_achat' => $prix_achat,
                    'prix_vente' => $prix_vente,
                    'numero_lot' => $lotReappro,
                    'date_peremption' => $peremptionReappro,
                    'description' => 'Reapprovisionnement de ' . $quantite . ' produits'
                ]);
            } else {
                Stock::create([
                    'produit_id' => $produitId,
                    'boutique_id' => $boutique_id,
                    'quantite' => $quantite,
                    'date_reapprovisionnement' => now()->format('Y-m-d'),
                    'prix_achat' => $prix_achat,
                    'prix_vente' => $prix_vente,
                    'numero_lot' => $lotReappro,
                    'date_peremption' => $peremptionReappro,
                    'description' => 'Initialisation stock via réappro'
                ]);
            }

            $reapproProcessed[] = [
                'produit_id' => $produitId,
                'quantite' => $quantite,
                'prix_achat' => $prix_achat,
                'prix_vente' => $prix_vente,
            ];
        }

        // SYSCOHADA & Module 3 : si un fournisseur, un paiement à crédit/mixte ou des frais d'approche sont fournis,
        // enregistrer la CommandeAchat reçue + dette fournisseur (401) + frais d'approche (611)
        $hasFournisseurData = $request->filled('fournisseur_id')
            || in_array($request->input('type_paiement'), ['credit', 'mixte'])
            || (float) $request->input('frais_transport', 0) > 0
            || (float) $request->input('frais_douane', 0) > 0
            || (float) $request->input('frais_manutention', 0) > 0;

        if ($hasFournisseurData && count($reapproProcessed) > 0) {
            $fraisTransport = max(0, round((float) $request->input('frais_transport', 0), 2));
            $fraisDouane = max(0, round((float) $request->input('frais_douane', 0), 2));
            $fraisManutention = max(0, round((float) $request->input('frais_manutention', 0), 2));
            $remise = max(0, round((float) $request->input('remise', 0), 2));
            $typePaiement = in_array($request->input('type_paiement'), ['comptant', 'credit', 'mixte'])
                ? $request->input('type_paiement')
                : 'comptant';
            $moyenPaiement = $request->input('moyen_paiement') ?: ($typePaiement === 'credit' ? 'credit' : 'especes');

            $sousTotalHt = 0;
            $totalQte = 0;
            foreach ($reapproProcessed as $rp) {
                $sousTotalHt += ((float) $rp['quantite'] * (float) $rp['prix_achat']);
                $totalQte += (float) $rp['quantite'];
            }
            $totalFrais = round($fraisTransport + $fraisDouane + $fraisManutention, 2);
            $montantTotal = max(0, round($sousTotalHt + $totalFrais - $remise, 2));

            if ($typePaiement === 'comptant') {
                $montantPaye = $montantTotal;
                $montantRestant = 0;
            } elseif ($typePaiement === 'credit') {
                $montantPaye = 0;
                $montantRestant = $montantTotal;
            } else {
                $montantPaye = min($montantTotal, max(0, round((float) $request->input('montant_paye', 0), 2)));
                $montantRestant = max(0, round($montantTotal - $montantPaye, 2));
            }

            $dateCommande = now()->format('Y-m-d');
            $fournisseurId = $request->input('fournisseur_id') ?: null;
            $dateEcheance = $request->input('date_echeance');
            if ($montantRestant > 0 && empty($dateEcheance)) {
                $fournisseur = $fournisseurId ? \App\Models\Fournisseur::find($fournisseurId) : null;
                $delai = $fournisseur ? (int) $fournisseur->delai_paiement_jours : 30;
                $dateEcheance = now()->addDays(max(0, $delai))->format('Y-m-d');
            }

            do {
                $refAchat = 'ACH-' . date('Ymd') . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4));
            } while (\App\Models\CommandeAchat::where('reference', $refAchat)->exists());

            $achat = \App\Models\CommandeAchat::create([
                'reference' => $refAchat,
                'boutique_id' => $boutique_id,
                'fournisseur_id' => $fournisseurId,
                'user_id' => $user->id,
                'statut' => 'recu',
                'type_paiement' => $typePaiement,
                'moyen_paiement' => $moyenPaiement,
                'sous_total_ht' => round($sousTotalHt, 2),
                'frais_transport' => $fraisTransport,
                'frais_douane' => $fraisDouane,
                'frais_manutention' => $fraisManutention,
                'remise' => $remise,
                'montant_total' => $montantTotal,
                'montant_paye' => $montantPaye,
                'montant_restant' => $montantRestant,
                'date_commande' => $dateCommande,
                'date_echeance' => $dateEcheance,
                'date_reception' => now(),
                'numero_facture_fournisseur' => $request->input('numero_facture_fournisseur'),
                'note' => $request->input('note') ?: 'Créé via réapprovisionnement rapide',
            ]);

            foreach ($reapproProcessed as $rp) {
                $qte = (int) $rp['quantite'];
                $pa = (float) $rp['prix_achat'];
                $ligneHt = round($qte * $pa, 2);
                $fraisUnitaire = 0;
                if ($totalFrais > 0 && $qte > 0) {
                    $partFrais = $sousTotalHt > 0
                        ? $totalFrais * ($ligneHt / $sousTotalHt)
                        : ($totalQte > 0 ? $totalFrais * ($qte / $totalQte) : 0);
                    $fraisUnitaire = round($partFrais / $qte, 2);
                }
                $coutRevient = round($pa + $fraisUnitaire, 2);

                \App\Models\CommandeAchatDetail::create([
                    'commande_achat_id' => $achat->id,
                    'produit_id' => $rp['produit_id'],
                    'unite_achat' => 'Unité',
                    'quantite_conditionnement' => 1,
                    'quantite_commandee' => $qte,
                    'quantite_recue' => $qte,
                    'prix_achat_unitaire' => $pa,
                    'frais_approche_unitaire' => $fraisUnitaire,
                    'cout_revient_unitaire' => $coutRevient,
                    'montant_ligne' => $ligneHt,
                ]);

                if ($fraisUnitaire > 0 && $coutRevient > 0) {
                    Stock::where('produit_id', $rp['produit_id'])
                        ->where('boutique_id', $boutique_id)
                        ->update(['prix_achat' => $coutRevient]);
                }
            }

            if ($montantPaye > 0) {
                \App\Models\PaiementFournisseur::create([
                    'commande_achat_id' => $achat->id,
                    'fournisseur_id' => $fournisseurId,
                    'boutique_id' => $boutique_id,
                    'user_id' => $user->id,
                    'montant' => $montantPaye,
                    'moyen_paiement' => $moyenPaiement === 'credit' ? 'especes' : $moyenPaiement,
                    'date_paiement' => $dateCommande,
                    'note' => $montantRestant > 0 ? 'Acompte initial (Réappro)' : 'Règlement comptant (Réappro)',
                ]);
            }

            app(\App\Services\ComptaService::class)->enregistrerAchatFournisseur($achat->fresh(['fournisseur']));
        } else {
            // SYSCOHADA : Enregistrement comptable standard du réapprovisionnement (Achats de marchandises)
            app(\App\Services\ComptaService::class)->enregistrerReappro($reapproProcessed, $boutique_id, $user->id);
        }

        return response()->json(['message' => 'Produit reapprovisionné avec succès'], 201);
    }

    public function rupture()
    {
        $boutique_id = $this->getBoutiqueId();
        $produits = Produit::with(['categorie', 'stock' => function ($query) use ($boutique_id) {
            $query->where('boutique_id', $boutique_id);
        }])
            ->whereHas('stock', function ($query) use ($boutique_id) {
                $query->where('quantite', '<=', 0)->where('boutique_id', $boutique_id);
            })
            ->orderBy('id', 'desc')->get();
        return response()->json($produits, 200);
    }

    /**
     * Recherche la disponibilité d'un produit dans toutes les autres filiales du groupe
     * avec adresse, contact et stock pour faciliter la réorientation des clients.
     */
    public function disponibilitesFiliales($id)
    {
        $produit = Produit::withTrashed()->findOrFail($id);
        $boutique_id = $this->getBoutiqueId();
        $currentBoutique = $boutique_id ? Boutique::find($boutique_id) : null;

        $currentStock = Stock::where('produit_id', $produit->id)
            ->where('boutique_id', $boutique_id)
            ->first();

        if (!$currentBoutique) {
            return response()->json([
                'produit' => [
                    'id' => $produit->id,
                    'nom' => $produit->nom,
                    'reference' => $produit->reference,
                    'stock_actuel' => $currentStock ? (float)$currentStock->quantite : 0,
                ],
                'filiales' => [],
                'total_filiales_disponibles' => 0,
                'total_stock_filiales' => 0,
            ], 200);
        }

        // Récupérer toutes les filiales et la boutique principale du groupe, hors boutique courante
        $groupBoutiques = $currentBoutique->getGroupBoutiques()->where('id', '!=', $currentBoutique->id);

        $filialesResultats = [];
        $totalStock = 0;
        $filialesDispos = 0;

        foreach ($groupBoutiques as $b) {
            // 1. Chercher le stock pour ce produit exact dans cette filiale
            $stock = Stock::where('produit_id', $produit->id)
                ->where('boutique_id', $b->id)
                ->first();

            // 2. Si pas trouvé directement par produit_id, chercher par référence ou nom identique
            if (!$stock) {
                $matchingProdIds = Produit::where('id', '!=', $produit->id)
                    ->where(function ($q) use ($produit) {
                        if (!empty($produit->reference)) {
                            $q->where('reference', $produit->reference);
                        }
                        $q->orWhereRaw('LOWER(TRIM(nom)) = ?', [strtolower(trim($produit->nom))]);
                    })
                    ->pluck('id');

                if ($matchingProdIds->isNotEmpty()) {
                    $stock = Stock::whereIn('produit_id', $matchingProdIds)
                        ->where('boutique_id', $b->id)
                        ->first();
                }
            }

            $qte = $stock ? (float)$stock->quantite : 0;
            $enStock = $qte > 0;
            if ($enStock) {
                $filialesDispos++;
                $totalStock += $qte;
            }

            $filialesResultats[] = [
                'boutique_id' => $b->id,
                'nom' => $b->nom,
                'adresse' => $b->adresse ?: 'Adresse non renseignée',
                'telephone' => $b->telephone ?: 'Non renseigné',
                'email' => $b->email,
                'is_parent' => is_null($b->parent_id),
                'quantite' => $qte,
                'prix_vente' => $stock && $stock->prix_vente > 0 ? (float)$stock->prix_vente : ($produit->prix_detail ?? 0),
                'en_stock' => $enStock,
                'statut' => $qte > 5 ? 'disponible' : ($qte > 0 ? 'faible' : 'rupture'),
            ];
        }

        // Tri : filiales avec stock disponible en premier, puis quantité décroissante
        usort($filialesResultats, function ($a, $b) {
            if ($a['en_stock'] !== $b['en_stock']) {
                return $a['en_stock'] ? -1 : 1;
            }
            return $b['quantite'] <=> $a['quantite'];
        });

        return response()->json([
            'produit' => [
                'id' => $produit->id,
                'nom' => $produit->nom,
                'reference' => $produit->reference,
                'image' => $produit->image,
                'stock_actuel' => $currentStock ? (float)$currentStock->quantite : 0,
            ],
            'current_boutique' => [
                'id' => $currentBoutique->id,
                'nom' => $currentBoutique->nom,
                'adresse' => $currentBoutique->adresse,
                'telephone' => $currentBoutique->telephone,
            ],
            'filiales' => $filialesResultats,
            'total_filiales_disponibles' => $filialesDispos,
            'total_stock_filiales' => $totalStock,
        ], 200);
    }

    /**
     * Recherche globale de stock inter-filiales pour administrateurs et gestionnaires.
     */
    public function searchStockInterFiliales(Request $request)
    {
        $query = $request->input('q', '');
        $boutique_id = $this->getBoutiqueId();
        $currentBoutique = $boutique_id ? Boutique::find($boutique_id) : null;

        if (!$currentBoutique) {
            return response()->json([], 200);
        }

        $groupBoutiqueIds = $currentBoutique->getGroupBoutiqueIds();
        $boutiques = Boutique::whereIn('id', $groupBoutiqueIds)->get()->keyBy('id');

        $produits = Produit::where(function ($q) use ($query) {
            $q->where('nom', 'like', "%{$query}%")
              ->orWhere('reference', 'like', "%{$query}%");
        })
        ->with(['stocks' => function ($q) use ($groupBoutiqueIds) {
            $q->whereIn('boutique_id', $groupBoutiqueIds);
        }, 'categorie'])
        ->limit(30)
        ->get();

        $results = [];
        foreach ($produits as $p) {
            $stockCourant = $p->stocks->firstWhere('boutique_id', $currentBoutique->id);
            $qteCourante = $stockCourant ? (float)$stockCourant->quantite : 0;

            $dispos = [];
            foreach ($groupBoutiqueIds as $gid) {
                if ($gid == $currentBoutique->id) continue;
                $b = $boutiques->get($gid);
                if (!$b) continue;

                $stk = $p->stocks->firstWhere('boutique_id', $gid);
                $q = $stk ? (float)$stk->quantite : 0;
                $dispos[] = [
                    'boutique_id' => $b->id,
                    'boutique_nom' => $b->nom,
                    'adresse' => $b->adresse ?: 'Adresse non renseignée',
                    'telephone' => $b->telephone ?: 'Non renseigné',
                    'quantite' => $q,
                    'prix_vente' => $stk ? (float)$stk->prix_vente : ($p->prix_detail ?? 0),
                    'en_stock' => $q > 0,
                ];
            }

            usort($dispos, fn($a, $b) => $b['quantite'] <=> $a['quantite']);

            $results[] = [
                'produit_id' => $p->id,
                'nom' => $p->nom,
                'reference' => $p->reference,
                'image' => $p->image,
                'stock_courant' => $qteCourante,
                'is_rupture' => $qteCourante <= 0,
                'filiales' => $dispos,
                'total_stock_filiales' => array_sum(array_column($dispos, 'quantite')),
                'filiales_avec_stock' => count(array_filter($dispos, fn($d) => $d['en_stock'])),
            ];
        }

        return response()->json($results, 200);
    }
    public function stock()
    {
        $boutique_id = $this->getBoutiqueId();
        $produits = Produit::with(['categorie', 'stock' => function ($query) use ($boutique_id) {
            $query->where('boutique_id', $boutique_id);
        }])
            ->whereHas('stock', function ($query) use ($boutique_id) {
                $query->where('quantite', '>', 0)->where('boutique_id', $boutique_id);
            })
            ->orderBy('id', 'desc')->get();
        return response()->json($produits, 200);
    }

    // Chargement adaptatif : ajuste la plage de dates selon le volume de données
    public function inventaire()
    {
        $boutique_id = $this->getBoutiqueId();
        $MAX_ROWS = 150; // Seuil au-delà duquel on réduit la plage

        // Plages à tester dans l'ordre : 3 jours → 2 jours → 1 jour
        $ranges = [2, 1, 0]; // subDays(n) → 3j, 2j, 1j
        $startDate = Carbon::now()->startOfDay(); // fallback : aujourd'hui

        foreach ($ranges as $daysBack) {
            $candidate = Carbon::now()->subDays($daysBack)->startOfDay();
            $candidateStr = $candidate->format('Y-m-d');

            // COUNT rapide (sans jointures) pour estimer le volume
            $count = Inventaire::where('boutique_id', $boutique_id)
                ->whereHas('produit')
                ->where('date', '>=', $candidateStr)
                ->count();

            $countPayments = \App\Models\PaiementCredit::where('boutique_id', $boutique_id)
                ->where('date_paiement', '>=', $candidateStr)
                ->count();

            if (($count + $countPayments) <= $MAX_ROWS || $daysBack === 0) {
                // Volume acceptable ou dernier recours (1 jour) → on utilise cette plage
                $startDate = $candidate;
                break;
            }
            // Sinon on continue avec une plage plus courte
        }

        $startDateStr = $startDate->format('Y-m-d');

        $inventaires = Inventaire::with('produit.stock', 'user', 'boutique')
            ->whereHas('produit')
            ->where('boutique_id', $boutique_id)
            ->where('date', '>=', $startDateStr)
            ->orderBy('created_at', 'desc')->get();

        $payments = \App\Models\PaiementCredit::with(['vente.client', 'user'])
            ->where('boutique_id', $boutique_id)
            ->where('date_paiement', '>=', $startDateStr)
            ->orderBy('date_paiement', 'desc')
            ->get();

        return $this->formatInventaireResponse($inventaires, $payments);
    }

    // Pour la methode qui nous recupere l'inventaire dans un intervalle de date
    public function inventaireDate($dateDebut, $dateFin)
    {
        $boutique_id = $this->getBoutiqueId();
        $inventaires = Inventaire::with('produit.stock', 'user', 'boutique')
            ->where('boutique_id', $boutique_id)
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->orderBy('created_at', 'desc')->get();

        $payments = \App\Models\PaiementCredit::with(['vente.client', 'user'])
            ->where('boutique_id', $boutique_id)
            ->whereBetween('date_paiement', [$dateDebut, $dateFin])
            ->orderBy('date_paiement', 'desc')
            ->get();

        return $this->formatInventaireResponse($inventaires, $payments, $dateDebut, $dateFin);
    }

    private function formatInventaireResponse($inventaires, $payments, $dateDebut = null, $dateFin = null)
    {
        $stats = [
            'totalEntrees' => 0,
            'totalSorties' => 0,
            'valeurAchatEntrante' => 0,
            'valeurVenteSortante' => 0,
            'recettesCredit' => 0,
            'beneficeTheorique' => 0,
            'totalSurplus' => 0,
            'totalRemise' => 0,
            'netMouvement' => 0
        ];

        // Process payments
        $formattedPayments = $payments->map(function($p) use (&$stats) {
            $stats['recettesCredit'] += (float)$p->montant;
            return array_merge($p->toArray(), [
                'type' => 'paiement',
                'date' => $p->date_paiement,
                'isGroup' => false,
                'impactNet' => (float)$p->montant,
                'description' => "Reglement Credit #{$p->vente_id} (" . ($p->vente->client->nom ?? 'Client') . ")"
            ]);
        })->toArray();

        // Group Inventaire
        $groupedGroups = [];
        $transactionMap = [];

        foreach ($inventaires as $item) {
            $groupKey = ($item->vente_id && $item->type !== 'paiement') ? "{$item->vente_id}_{$item->type}" : null;
            
            if ($groupKey) {
                if (!isset($transactionMap[$groupKey])) {
                    $isReturn = $item->type === 'ajout';
                    $transactionMap[$groupKey] = [
                        'isGroup' => true,
                        'vente_id' => $item->vente_id,
                        'date' => $item->date,
                        'created_at' => (string)$item->created_at,
                        'type' => $item->type,
                        'isReturn' => $isReturn,
                        'description' => $item->description,
                        'user' => $item->user,
                        'items' => [],
                        'totalQuantite' => 0,
                        'totalRemise' => 0, 
                        'totalSurplus' => 0,
                        'impactNet' => 0,
                        'showDetails' => false
                    ];
                }
                
                $transactionMap[$groupKey]['items'][] = $item;
                $transactionMap[$groupKey]['totalQuantite'] += $item->quantite;
                $transactionMap[$groupKey]['totalRemise'] += (float)($item->remise ?? 0);
                $transactionMap[$groupKey]['totalSurplus'] += (float)($item->surplus ?? 0);
                
                $pxAchat = $item->prix_achat ?? ($item->produit?->stock?->prix_achat ?? 0);
                $pxVente = $item->prix_vente ?? ($item->produit?->stock?->prix_vente ?? 0);
                $transactionMap[$groupKey]['impactNet'] += ($item->quantite * ($pxVente - $pxAchat));
            } else {
                $itemArray = $item->toArray();
                $pxAchat = $item->prix_achat ?? ($item->produit?->stock?->prix_achat ?? 0);
                $pxVente = $item->prix_vente ?? ($item->produit?->stock?->prix_vente ?? 0);
                $remise = (float)($item->remise ?? 0);
                
                if ($item->type === 'retrait') {
                    $impact = (($item->quantite * $pxVente) - $remise) - ($item->quantite * $pxAchat);
                } else {
                    // Logic for individual returns or reappro
                    $isReturn = isset($item->vente_id) && $item->vente_id;
                    if ($isReturn) {
                        $impact = (($item->quantite * $pxVente) - $remise) - ($item->quantite * $pxAchat);
                    } else {
                        // For reappro, it's not a "profit" event but a cost addition
                        $impact = 0; 
                    }
                }
                
                $groupedGroups[] = array_merge($itemArray, [
                    'isGroup' => false,
                    'impactNet' => $impact
                ]);
            }
        }

        // Finalize groups and update global stats
        foreach ($transactionMap as $key => &$g) {
            // Subtract global remise from impactNet regardless of type (balanced reversal)
            $g['impactNet'] -= $g['totalRemise'];

            if ($g['isReturn']) {
                $g['description'] = count($g['items']) > 1 ? "Retour Groupé #{$g['vente_id']} (" . count($g['items']) . " articles)" : $g['items'][0]->description;
                
                // Deduction from global stats for returns
                $stats['totalSorties'] -= $g['totalQuantite'];
                $valVente = 0;
                foreach($g['items'] as $it) {
                    $pxV = $it->prix_vente ?? ($it->produit?->stock?->prix_vente ?? 0);
                    $valVente += ($it->quantite * $pxV);
                }
                // Corrected: Subtract net value (gross - remise) for returns
                $stats['valeurVenteSortante'] -= ($valVente - $g['totalRemise']);
                $stats['beneficeTheorique'] -= $g['impactNet'];
            } else {
                if (count($g['items']) > 1) {
                    $g['description'] = "Vente Groupée #{$g['vente_id']} (" . count($g['items']) . " articles)";
                }
                
                // Addition to global stats for sales (retrait)
                if ($g['type'] === 'retrait') {
                    $stats['totalSorties'] += $g['totalQuantite'];
                    $stats['totalSurplus'] += (float)($g['totalSurplus'] ?? 0);
                    $stats['totalRemise'] += (float)($g['totalRemise'] ?? 0);
                    $valVente = 0;
                    foreach($g['items'] as $it) {
                        $pxV = $it->prix_vente ?? ($it->produit?->stock?->prix_vente ?? 0);
                        $valVente += ($it->quantite * $pxV);
                    }
                    $stats['valeurVenteSortante'] += ($valVente - $g['totalRemise']);
                    $stats['beneficeTheorique'] += $g['impactNet'];
                } else {
                    // Purchase entry
                    $stats['totalEntrees'] += $g['totalQuantite'];
                    $valAchat = 0;
                    foreach($g['items'] as $it) {
                        $pxA = $it->prix_achat ?? ($it->produit?->stock?->prix_achat ?? 0);
                        $valAchat += ($it->quantite * $pxA);
                    }
                    $stats['valeurAchatEntrante'] += $valAchat;
                }
            }
            $groupedGroups[] = $g;
        }

        // Finalize individual item stats
        foreach ($groupedGroups as $item) {
            if (isset($item['isGroup']) && $item['isGroup']) continue;
            if ($item['type'] === 'paiement') continue;

            // Robust access for virtual records (global discounts where produit_id is null)
            $pxAchat = $item['prix_achat'] ?? null;
            if ($pxAchat === null && !empty($item['produit_id']) && isset($item['produit']['stock']['prix_achat'])) {
                $pxAchat = $item['produit']['stock']['prix_achat'];
            }
            $pxAchat = (float) ($pxAchat ?? 0);

            $pxVente = $item['prix_vente'] ?? null;
            if ($pxVente === null && !empty($item['produit_id']) && isset($item['produit']['stock']['prix_vente'])) {
                $pxVente = $item['produit']['stock']['prix_vente'];
            }
            $pxVente = (float) ($pxVente ?? 0);

            $remise = (float)($item['remise'] ?? 0);
            $surplus = (float)($item['surplus'] ?? 0);
            $qte = (float)$item['quantite'];

            if ($item['type'] === 'retrait') {
                $stats['totalSorties'] += $qte;
                $stats['totalSurplus'] += $surplus;
                $stats['totalRemise'] += $remise;
                $isCreditSale = stripos($item['description'] ?? '', 'credit') !== false;
                if (!$isCreditSale) {
                    $stats['valeurVenteSortante'] += (($qte * $pxVente) - $remise);
                }
                $stats['beneficeTheorique'] += (float)($item['impactNet'] ?? 0);
            } else {
                if (isset($item['vente_id']) && $item['vente_id']) {
                    $stats['totalSorties'] -= $qte;
                    $stats['valeurVenteSortante'] -= (($qte * $pxVente) - $remise);
                    $stats['beneficeTheorique'] -= (float)($item['impactNet'] ?? 0); 
                } else {
                    $stats['totalEntrees'] += $qte;
                    $stats['valeurAchatEntrante'] += ($qte * $pxAchat);
                }
            }
        }

        $stats['netMouvement'] = $stats['totalEntrees'] - $stats['totalSorties'];

        // Combine and sort finally by created_at (most precise) then date
        $finalList = array_merge($groupedGroups, $formattedPayments);
        usort($finalList, function($a, $b) {
            $dateA = $a['created_at'] ?? $a['date'];
            $dateB = $b['created_at'] ?? $b['date'];
            return strcmp((string)$dateB, (string)$dateA);
        });

        return response()->json([
            'items' => $finalList,
            'stats' => $stats,
            'boutique' => count($inventaires) > 0 ? $inventaires[0]->boutique : null
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Produit $produit)
    {
        //Pour afficher un produit specifique:
        return response()->json($produit, 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Produit $produit)
    {
        //la vue pour la modification d'un produit avec les categories, inventaires et les stocks:
        // $produit = Produit::with([
        //     'categorie'=> function ($query){
        //         $query->select('id','nom');
        //     },
        //     'stock' => function ($query){
        //         $query->select('prix_achat','prix_vente','quantite');
        //     }
        // ])->find($produit->id);
        $boutique_id = $this->getBoutiqueId();
        $produitA = Produit::produitStock($produit->id, $boutique_id)->first();

        return response()->json($produitA, 200);
    }
    public function editProd($id)
    {
        // Pour recuperer un produit specifique avec les categories, les stocks et les inventaires:

        $boutique_id = $this->getBoutiqueId();
        $produit = Produit::with(['categorie', 'stock' => function ($q) use ($boutique_id) {
            $q->where('boutique_id', $boutique_id);
        }, 'inventaires' => function ($q) use ($boutique_id) {
            $q->where('boutique_id', $boutique_id);
        }])->find($id);
        return response()->json($produit, 200);
    }

    public function store(Request $request)
    {
        $boutique_id = $this->getBoutiqueId();
        if (!$boutique_id) {
            return response()->json(['error' => 'Aucune boutique sélectionnée'], 400);
        }

        // Pour la creation d'un nouveau produit:
        $fields = $request->validate(array_merge([
            'nom' => 'required|string',
            'description' => 'nullable|string',
            'categorie_id' => 'required|exists:categories,id',
            'prix_achat' => 'required|numeric|min:0', 
            'prix_vente' => 'required|numeric|min:0',
            'prix_detail' => 'nullable|numeric|min:0',
            'prix_demi_gros' => 'nullable|numeric|min:0',
            'seuil_demi_gros' => 'nullable|integer|min:1',
            'prix_master' => 'nullable|numeric|min:0',
            'seuil_gros' => 'nullable|integer|min:1',
            'unite_base' => 'nullable|string|max:40',
            'unite_conditionnement' => 'nullable|string|max:40',
            'quantite_conditionnement' => 'nullable|integer|min:1',
            'prix_conditionnement' => 'nullable|numeric|min:0',
            'quantite' => 'required|numeric|min:0',
            'seuil_alerte' => 'nullable|integer|min:0',
            'emplacement' => 'nullable|string|max:120',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10048',
            'reference' => 'nullable|string',
            'date_achat' => 'nullable|date',
            'date_fin_garantie' => 'nullable|date',
            'config' => 'nullable|string', // Expecting JSON string from FormData
        ], $this->traceabilityValidationRules()));

        $imagePath = $request->hasFile('image')
            ? $this->storeUploadedProductImage($request->file('image'))
            : null;

        $prixDemiGros = $request->filled('prix_demi_gros') ? (float) $request->input('prix_demi_gros') : null;
        $prixMaster = $request->filled('prix_master') ? (float) $request->input('prix_master') : null;
        $seuilDemiGros = $request->filled('seuil_demi_gros') ? (int) $request->input('seuil_demi_gros') : null;
        $seuilGros = $request->filled('seuil_gros') ? (int) $request->input('seuil_gros') : null;
        $uniteBase = $request->filled('unite_base') ? trim((string) $request->input('unite_base')) : 'Unité';
        $uniteCond = $request->filled('unite_conditionnement') ? trim((string) $request->input('unite_conditionnement')) : null;
        $qteCond = $request->filled('quantite_conditionnement') ? max(1, (int) $request->input('quantite_conditionnement')) : 1;
        $prixCond = $request->filled('prix_conditionnement') ? (float) $request->input('prix_conditionnement') : null;
        $traceability = $this->extractTraceabilityFields($request);

        $produit = Produit::create(array_merge([
            'nom' => $request->input('nom'),
            'unite_base' => $uniteBase,
            'unite_conditionnement' => $uniteCond,
            'quantite_conditionnement' => $qteCond,
            'prix_conditionnement' => $prixCond,
            'reference' => $request->input('reference'),
            'description' => $request->input('description'),
            'categorie_id' => $request->input('categorie_id'),
            'image' => $imagePath,
            'prix_master' => $prixMaster,
            'seuil_gros' => $seuilGros,
            'prix_demi_gros' => $prixDemiGros,
            'seuil_demi_gros' => $seuilDemiGros,
            'prix_detail' => $request->filled('prix_detail') ? (float) $request->input('prix_detail') : $request->input('prix_vente'),
            'config' => json_decode($request->input('config'), true),
        ], $traceability));

        $produitId = $produit->id;
        $user = Auth::user();

        // L'enregistrement dans la table Inventaire :
        Inventaire::create([
            'produit_id' => $produitId,
            'boutique_id' => $boutique_id,
            'user_id' => $user->id,
            'quantite' => $request->input('quantite'),
            'type' => 'ajout',
            'prix_achat' => $request->input('prix_achat'),
            'prix_vente' => $request->input('prix_vente'),
            'remise' => 0,
            'description' => "Initialisation du produit " . $request->input('nom'),
            'date' => now()->format('Y-m-d')
        ]);

        // Pour l'enregistrement dans la table stock :
        Stock::create([
            'produit_id' => $produitId,
            'boutique_id' => $boutique_id,
            'quantite' => $request->input('quantite'),
            'seuil_alerte' => $request->filled('seuil_alerte') ? (int)$request->input('seuil_alerte') : 5,
            'emplacement' => $request->input('emplacement'),
            'numero_lot' => $traceability['numero_lot'],
            'date_peremption' => $traceability['date_peremption'],
            'date_reapprovisionnement' => $request->input('date_achat') ?? now()->format('Y-m-d'),
            'prix_achat' => $request->input('prix_achat'),
            'prix_vente' => $request->input('prix_vente'),
            'prix_demi_gros' => $prixDemiGros,
            'prix_gros' => $prixMaster,
            'description' => $request->input('description')
        ]);

        $produit->load(['categorie', 'stock' => function ($query) use ($boutique_id) {
            $query->where('boutique_id', $boutique_id);
        }]);

        return response()->json($produit, 201);
    }

    public function update(Request $request, Produit $produit)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canChangePrices = $user && $user->hasPermission('can_change_prices');

        // Pour la modification d'un produit (Nom, Description, Categorie)
        $request->validate(array_merge([
            'nom' => 'string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10048',
            'description' => 'nullable|string',
            'categorie_id' => 'integer',
            'reference' => 'nullable|string|max:50',
            'prix_vente' => 'nullable|numeric',
            'prix_achat' => 'nullable|numeric',
            'prix_detail' => 'nullable|numeric',
            'prix_demi_gros' => 'nullable|numeric|min:0',
            'seuil_demi_gros' => 'nullable|integer|min:1',
            'prix_master' => 'nullable|numeric',
            'seuil_gros' => 'nullable|integer|min:1',
            'unite_base' => 'nullable|string|max:40',
            'unite_conditionnement' => 'nullable|string|max:40',
            'quantite_conditionnement' => 'nullable|integer|min:1',
            'prix_conditionnement' => 'nullable|numeric|min:0',
            'quantite' => 'nullable|numeric|min:0',
            'seuil_alerte' => 'nullable|integer|min:0',
            'emplacement' => 'nullable|string|max:120',
            'config' => 'nullable|string',
            // Accepter aussi camelCase au cas où
            'prixVente' => 'nullable|numeric',
            'prixAchat' => 'nullable|numeric',
        ], $this->traceabilityValidationRules()));

        $boutique_id = $this->getBoutiqueId();
        $stock = Stock::where('produit_id', $produit->id)->where('boutique_id', $boutique_id)->first();

        $reqPrixVente = $request->input('prix_vente') ?? $request->input('prixVente');
        $reqPrixAchat = $request->input('prix_achat') ?? $request->input('prixAchat');
        $reqPrixDetail = $request->input('prix_detail');
        $reqPrixDemiGros = $request->input('prix_demi_gros');
        $reqPrixMaster = $request->input('prix_master');
        $reqPrixConditionnement = $request->input('prix_conditionnement');

        if (!$canChangePrices) {
            $priceChanged = false;
            if ($stock) {
                if ($reqPrixVente !== null && (float)$reqPrixVente != (float)$stock->prix_vente) {
                    $priceChanged = true;
                }
                if ($reqPrixAchat !== null && (float)$reqPrixAchat != (float)$stock->prix_achat) {
                    $priceChanged = true;
                }
            }
            if ($reqPrixDetail !== null && (float)$reqPrixDetail != (float)$produit->prix_detail) {
                $priceChanged = true;
            }
            if ($reqPrixDemiGros !== null && (float)$reqPrixDemiGros != (float)($produit->prix_demi_gros ?? 0)) {
                $priceChanged = true;
            }
            if ($reqPrixMaster !== null && (float)$reqPrixMaster != (float)$produit->prix_master) {
                $priceChanged = true;
            }
            if ($reqPrixConditionnement !== null && (float)$reqPrixConditionnement != (float)($produit->prix_conditionnement ?? 0)) {
                $priceChanged = true;
            }

            if ($priceChanged) {
                return response()->json([
                    'error' => "Accès refusé. Seul l'administrateur de la boutique (ou un utilisateur disposant d'une autorisation déléguée) peut modifier les prix des produits."
                ], 403);
            }
        }

        $data = [
            'nom' => $request->input('nom', $produit->nom),
            'unite_base' => $request->filled('unite_base') ? trim((string)$request->input('unite_base')) : ($produit->unite_base ?: 'Unité'),
            'unite_conditionnement' => $request->has('unite_conditionnement')
                ? ($request->filled('unite_conditionnement') ? trim((string)$request->input('unite_conditionnement')) : null)
                : $produit->unite_conditionnement,
            'quantite_conditionnement' => $request->filled('quantite_conditionnement')
                ? max(1, (int)$request->input('quantite_conditionnement'))
                : ($produit->quantite_conditionnement ?? 1),
            'prix_conditionnement' => ($canChangePrices && $request->has('prix_conditionnement'))
                ? ($request->filled('prix_conditionnement') ? (float)$request->input('prix_conditionnement') : null)
                : $produit->prix_conditionnement,
            'reference' => $request->input('reference', $produit->reference),
            'description' => $request->input('description', $produit->description),
            'categorie_id' => $request->input('categorie_id', $produit->categorie_id),
            'prix_detail' => $canChangePrices ? $request->input('prix_detail', $produit->prix_detail) : $produit->prix_detail,
            'prix_demi_gros' => ($canChangePrices && $request->has('prix_demi_gros'))
                ? ($request->filled('prix_demi_gros') ? (float)$request->input('prix_demi_gros') : null)
                : $produit->prix_demi_gros,
            'seuil_demi_gros' => $request->has('seuil_demi_gros')
                ? ($request->filled('seuil_demi_gros') ? (int)$request->input('seuil_demi_gros') : null)
                : $produit->seuil_demi_gros,
            'prix_master' => $canChangePrices ? ($request->filled('prix_master') ? (float)$request->input('prix_master') : ($request->has('prix_master') ? null : $produit->prix_master)) : $produit->prix_master,
            'seuil_gros' => $request->has('seuil_gros')
                ? ($request->filled('seuil_gros') ? (int)$request->input('seuil_gros') : null)
                : $produit->seuil_gros,
            'config' => $request->has('config') ? json_decode($request->input('config'), true) : $produit->config,
        ];

        $traceability = $this->extractTraceabilityFields($request, $produit);
        $data = array_merge($data, $traceability);

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeUploadedProductImage($request->file('image'));
        }

        $produit->update($data);

        // Handle snake_case or camelCase inputs
        $prix_vente = $canChangePrices ? $reqPrixVente : ($stock ? $stock->prix_vente : null);
        $prix_achat = $canChangePrices ? $reqPrixAchat : ($stock ? $stock->prix_achat : null);
        $quantite = $request->input('quantite');
        $hasSeuil = $request->has('seuil_alerte') && $request->input('seuil_alerte') !== '' && $request->input('seuil_alerte') !== null;
        $hasEmplacement = $request->has('emplacement');
        $hasTierPrices = $canChangePrices && ($request->has('prix_demi_gros') || $request->has('prix_master'));
        $hasTraceStock = $request->has('numero_lot') || $request->has('date_peremption');

        // Only update stock if relevant fields are present
        if ($prix_vente !== null || $prix_achat !== null || $quantite !== null || $hasSeuil || $hasEmplacement || $hasTierPrices || $hasTraceStock) {
            if ($stock) {
                $stock->update([
                    'prix_vente' => $prix_vente ?? $stock->prix_vente,
                    'prix_achat' => $prix_achat ?? $stock->prix_achat,
                    'prix_demi_gros' => $canChangePrices && $request->has('prix_demi_gros') ? $data['prix_demi_gros'] : $stock->prix_demi_gros,
                    'prix_gros' => $canChangePrices && $request->has('prix_master') ? $data['prix_master'] : $stock->prix_gros,
                    'quantite' => $quantite ?? $stock->quantite,
                    'seuil_alerte' => $hasSeuil ? (int)$request->input('seuil_alerte') : ($stock->seuil_alerte ?? 5),
                    'emplacement' => $hasEmplacement ? $request->input('emplacement') : $stock->emplacement,
                    'numero_lot' => $request->has('numero_lot') ? $traceability['numero_lot'] : $stock->numero_lot,
                    'date_peremption' => $request->has('date_peremption') ? $traceability['date_peremption'] : $stock->date_peremption,
                ]);
            } else {
                Stock::create([
                    'produit_id' => $produit->id,
                    'boutique_id' => $boutique_id,
                    'quantite' => $quantite ?? 0,
                    'seuil_alerte' => $hasSeuil ? (int)$request->input('seuil_alerte') : 5,
                    'emplacement' => $hasEmplacement ? $request->input('emplacement') : null,
                    'numero_lot' => $traceability['numero_lot'],
                    'date_peremption' => $traceability['date_peremption'],
                    'prix_achat' => $prix_achat ?? 0,
                    'prix_vente' => $prix_vente ?? 0,
                    'prix_demi_gros' => $data['prix_demi_gros'],
                    'prix_gros' => $data['prix_master'],
                    'date_reapprovisionnement' => now()->format('Y-m-d')
                ]);
            }
        }

        return response()->json(['message' => 'Produit modifié avec succès', 'produit' => $produit], 200);
    }

    public function importCSV(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $rawContent = file_get_contents($file->getRealPath());
        if ($rawContent === false || trim($rawContent) === '') {
            return response()->json(['message' => "Le fichier CSV est vide ou illisible."], 422);
        }

        // Handle UTF-16LE / UTF-16BE / Windows-1252 / UTF-8 BOM from Windows/Excel
        if (str_starts_with($rawContent, "\xFF\xFE")) {
            $rawContent = mb_convert_encoding(substr($rawContent, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($rawContent, "\xFE\xFF")) {
            $rawContent = mb_convert_encoding(substr($rawContent, 2), 'UTF-8', 'UTF-16BE');
        } else {
            $rawContent = str_replace("\xEF\xBB\xBF", '', $rawContent);
            if (!mb_check_encoding($rawContent, 'UTF-8')) {
                $rawContent = mb_convert_encoding($rawContent, 'UTF-8', 'Windows-1252');
            }
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $rawContent);
        rewind($handle);

        $headersString = fgets($handle);
        if ($headersString === false) {
            fclose($handle);
            return response()->json(['message' => "Le fichier CSV est vide."], 422);
        }

        $counts = [
            ';' => substr_count($headersString, ';'),
            ',' => substr_count($headersString, ','),
            "\t" => substr_count($headersString, "\t"),
        ];
        arsort($counts);
        $delimiter = (reset($counts) > 0) ? (string) key($counts) : ';';
        $rawHeaders = str_getcsv(trim($headersString), $delimiter);
        $canonicalHeaders = array_map(fn($h) => $this->mapImportCsvHeader($h), $rawHeaders);

        $hasNom = in_array('nom', $canonicalHeaders, true);
        $hasQuantite = in_array('quantite', $canonicalHeaders, true);
        $hasPrixVente = in_array('prix_vente', $canonicalHeaders, true);

        if (!$hasNom || !$hasQuantite || !$hasPrixVente) {
            fclose($handle);
            return response()->json([
                'message' => "L'entête du fichier est incorrecte.",
                'error' => "Le fichier doit provenir de l'export stock MalCom ou contenir au minimum les colonnes : nom, prix_vente, quantite (et optionnellement categorie, reference, prix_achat, prix_master, description).",
                'received' => implode($delimiter, array_map('trim', $rawHeaders))
            ], 422);
        }

        $user = Auth::user();
        $boutique_id = $this->getBoutiqueId();
        $fallbackCategorieId = $request->input('categorie_id');

        // Preload existing categories for this boutique (+ global) for case-insensitive matching
        $existingCategories = Categorie::where(function ($q) use ($boutique_id) {
            $q->where('boutique_id', $boutique_id)
              ->orWhereNull('boutique_id');
        })->get();

        $categoryCache = [];
        foreach ($existingCategories as $cat) {
            $norm = $this->normalizeCategoryName((string) $cat->nom);
            if ($norm !== '' && !isset($categoryCache[$norm])) {
                $categoryCache[$norm] = $cat->id;
            }
        }

        // Preload existing stocks/products in this boutique to avoid duplicates and restock instead
        $existingStocks = Stock::with('produit')
            ->where('boutique_id', $boutique_id)
            ->whereHas('produit')
            ->get();

        $stockByReference = [];
        $stockByName = [];
        foreach ($existingStocks as $st) {
            $p = $st->produit;
            if (!$p) {
                continue;
            }
            $refKey = $this->normalizeProductKey((string) ($p->reference ?? ''));
            if ($refKey !== '' && !str_starts_with($refKey, '#prod-') && !isset($stockByReference[$refKey])) {
                $stockByReference[$refKey] = $st;
            }
            $nameKey = $this->normalizeProductKey((string) ($p->nom ?? ''));
            if ($nameKey !== '' && !isset($stockByName[$nameKey])) {
                $stockByName[$nameKey] = $st;
            }
        }

        $createdCount = 0;
        $restockedCount = 0;
        $createdCategoriesCount = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                // Pad or align row length with headers
                $rowCount = count($row);
                $headerCount = count($canonicalHeaders);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                } elseif ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                $data = [];
                foreach ($canonicalHeaders as $idx => $key) {
                    if ($key !== null && !array_key_exists($key, $data)) {
                        $data[$key] = trim((string) $row[$idx]);
                    }
                }

                $nom = $data['nom'] ?? '';
                if ($nom === '') {
                    continue;
                }

                // Resolve category automatically (case-insensitive) from CSV or fallback
                $rawCatName = trim((string) ($data['categorie'] ?? ''));
                $categorieId = null;

                if ($rawCatName !== '') {
                    $normCat = $this->normalizeCategoryName($rawCatName);
                    if (isset($categoryCache[$normCat])) {
                        $categorieId = $categoryCache[$normCat];
                    } else {
                        $newCat = new Categorie();
                        $newCat->nom = $rawCatName;
                        $newCat->description = '';
                        $newCat->boutique_id = $boutique_id;
                        $newCat->save();

                        $categorieId = $newCat->id;
                        $categoryCache[$normCat] = $categorieId;
                        $createdCategoriesCount++;
                    }
                } elseif (!empty($fallbackCategorieId)) {
                    $categorieId = $fallbackCategorieId;
                } else {
                    $defaultNorm = $this->normalizeCategoryName('Général');
                    if (isset($categoryCache[$defaultNorm])) {
                        $categorieId = $categoryCache[$defaultNorm];
                    } else {
                        $newCat = new Categorie();
                        $newCat->nom = 'Général';
                        $newCat->description = '';
                        $newCat->boutique_id = $boutique_id;
                        $newCat->save();

                        $categorieId = $newCat->id;
                        $categoryCache[$defaultNorm] = $categorieId;
                        $createdCategoriesCount++;
                    }
                }

                $quantite = $this->parseCsvNumber($data['quantite'] ?? 0, 0);
                $prixAchat = $this->parseCsvNumber($data['prix_achat'] ?? 0, 0);
                $prixVente = $this->parseCsvNumber($data['prix_vente'] ?? 0, 0);
                $prixDemiGros = isset($data['prix_demi_gros']) && $data['prix_demi_gros'] !== ''
                    ? $this->parseCsvNumber($data['prix_demi_gros'], null)
                    : null;
                $prixMaster = isset($data['prix_master']) && $data['prix_master'] !== ''
                    ? $this->parseCsvNumber($data['prix_master'], null)
                    : null;
                $prixColis = isset($data['prix_conditionnement']) && $data['prix_conditionnement'] !== ''
                    ? $this->parseCsvNumber($data['prix_conditionnement'], null)
                    : null;
                $seuilAlerte = isset($data['seuil_alerte']) && $data['seuil_alerte'] !== ''
                    ? (int) round($this->parseCsvNumber($data['seuil_alerte'], 5))
                    : 5;

                // Parse Conditionnement from export e.g. "Carton (12 Unités)" or "Unitaire"
                $uniteCond = null;
                $qteCond = 1;
                if (!empty($data['conditionnement']) && mb_strtolower($data['conditionnement'], 'UTF-8') !== 'unitaire') {
                    if (preg_match('/^(.+?)\s*\(\s*(\d+(?:[.,]\d+)?)/u', $data['conditionnement'], $m)) {
                        $uniteCond = trim($m[1]);
                        $qteCond = max(1, (int) round((float) str_replace(',', '.', $m[2])));
                    }
                }

                $reference = isset($data['reference']) && $data['reference'] !== '' ? $data['reference'] : null;
                $normRef = $reference ? $this->normalizeProductKey($reference) : '';
                $normNom = $this->normalizeProductKey($nom);

                $existingStock = null;
                if ($normRef !== '' && !str_starts_with($normRef, '#prod-') && isset($stockByReference[$normRef])) {
                    $existingStock = $stockByReference[$normRef];
                } elseif ($normNom !== '' && isset($stockByName[$normNom])) {
                    $existingStock = $stockByName[$normNom];
                }

                if ($existingStock) {
                    $existingProduit = $existingStock->produit;
                    $effectivePrixAchat = $prixAchat > 0 ? $prixAchat : (float) $existingStock->prix_achat;
                    $effectivePrixVente = $prixVente > 0 ? $prixVente : (float) $existingStock->prix_vente;

                    $existingStock->update([
                        'quantite' => (float) $existingStock->quantite + (float) $quantite,
                        'prix_achat' => $effectivePrixAchat,
                        'prix_vente' => $effectivePrixVente,
                        'prix_demi_gros' => $prixDemiGros !== null ? $prixDemiGros : $existingStock->prix_demi_gros,
                        'prix_gros' => $prixMaster !== null ? $prixMaster : $existingStock->prix_gros,
                        'seuil_alerte' => isset($data['seuil_alerte']) && $data['seuil_alerte'] !== '' ? $seuilAlerte : $existingStock->seuil_alerte,
                        'emplacement' => !empty($data['emplacement']) ? $data['emplacement'] : $existingStock->emplacement,
                        'date_reapprovisionnement' => now()->format('Y-m-d'),
                        'description' => 'Réapprovisionnement via importation CSV (+' . $quantite . ')',
                    ]);

                    if ($existingProduit) {
                        $existingProduit->update([
                            'prix_detail' => $effectivePrixVente,
                            'prix_demi_gros' => $prixDemiGros !== null ? $prixDemiGros : $existingProduit->prix_demi_gros,
                            'prix_master' => $prixMaster !== null ? $prixMaster : $existingProduit->prix_master,
                            'prix_conditionnement' => $prixColis !== null ? $prixColis : $existingProduit->prix_conditionnement,
                        ]);
                    }

                    Inventaire::create([
                        'produit_id' => $existingStock->produit_id,
                        'boutique_id' => $boutique_id,
                        'user_id' => $user->id,
                        'quantite' => $quantite,
                        'type' => 'ajout',
                        'prix_achat' => $effectivePrixAchat,
                        'prix_vente' => $effectivePrixVente,
                        'remise' => 0,
                        'description' => "Réapprovisionnement Import CSV",
                        'date' => now()->format('Y-m-d')
                    ]);

                    $restockedCount++;
                    continue;
                }

                $produit = Produit::create([
                    'nom' => $nom,
                    'reference' => $reference,
                    'categorie_id' => $categorieId,
                    'description' => $data['description'] ?? '',
                    'image' => null,
                    'unite_base' => !empty($data['unite_base']) ? $data['unite_base'] : 'Unité',
                    'unite_conditionnement' => $uniteCond,
                    'quantite_conditionnement' => $qteCond,
                    'prix_conditionnement' => $prixColis,
                    'prix_detail' => $prixVente,
                    'prix_demi_gros' => $prixDemiGros,
                    'prix_master' => $prixMaster,
                ]);

                $createdStock = Stock::create([
                    'produit_id' => $produit->id,
                    'boutique_id' => $boutique_id,
                    'quantite' => $quantite,
                    'seuil_alerte' => $seuilAlerte,
                    'emplacement' => !empty($data['emplacement']) ? $data['emplacement'] : null,
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                    'prix_demi_gros' => $prixDemiGros,
                    'prix_gros' => $prixMaster,
                    'date_reapprovisionnement' => now()->format('Y-m-d')
                ]);

                $createdStock->setRelation('produit', $produit);
                if ($normRef !== '' && !str_starts_with($normRef, '#prod-') && !isset($stockByReference[$normRef])) {
                    $stockByReference[$normRef] = $createdStock;
                }
                if ($normNom !== '' && !isset($stockByName[$normNom])) {
                    $stockByName[$normNom] = $createdStock;
                }

                Inventaire::create([
                    'produit_id' => $produit->id,
                    'boutique_id' => $boutique_id,
                    'user_id' => $user->id,
                    'quantite' => $quantite,
                    'type' => 'ajout',
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                    'remise' => 0,
                    'description' => "Importation CSV",
                    'date' => now()->format('Y-m-d')
                ]);

                $createdCount++;
            }

            fclose($handle);

            $totalProcessed = $createdCount + $restockedCount;
            if ($totalProcessed === 0) {
                DB::rollBack();
                return response()->json(['message' => "Aucun produit n'a pu être importé. Vérifiez le contenu du fichier."], 400);
            }

            DB::commit();

            $parts = [];
            if ($createdCount > 0) $parts[] = "$createdCount créé(s)";
            if ($restockedCount > 0) $parts[] = "$restockedCount réapprovisionné(s)";
            $msg = "$totalProcessed produit(s) traité(s) (" . implode(', ', $parts) . ")";
            if ($createdCategoriesCount > 0) {
                $msg .= " · $createdCategoriesCount nouvelle(s) catégorie(s) créée(s)";
            }

            return response()->json([
                'message' => $msg,
                'imported_count' => $totalProcessed,
                'created_count' => $createdCount,
                'restocked_count' => $restockedCount,
                'created_categories_count' => $createdCategoriesCount,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            return response()->json([
                'message' => "Erreur lors de l'importation CSV.",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function normalizeCategoryName(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name));
        return mb_strtolower((string) $collapsed, 'UTF-8');
    }

    private function normalizeProductKey(string $val): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($val));
        return mb_strtolower((string) $collapsed, 'UTF-8');
    }

    private function mapImportCsvHeader(string $header): ?string
    {
        $clean = trim($header);
        $clean = mb_strtolower($clean, 'UTF-8');
        $clean = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c'], $clean);
        $clean = preg_replace('/\s+/u', ' ', $clean);

        if ($clean === 'nom' || $clean === 'designation' || $clean === 'article' || $clean === 'produit' || $clean === 'libelle') return 'nom';
        if ($clean === 'reference' || $clean === 'ref') return 'reference';
        if ($clean === 'categorie' || $clean === 'category' || $clean === 'famille' || $clean === 'rayon') return 'categorie';
        if ($clean === 'emplacement') return 'emplacement';
        if ($clean === 'unite base' || $clean === 'unite_base') return 'unite_base';
        if ($clean === 'conditionnement') return 'conditionnement';
        if ($clean === 'quantite' || $clean === 'qte' || $clean === 'stock' || str_starts_with($clean, 'stock actuel')) return 'quantite';
        if ($clean === 'seuil alerte' || $clean === 'seuil_alerte') return 'seuil_alerte';
        if ($clean === 'prix_achat' || $clean === 'pa' || str_starts_with($clean, 'prix achat')) return 'prix_achat';
        if ($clean === 'prix_vente' || $clean === 'prix vente' || $clean === 'prix_detail' || $clean === 'prix unitaire' || $clean === 'pu' || str_starts_with($clean, 'prix detail')) return 'prix_vente';
        if ($clean === 'prix_demi_gros' || str_starts_with($clean, 'prix demi-gros')) return 'prix_demi_gros';
        if ($clean === 'prix_master' || $clean === 'prix_gros' || str_starts_with($clean, 'prix gros')) return 'prix_master';
        if ($clean === 'prix_conditionnement' || str_starts_with($clean, 'prix colis')) return 'prix_conditionnement';
        if ($clean === 'description') return 'description';

        return null;
    }

    private function parseCsvNumber($value, $default = 0)
    {
        if ($value === null) return $default;
        $str = str_replace(["\u{00A0}", ' ', ','], ['', '', '.'], trim((string) $value));
        if ($str === '' || !is_numeric($str)) {
            return $default;
        }
        return (float) $str;
    }

    public function summary()
    {
        $boutique_id = $this->getBoutiqueId();

        $produit_count = Stock::where('boutique_id', $boutique_id)
            ->whereHas('produit')
            ->where('quantite', '>', 0)
            ->count();
        $total_stock = Stock::where('boutique_id', $boutique_id)
            ->whereHas('produit')
            ->selectRaw('SUM(quantite * prix_vente) as total')
            ->first()->total ?? 0;

        $ventes_jour = DB::table('ventes')
            ->where('boutique_id', $boutique_id)
            ->whereIn('statut', ['validee', 'payee', 'credit'])
            ->whereRaw("date_vente = ?", [Carbon::today()->format('Y-m-d')])
            ->count();

        $categorie_count = \App\Models\Categorie::where('boutique_id', $boutique_id)
            ->orWhereNull('boutique_id')
            ->count();

        return response()->json([
            'produit_count' => $produit_count,
            'total_stock' => (float)$total_stock,
            'ventes_jour' => $ventes_jour,
            'categorie_count' => $categorie_count,
            'annee_active' => now()->year
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Produit $produit)
    {
        $boutique_id = $this->getBoutiqueId();
        if (!$boutique_id) {
            return response()->json(['error' => 'Aucune boutique sélectionnée'], 400);
        }

        // Verify product belongs to a stock in this boutique
        $hasStock = \App\Models\Stock::where('produit_id', $produit->id)
            ->where('boutique_id', $boutique_id)
            ->exists();

        if (!$hasStock) {
            return response()->json(['error' => 'Produit non trouvé dans cette boutique'], 404);
        }

        //Pour la suppression d'un produit (Soft Delete enabled):
        $produit->delete();

        // \Log::info("Product {$produit->id} soft deleted by user " . auth()->id() . " for boutique {$boutique_id}");

        return response()->json(['message' => 'Produit déplacé vers la corbeille'], 200);
    }

    public function trashed()
    {
        $boutique_id = $this->getBoutiqueId();
        if (!$boutique_id) {
            return response()->json(['error' => 'Aucune boutique sélectionnée'], 400);
        }

        $produits = Produit::onlyTrashed()
            ->with(['categorie', 'stock' => function ($query) use ($boutique_id) {
                $query->where('boutique_id', $boutique_id);
            }])
            ->whereHas('stock', function ($query) use ($boutique_id) {
                $query->where('boutique_id', $boutique_id);
            })
            ->orderBy('deleted_at', 'desc')->get();

        return response()->json($produits, 200);
    }

    public function restore(Request $request, $id)
    {
        $boutique_id = $this->getBoutiqueId();
        if (!$boutique_id) {
            return response()->json(['error' => 'Aucune boutique sélectionnée'], 400);
        }

        $produit = Produit::onlyTrashed()->findOrFail($id);

        // Verify product belongs to this boutique's stock
        $stock = Stock::where('produit_id', $id)
            ->where('boutique_id', $boutique_id)
            ->first();

        if (!$stock) {
            return response()->json(['error' => 'Produit non trouvé dans cette boutique'], 404);
        }

        $produit->restore();

        if ($request->has('quantite') != 0) {
            $stock->update([
                'quantite' => $request->quantite,
                'date_reapprovisionnement' => now()->format('Y-m-d')
            ]);
        }else{

            $stock->update([
                'quantite' => $produit->quantite,
                'date_reapprovisionnement' => now()->format('Y-m-d')
            ]);
        }

        // Log::info("Product {$id} restored by user " . auth()->id() . " for boutique {$boutique_id}");

        return response()->json(['message' => 'Produit restauré avec succès'], 200);
    }

    /**
     * Sert publiquement les fichiers médias (images produits, logos boutiques) avec headers CORS & cache.
     */
    public function serveMedia(string $folder, string $filename)
    {
        $allowedFolders = ['produit', 'boutique', 'logo', 'categories'];
        $cleanFolder = basename($folder);
        $decodedFile = rawurldecode($filename);

        if (
            !in_array($cleanFolder, $allowedFolders, true)
            || $decodedFile === ''
            || str_contains($decodedFile, '..')
            || str_contains($decodedFile, "\0")
            || str_contains($decodedFile, '\\')
        ) {
            abort(404);
        }

        $cleanFile = basename($decodedFile);
        $baseDir = realpath(storage_path('app/public/' . $cleanFolder));
        $candidates = [];
        if ($baseDir !== false) {
            $candidates[] = $baseDir . DIRECTORY_SEPARATOR . $cleanFile;
        }
        $candidates[] = public_path('storage/' . $cleanFolder . '/' . $cleanFile);

        $targetPath = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $targetPath = $candidate;
                break;
            }
        }

        if ($targetPath === null) {
            abort(404);
        }

        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => (@mime_content_type($targetPath) ?: 'application/octet-stream'),
        };

        return response()->file($targetPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function storeUploadedProductImage($file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $slug = \Illuminate\Support\Str::slug($originalName) ?: 'produit';
        $fileName = time() . '_' . $slug . '.' . $ext;
        $chemin = $file->storeAs('produit', $fileName, 'public');

        return '/api/media/' . ltrim($chemin, '/');
    }

    private function traceabilityValidationRules(): array
    {
        return [
            'numero_lot' => 'nullable|string|max:100',
            'date_peremption' => 'nullable|date',
            'alerte_peremption_jours' => 'nullable|integer|min:1|max:730',
            'exige_imei_serie' => 'nullable',
            'duree_garantie_mois' => 'nullable|integer|min:0|max:240',
            'est_consignable' => 'nullable',
            'type_emballage' => 'nullable|string|max:100',
            'montant_consigne_unitaire' => 'nullable|numeric|min:0',
        ];
    }

    private function extractTraceabilityFields(Request $request, ?Produit $existing = null): array
    {
        $numeroLot = $request->has('numero_lot')
            ? ($request->filled('numero_lot') ? trim((string) $request->input('numero_lot')) : null)
            : ($existing?->numero_lot);

        $datePeremption = $request->has('date_peremption')
            ? ($request->filled('date_peremption') ? $request->input('date_peremption') : null)
            : ($existing?->date_peremption?->format('Y-m-d'));

        $alerteJours = $request->filled('alerte_peremption_jours')
            ? max(1, (int) $request->input('alerte_peremption_jours'))
            : ($existing?->alerte_peremption_jours ?? 30);

        $exigeImei = $request->has('exige_imei_serie')
            ? filter_var($request->input('exige_imei_serie'), FILTER_VALIDATE_BOOLEAN)
            : (bool) ($existing?->exige_imei_serie ?? false);

        $garantieMois = $request->has('duree_garantie_mois')
            ? ($request->filled('duree_garantie_mois') ? max(0, (int) $request->input('duree_garantie_mois')) : 0)
            : (int) ($existing?->duree_garantie_mois ?? 0);

        $estConsignable = $request->has('est_consignable')
            ? filter_var($request->input('est_consignable'), FILTER_VALIDATE_BOOLEAN)
            : (bool) ($existing?->est_consignable ?? false);

        $typeEmballage = $request->has('type_emballage')
            ? ($request->filled('type_emballage') ? trim((string) $request->input('type_emballage')) : null)
            : ($existing?->type_emballage);

        $consigneUnitaire = $request->has('montant_consigne_unitaire')
            ? ($request->filled('montant_consigne_unitaire') ? max(0, (float) $request->input('montant_consigne_unitaire')) : 0)
            : (float) ($existing?->montant_consigne_unitaire ?? 0);

        return [
            'numero_lot' => $numeroLot,
            'date_peremption' => $datePeremption,
            'alerte_peremption_jours' => $alerteJours,
            'exige_imei_serie' => $exigeImei,
            'duree_garantie_mois' => $garantieMois,
            'est_consignable' => $estConsignable,
            'type_emballage' => $estConsignable ? $typeEmballage : null,
            'montant_consigne_unitaire' => $estConsignable ? $consigneUnitaire : 0,
        ];
    }
}
