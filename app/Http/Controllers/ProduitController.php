<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Produit;
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
        // Pour le reaprovisionnement des produits:
        $request->validate([
            'produits' => 'Array'
        ]);

        $user = Auth::user();
        $boutique_id = $this->getBoutiqueId();

        foreach ($request->produits as $produit) {
            $produitId = $produit['produit_id'];
            $quantite = isset($produit['quantite']) ? floatval($produit['quantite']) : 0;
            if ($quantite <= 0) {
                continue;
            }

            $prod = $produit['produit'] ?? '';

            // Pour la mise a jour dans la table stock :
            $stock = Stock::where('produit_id', $produitId)->where('boutique_id', $boutique_id)->first();

            $prix_achat = (isset($produit['prix_achat']) && floatval($produit['prix_achat']) > 0)
                ? floatval($produit['prix_achat'])
                : ($stock ? floatval($stock->prix_achat) : 0);

            $prix_vente = (isset($produit['prix_vente']) && floatval($produit['prix_vente']) > 0)
                ? floatval($produit['prix_vente'])
                : ($stock ? floatval($stock->prix_vente) : 0);

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
                'description' => 'Reapprovisionnement du produit ' . $prod,
                'date' => now()->format('Y-m-d')
            ]);

            if ($stock) {
                $stock->update([
                    'quantite' => $stock->quantite + $quantite,
                    'date_reapprovisionnement' => now()->format('Y-m-d'),
                    'prix_achat' => $prix_achat,
                    'prix_vente' => $prix_vente,
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
                    'description' => 'Initialisation stock via réappro'
                ]);
            }
        }

        // SYSCOHADA : Enregistrement comptable du réapprovisionnement (Achats de marchandises)
        app(\App\Services\ComptaService::class)->enregistrerReappro($request->produits, $boutique_id, $user->id);

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
                        'impactNet' => 0,
                        'showDetails' => false
                    ];
                }
                
                $transactionMap[$groupKey]['items'][] = $item;
                $transactionMap[$groupKey]['totalQuantite'] += $item->quantite;
                $transactionMap[$groupKey]['totalRemise'] += (float)($item->remise ?? 0);
                
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

            // Robust access for virtual records (global discounts)
            $pxAchat = $item['prix_achat'] ?? 0;
            if ($pxAchat == 0 && isset($item['produit']['stock']['prix_achat'])) {
                $pxAchat = $item['produit']['stock']['prix_achat'];
            }

            $pxVente = $item['prix_vente'] ?? 0;
            if ($pxVente == 0 && isset($item['produit']['stock']['prix_vente'])) {
                $pxVente = $item['produit']['stock']['prix_vente'];
            }

            $remise = (float)($item['remise'] ?? 0);
            $qte = (float)$item['quantite'];

            if ($item['type'] === 'retrait') {
                $stats['totalSorties'] += $qte;
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
        $fields = $request->validate([
            'nom' => 'required|string',
            'description' => 'nullable|string',
            'categorie_id' => 'required|exists:categories,id',
            'prix_achat' => 'required|numeric|min:0', 
            'prix_vente' => 'required|numeric|min:0',
            'prix_detail' => 'nullable|numeric|min:0',
            'prix_master' => 'nullable|numeric|min:0',
            'quantite' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'reference' => 'nullable|string',
            'date_achat' => 'nullable|date',
            'date_fin_garantie' => 'nullable|date',
            'config' => 'nullable|string', // Expecting JSON string from FormData
        ]);

        $chemin = null;
        if ($request->hasFile('image')) {
            $fileName = time() . '_' . $request->file('image')->getClientOriginalName();
            $chemin = $request->file('image')->storeAs('produit', $fileName, 'public');
            $imagePath = asset('storage/' . $chemin);
        } else {
            $imagePath = asset('storage/produit/default.png');
        }

        $produit = Produit::create([
            'nom' => $request->input('nom'),
            'reference' => $request->input('reference'),
            'description' => $request->input('description'),
            'categorie_id' => $request->input('categorie_id'),
            'image' => $imagePath,
            'prix_master' => $request->input('prix_master'),
            'prix_detail' => $request->input('prix_detail'),
            'config' => json_decode($request->input('config'), true),
        ]);

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
            'date_reapprovisionnement' => $request->input('date_achat') ?? now()->format('Y-m-d'),
            'prix_achat' => $request->input('prix_achat'),
            'prix_vente' => $request->input('prix_vente'),
            'description' => $request->input('description')
        ]);

        return response()->json($produit, 201);
    }

    public function update(Request $request, Produit $produit)
    {
        // Pour la modification d'un produit (Nom, Description, Categorie)
        $request->validate([
            'nom' => 'string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10048',
            'description' => 'nullable|string',
            'categorie_id' => 'integer',
            'reference' => 'nullable|string|max:50',
            'prix_vente' => 'nullable|numeric',
            'prix_achat' => 'nullable|numeric',
            'prix_detail' => 'nullable|numeric',
            'prix_master' => 'nullable|numeric',
            'quantite' => 'nullable|integer',
            'config' => 'nullable|string',
            // Accepter aussi camelCase au cas où
            'prixVente' => 'nullable|numeric',
            'prixAchat' => 'nullable|numeric',
        ]);

        $data = [
            'nom' => $request->input('nom', $produit->nom),
            'reference' => $request->input('reference', $produit->reference),
            'description' => $request->input('description', $produit->description),
            'categorie_id' => $request->input('categorie_id', $produit->categorie_id),
            'prix_detail' => $request->input('prix_detail', $produit->prix_detail),
            'prix_master' => $request->input('prix_master', $produit->prix_master),
            'config' => $request->has('config') ? json_decode($request->input('config'), true) : $produit->config,
        ];

        if ($request->hasFile('image')) {
            $fileName = time() . '_' . $request->file('image')->getClientOriginalName();
            $chemin = $request->file('image')->storeAs('produit', $fileName, 'public');
            $data['image'] = asset('storage/' . $chemin);
        }

        $produit->update($data);

        // Mise à jour du stock pour la boutique actuelle
        $boutique_id = $this->getBoutiqueId();
        
        // Handle snake_case or camelCase inputs
        $prix_vente = $request->input('prix_vente') ?? $request->input('prixVente');
        $prix_achat = $request->input('prix_achat') ?? $request->input('prixAchat');
        $quantite = $request->input('quantite');

        // Only update stock if relevant fields are present
        if ($prix_vente !== null || $prix_achat !== null || $quantite !== null) {
            $stock = Stock::where('produit_id', $produit->id)->where('boutique_id', $boutique_id)->first();
            
            if ($stock) {
                $stock->update([
                    'prix_vente' => $prix_vente ?? $stock->prix_vente,
                    'prix_achat' => $prix_achat ?? $stock->prix_achat,
                    'quantite' => $quantite ?? $stock->quantite,
                ]);
            } else {
                Stock::create([
                    'produit_id' => $produit->id,
                    'boutique_id' => $boutique_id,
                    'quantite' => $quantite ?? 0,
                    'prix_achat' => $prix_achat ?? 0,
                    'prix_vente' => $prix_vente ?? 0,
                    'date_reapprovisionnement' => now()->format('Y-m-d')
                ]);
            }
        }

        return response()->json(['message' => 'Produit modifié avec succès', 'produit' => $produit], 200);
    }

    public function importCSV(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'categorie_id' => 'required'
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        // Expected header as per user request
        $expectedHeader = ['nom', 'reference', 'prix_achat', 'prix_vente', 'prix_master', 'quantite', 'description'];

        // Read headers and remove BOM if present
        $headersString = fgets($handle);
        $headersString = str_replace("\xEF\xBB\xBF", '', $headersString);
        $headers = str_getcsv(trim($headersString), ';');

        // Clean headers (trim spaces)
        $headers = array_map('trim', $headers);

        // Strict header validation
        if ($headers !== $expectedHeader) {
            fclose($handle);
            return response()->json([
                'message' => "L'entête du fichier est incorrecte.",
                'error' => "Format attendu: " . implode(';', $expectedHeader),
                'received' => implode(';', $headers)
            ], 422);
        }

        $user = Auth::user();
        $boutique_id = $this->getBoutiqueId();
        $count = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) continue;

            // Ensure row matches headers count
            if (count($headers) !== count($row)) {
                continue;
            }
            $data = array_combine($headers, $row);

            $produit = Produit::create([
                'nom' => $data['nom'] ?? 'Produit sans nom',
                'reference' => $data['reference'] ?? null,
                'categorie_id' => $request->input('categorie_id'),
                'description' => $data['description'] ?? '',
                'image' => asset('storage/produit/default.png'),
                'prix_detail' => $data['prix_detail'] ?? $data['prix_vente'] ?? 0,
                'prix_master' => $data['prix_master'] ?? null
            ]);

            Stock::create([
                'produit_id' => $produit->id,
                'boutique_id' => $boutique_id,
                'quantite' => $data['quantite'] ?? 0,
                'prix_achat' => $data['prix_achat'] ?? 0,
                'prix_vente' => $data['prix_vente'] ?? 0,
                'date_reapprovisionnement' => now()->format('Y-m-d')
            ]);

            Inventaire::create([
                'produit_id' => $produit->id,
                'boutique_id' => $boutique_id,
                'user_id' => $user->id,
                'quantite' => $data['quantite'] ?? 0,
                'type' => 'ajout',
                'prix_achat' => $data['prix_achat'] ?? 0,
                'prix_vente' => $data['prix_vente'] ?? 0,
                'remise' => 0,
                'description' => "Importation CSV",
                'date' => now()->format('Y-m-d')
            ]);

            $count++;
        }

        fclose($handle);

        if ($count === 0) {
            return response()->json(['message' => "Aucun produit n'a pu être importé. Vérifiez le contenu du fichier."], 400);
        }

        return response()->json(['message' => "$count produits importés avec succès"], 201);
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
}
