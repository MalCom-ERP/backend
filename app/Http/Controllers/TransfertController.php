<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Inventaire;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Transfert;
use App\Models\TransfertDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransfertController extends Controller
{
    /**
     * Liste des transferts de la boutique courante (entrants et sortants).
     */
    public function index(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $query = Transfert::with(['boutiqueSource:id,nom,adresse,telephone', 'boutiqueDest:id,nom,adresse,telephone', 'auteur:id,name', 'recepteur:id,name', 'details.produit:id,nom,reference'])
            ->where(function ($q) use ($boutiqueId) {
                $q->where('boutique_source_id', $boutiqueId)
                  ->orWhere('boutique_dest_id', $boutiqueId);
            });

        // Filtre type (sortant / entrant)
        if ($request->has('type')) {
            if ($request->type === 'sortant') {
                $query->where('boutique_source_id', $boutiqueId);
            } elseif ($request->type === 'entrant') {
                $query->where('boutique_dest_id', $boutiqueId);
            }
        }

        // Filtre statut
        if ($request->has('statut') && !empty($request->statut)) {
            $query->where('statut', $request->statut);
        }

        $transferts = $query->orderBy('date_transfert', 'desc')->paginate($request->input('per_page', 15));

        // Ajouter l'indicateur direction pour l'interface
        $transferts->getCollection()->transform(function ($t) use ($boutiqueId) {
            $t->direction = ($t->boutique_source_id == $boutiqueId) ? 'sortant' : 'entrant';
            return $t;
        });

        return response()->json($transferts, 200);
    }

    /**
     * Détail d'un transfert avec ses articles.
     */
    public function show($id)
    {
        $boutiqueId = $this->getBoutiqueId();
        $transfert = Transfert::with([
            'boutiqueSource:id,nom,adresse,telephone,email',
            'boutiqueDest:id,nom,adresse,telephone,email',
            'auteur:id,name,email',
            'recepteur:id,name,email',
            'details.produit:id,nom,reference,image'
        ])->findOrFail($id);

        // Vérifier l'accès
        if ($transfert->boutique_source_id != $boutiqueId && $transfert->boutique_dest_id != $boutiqueId) {
            $user = Auth::user();
            if (!in_array($user->role, ['admin', 'admin1'])) {
                return response()->json(['message' => 'Accès non autorisé à ce transfert'], 403);
            }
        }

        $transfert->direction = ($transfert->boutique_source_id == $boutiqueId) ? 'sortant' : 'entrant';

        return response()->json($transfert, 200);
    }

    /**
     * Enregistrer et exécuter un nouveau transfert inter-boutiques.
     */
    public function store(Request $request)
    {
        $sourceBoutiqueId = $this->getBoutiqueId();
        if (!$sourceBoutiqueId) {
            return response()->json(['message' => 'Boutique source non identifiée'], 400);
        }

        $request->validate([
            'boutique_dest_id' => 'required|exists:boutiques,id',
            'articles' => 'required|array|min:1',
            'articles.*.produit_id' => 'required|exists:produits,id',
            'articles.*.quantite' => 'required|integer|min:1',
            'note' => 'nullable|string|max:500'
        ]);

        $destBoutiqueId = (int)$request->boutique_dest_id;
        if ($sourceBoutiqueId === $destBoutiqueId) {
            return response()->json(['message' => 'La boutique de destination doit être différente de la boutique source.'], 422);
        }

        $sourceBoutique = Boutique::findOrFail($sourceBoutiqueId);
        $destBoutique = Boutique::findOrFail($destBoutiqueId);
        $user = Auth::user();

        DB::beginTransaction();
        try {
            // 1. Génération du numéro de transfert unique
            $datePrefix = now()->format('Ymd');
            $countToday = Transfert::whereDate('created_at', now()->toDateString())->count() + 1;
            $numeroTransfert = 'TRF-' . $datePrefix . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            $totalArticles = count($request->articles);
            $totalQuantite = 0;
            $valeurTotale = 0;

            // 2. Création de l'entête de transfert
            $transfert = Transfert::create([
                'numero_transfert' => $numeroTransfert,
                'boutique_source_id' => $sourceBoutiqueId,
                'boutique_dest_id' => $destBoutiqueId,
                'user_id' => $user->id,
                'statut' => 'valide',
                'date_transfert' => now(),
                'date_reception' => now(), // Instantané dans notre modèle direct
                'note' => $request->note,
                'total_articles' => $totalArticles,
                'total_quantite' => 0,
                'valeur_totale' => 0
            ]);

            // 3. Traitement de chaque article avec vérification stricte du stock
            foreach ($request->articles as $art) {
                $produitId = (int)$art['produit_id'];
                $qte = (int)$art['quantite'];

                $stockSource = Stock::where('produit_id', $produitId)
                    ->where('boutique_id', $sourceBoutiqueId)
                    ->lockForUpdate()
                    ->first();

                $produit = Produit::find($produitId);
                $nomProd = $produit ? $produit->nom : "Produit #$produitId";

                if (!$stockSource || $stockSource->quantite < $qte) {
                    $dispo = $stockSource ? $stockSource->quantite : 0;
                    throw new \Exception("Stock insuffisant pour '$nomProd' dans la boutique source. Disponible : $dispo, Demandé : $qte.");
                }

                $prixAchat = (float)($stockSource->prix_achat ?: 0);
                $prixVente = (float)($stockSource->prix_vente ?: 0);
                $lineValeur = $prixAchat * $qte;

                // A. Décrémentation source
                $stockSource->quantite -= $qte;
                $stockSource->save();

                // Journal Inventaire Source
                Inventaire::create([
                    'produit_id' => $produitId,
                    'boutique_id' => $sourceBoutiqueId,
                    'user_id' => $user->id,
                    'quantite' => $qte,
                    'type' => 'transfert_sortant',
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                    'description' => "Transfert sortant vers {$destBoutique->nom} ($numeroTransfert)",
                    'date' => now()->format('Y-m-d H:i:s')
                ]);

                // B. Incrémentation destination
                $stockDest = Stock::where('produit_id', $produitId)
                    ->where('boutique_id', $destBoutiqueId)
                    ->lockForUpdate()
                    ->first();

                if (!$stockDest) {
                    $stockDest = Stock::create([
                        'produit_id' => $produitId,
                        'boutique_id' => $destBoutiqueId,
                        'quantite' => $qte,
                        'prix_achat' => $prixAchat,
                        'prix_vente' => $prixVente
                    ]);
                } else {
                    $stockDest->quantite += $qte;
                    // Mettre à jour les prix de référence si manquants
                    if (!$stockDest->prix_achat || $stockDest->prix_achat == 0) {
                        $stockDest->prix_achat = $prixAchat;
                    }
                    if (!$stockDest->prix_vente || $stockDest->prix_vente == 0) {
                        $stockDest->prix_vente = $prixVente;
                    }
                    $stockDest->save();
                }

                // Journal Inventaire Destination
                Inventaire::create([
                    'produit_id' => $produitId,
                    'boutique_id' => $destBoutiqueId,
                    'user_id' => $user->id,
                    'quantite' => $qte,
                    'type' => 'transfert_entrant',
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                    'description' => "Transfert entrant depuis {$sourceBoutique->nom} ($numeroTransfert)",
                    'date' => now()->format('Y-m-d H:i:s')
                ]);

                // C. Ligne de détail du transfert
                TransfertDetail::create([
                    'transfert_id' => $transfert->id,
                    'produit_id' => $produitId,
                    'quantite' => $qte,
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente
                ]);

                $totalQuantite += $qte;
                $valeurTotale += $lineValeur;
            }

            // 4. Mettre à jour les totaux du transfert
            $transfert->total_quantite = $totalQuantite;
            $transfert->valeur_totale = $valeurTotale;
            $transfert->save();

            DB::commit();

            return response()->json([
                'message' => 'Transfert inter-boutiques effectué avec succès !',
                'transfert' => $transfert->load(['boutiqueSource', 'boutiqueDest', 'details.produit'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Liste des autres boutiques accessibles pour transférer du stock.
     */
    public function getAccessibleBoutiques()
    {
        $currentBoutiqueId = $this->getBoutiqueId();
        $user = Auth::user();

        $query = Boutique::where('is_active', true)
            ->where('id', '!=', $currentBoutiqueId);

        // Si l'utilisateur n'est pas super admin, on restreint aux boutiques de son propriétaire
        if (!in_array($user->role, ['admin', 'admin1'])) {
            $creatorId = Boutique::find($currentBoutiqueId)?->user_id;
            if ($creatorId) {
                $query->where('user_id', $creatorId);
            }
        }

        $boutiques = $query->select('id', 'nom', 'adresse', 'telephone')->get();

        return response()->json($boutiques, 200);
    }

    /**
     * Valorisation intégrale du stock de la boutique courante (FIFO / PUMP).
     */
    public function stockValuation()
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $stocks = Stock::with(['produit.categorie'])
            ->where('boutique_id', $boutiqueId)
            ->get();

        $totalArticlesDistincts = $stocks->count();
        $totalQuantitePhysique = 0;
        $totalValeurAchat = 0;
        $totalValeurVente = 0;
        $articlesEnRupture = 0;
        $articlesStockFaible = 0; // Moins de 5 unités

        $categoriesBreakdown = [];

        foreach ($stocks as $s) {
            $qte = (int)$s->quantite;
            $prixAchat = (float)($s->prix_achat ?: 0);
            $prixVente = (float)($s->prix_vente ?: 0);

            $totalQuantitePhysique += $qte;
            $totalValeurAchat += ($qte * $prixAchat);
            $totalValeurVente += ($qte * $prixVente);

            if ($qte <= 0) {
                $articlesEnRupture++;
            } elseif ($qte <= 5) {
                $articlesStockFaible++;
            }

            // Répartition par catégorie
            $catNom = $s->produit?->categorie?->nom ?: 'Non catégorisé';
            if (!isset($categoriesBreakdown[$catNom])) {
                $categoriesBreakdown[$catNom] = [
                    'nom' => $catNom,
                    'articles_count' => 0,
                    'quantite_totale' => 0,
                    'valeur_achat' => 0,
                    'valeur_vente' => 0
                ];
            }
            $categoriesBreakdown[$catNom]['articles_count']++;
            $categoriesBreakdown[$catNom]['quantite_totale'] += $qte;
            $categoriesBreakdown[$catNom]['valeur_achat'] += ($qte * $prixAchat);
            $categoriesBreakdown[$catNom]['valeur_vente'] += ($qte * $prixVente);
        }

        $margeBruteTotale = $totalValeurVente - $totalValeurAchat;
        $tauxMargeMoyen = $totalValeurAchat > 0 ? round(($margeBruteTotale / $totalValeurAchat) * 100, 1) : 0;

        return response()->json([
            'total_articles_distincts' => $totalArticlesDistincts,
            'total_quantite_physique' => $totalQuantitePhysique,
            'total_valeur_achat' => round($totalValeurAchat, 2),
            'total_valeur_vente' => round($totalValeurVente, 2),
            'marge_brute_potentielle' => round($margeBruteTotale, 2),
            'taux_marge_moyen' => $tauxMargeMoyen,
            'articles_en_rupture' => $articlesEnRupture,
            'articles_stock_faible' => $articlesStockFaible,
            'categories' => array_values($categoriesBreakdown)
        ], 200);
    }

    /**
     * Régularisation d'inventaire physique & gestion des écarts de stock.
     */
    public function stockAdjustment(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $request->validate([
            'produit_id' => 'required|exists:produits,id',
            'quantite_physique' => 'required|integer|min:0',
            'motif' => 'required|string|max:100',
            'note' => 'nullable|string|max:500'
        ]);

        $produitId = (int)$request->produit_id;
        $qtePhysique = (int)$request->quantite_physique;
        $motif = $request->motif;
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $stock = Stock::where('produit_id', $produitId)
                ->where('boutique_id', $boutiqueId)
                ->lockForUpdate()
                ->first();

            $produit = Produit::findOrFail($produitId);

            if (!$stock) {
                $stock = Stock::create([
                    'produit_id' => $produitId,
                    'boutique_id' => $boutiqueId,
                    'quantite' => 0,
                    'prix_achat' => $produit->prix_master ?: 0,
                    'prix_vente' => $produit->prix_detail ?: 0
                ]);
            }

            $qteTheorique = (int)$stock->quantite;
            $ecart = $qtePhysique - $qteTheorique;

            if ($ecart === 0) {
                return response()->json([
                    'message' => 'Aucun écart constaté entre le stock théorique et physique.',
                    'nouveau_stock' => $qteTheorique
                ], 200);
            }

            // Mise à jour de la quantité réelle constatée
            $stock->quantite = $qtePhysique;
            $stock->save();

            // Journaliser le mouvement d'ajustement
            $typeMouvement = $ecart > 0 ? 'ajustement_positif' : 'ajustement_negatif';
            $signe = $ecart > 0 ? "+$ecart" : "$ecart";

            Inventaire::create([
                'produit_id' => $produitId,
                'boutique_id' => $boutiqueId,
                'user_id' => $user->id,
                'quantite' => abs($ecart),
                'type' => $typeMouvement,
                'prix_achat' => $stock->prix_achat,
                'prix_vente' => $stock->prix_vente,
                'description' => "Inventaire Physique : $motif (Écart : $signe unités) " . ($request->note ? "— " . $request->note : ""),
                'date' => now()->format('Y-m-d H:i:s')
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock régularisé avec succès !',
                'produit' => $produit->nom,
                'stock_theorique' => $qteTheorique,
                'stock_physique' => $qtePhysique,
                'ecart' => $ecart,
                'motif' => $motif
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
