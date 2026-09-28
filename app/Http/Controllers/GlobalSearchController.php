<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Client;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $rawQuery = trim($request->input('q', ''));
        $explicitType = $request->input('type');
        $boutiqueId = $this->getBoutiqueId();

        try {

            $mode = 'all';
            $query = $rawQuery;

            // Prefix detection
            if (str_starts_with($rawQuery, '#')) {
                $mode = 'produits';
                $query = trim(substr($rawQuery, 1));
            } elseif (str_starts_with($rawQuery, '@')) {
                $mode = 'clients';
                $query = trim(substr($rawQuery, 1));
            } elseif (str_starts_with($rawQuery, '!') || str_starts_with($rawQuery, '$')) {
                $mode = 'factures';
                $query = trim(substr($rawQuery, 1));
            } elseif (stripos($rawQuery, 'fac') === 0) {
                $mode = 'factures';
            }

            if ($explicitType && in_array($explicitType, ['produits', 'clients', 'factures'])) {
                $mode = $explicitType;
            }

            $results = [
                'mode' => $mode,
                'query' => $query,
                'produits' => [],
                'clients' => [],
                'factures' => [],
                'total_count' => 0
            ];

            // ── 1. Search Produits ───────────────────────────────────────────
            if ($mode === 'all' || $mode === 'produits') {
                $prodLimit = ($mode === 'produits') ? 20 : 6;
                $currentBoutique = $boutiqueId ? \App\Models\Boutique::find($boutiqueId) : null;
                $groupBoutiques = $currentBoutique ? $currentBoutique->getGroupBoutiques()->where('id', '!=', $boutiqueId) : collect();

                $prodBuilder = Produit::with(['categorie', 'stock' => function ($q) use ($boutiqueId) {
                    if ($boutiqueId) {
                        $q->where('boutique_id', $boutiqueId);
                    }
                }]);

                $groupBoutiqueIds = $currentBoutique ? $currentBoutique->getGroupBoutiqueIds() : ($boutiqueId ? [$boutiqueId] : []);
                if (!empty($groupBoutiqueIds)) {
                    $prodBuilder->whereHas('stocks', function ($q) use ($groupBoutiqueIds) {
                        $q->whereIn('boutique_id', $groupBoutiqueIds);
                    });
                }

                if (!empty($query)) {
                    $prodBuilder->where(function ($q) use ($query) {
                        $q->where('nom', 'LIKE', "%{$query}%")
                        ->orWhere('reference', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%")
                        ->orWhereHas('categorie', function ($catQ) use ($query) {
                            $catQ->where('nom', 'LIKE', "%{$query}%");
                        });
                    });
                }

                $produits = $prodBuilder->orderBy('nom', 'asc')->limit($prodLimit)->get();

                $results['produits'] = $produits->map(function ($p) use ($groupBoutiques) {
                    $stockQte = $p->stock ? (float)$p->stock->quantite : 0;
                    $seuil = $p->stock ? (float)($p->stock->seuil_alerte ?? 5) : 5;

                    $status = 'disponible';
                    if ($stockQte <= 0) {
                        $status = 'rupture';
                    } elseif ($stockQte <= $seuil) {
                        $status = 'faible';
                    }

                    // Aperçu d'orientation : si en rupture locale, chercher les autres filiales dispos avec adresse et contact
                    $filialesApercu = [];
                    if ($status === 'rupture' && $groupBoutiques->isNotEmpty()) {
                        foreach ($groupBoutiques as $gb) {
                            $otherStock = \App\Models\Stock::where('produit_id', $p->id)
                                ->where('boutique_id', $gb->id)
                                ->where('quantite', '>', 0)
                                ->first();

                            if (!$otherStock) {
                                $matchingId = Produit::where('id', '!=', $p->id)
                                    ->where(function ($q) use ($p) {
                                        if (!empty($p->reference)) {
                                            $q->where('reference', $p->reference);
                                        }
                                        $q->orWhereRaw('LOWER(TRIM(nom)) = ?', [strtolower(trim($p->nom))]);
                                    })
                                    ->pluck('id');

                                if ($matchingId->isNotEmpty()) {
                                    $otherStock = \App\Models\Stock::whereIn('produit_id', $matchingId)
                                        ->where('boutique_id', $gb->id)
                                        ->where('quantite', '>', 0)
                                        ->first();
                                }
                            }

                            if ($otherStock && $otherStock->quantite > 0) {
                                $filialesApercu[] = [
                                    'boutique_id' => $gb->id,
                                    'nom' => $gb->nom,
                                    'adresse' => $gb->adresse ?: 'Adresse non renseignée',
                                    'telephone' => $gb->telephone ?: 'Non renseigné',
                                    'quantite' => (float)$otherStock->quantite,
                                    'prix_vente' => (float)($otherStock->prix_vente ?: $p->prix_detail)
                                ];
                            }
                        }
                    }

                    return [
                        'id' => $p->id,
                        'nom' => $p->nom,
                        'reference' => $p->reference ?: "REF-{$p->id}",
                        'categorie_nom' => $p->categorie ? $p->categorie->nom : 'Général',
                        'prix_detail' => (float)$p->prix_detail,
                        'prix_master' => (float)$p->prix_master,
                        'stock_restant' => $stockQte,
                        'stock_status' => $status,
                        'image' => $p->image ?: 'assets/img/produit/default.png',
                        'nature_slug' => $p->config['nature'] ?? 'default',
                        'filiales_disponibles' => $filialesApercu,
                        'total_stock_filiales' => array_sum(array_column($filialesApercu, 'quantite')),
                    ];
                });
            }

            // ── 2. Search Clients ────────────────────────────────────────────
            if ($mode === 'all' || $mode === 'clients') {
                $clientLimit = ($mode === 'clients') ? 20 : 6;
                $clientBuilder = Client::query();

                if (!empty($query)) {
                    $clientBuilder->where(function ($q) use ($query) {
                        $q->where('nom', 'LIKE', "%{$query}%")
                        ->orWhere('telephone', 'LIKE', "%{$query}%")
                        ->orWhere('adresse', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%");
                    });
                }

                // Exclude anonyme if search is empty or not explicitly queried
                if (empty($query)) {
                    $clientBuilder->where('nom', '<>', 'ANONYME');
                }

                if ($boutiqueId) {
                    $clientBuilder->whereHas('ventes', function ($q) use ($boutiqueId) {
                        $q->where('boutique_id', $boutiqueId);
                    });

                    $clientBuilder->withSum(['ventes as total_dette' => function ($q) use ($boutiqueId) {
                        $q->where('boutique_id', $boutiqueId)
                        ->where('type_paiement', 'credit');
                    }], 'montant_restant');

                    $clientBuilder->withCount(['ventes as count_credits' => function ($q) use ($boutiqueId) {
                        $q->where('boutique_id', $boutiqueId)
                        ->where('type_paiement', 'credit')
                        ->where('montant_restant', '>', 0);
                    }]);
                } else {
                    $clientBuilder->withSum(['ventes as total_dette' => function ($q) {
                        $q->where('type_paiement', 'credit');
                    }], 'montant_restant');

                    $clientBuilder->withCount(['ventes as count_credits' => function ($q) {
                        $q->where('type_paiement', 'credit')
                        ->where('montant_restant', '>', 0);
                    }]);
                }

                $clients = $clientBuilder->orderByDesc('total_dette')->limit($clientLimit)->get();

                $results['clients'] = $clients->map(function ($c) {
                    $dette = (float)($c->total_dette ?? 0);
                    return [
                        'id' => $c->id,
                        'nom' => $c->nom,
                        'telephone' => $c->telephone ?: 'Non renseigné',
                        'adresse' => $c->adresse ?: '',
                        'total_dette' => $dette,
                        'has_credit' => $dette > 0,
                        'count_credits' => (int)($c->count_credits ?? 0)
                    ];
                });
            }

            // ── 3. Search Factures / Ventes ──────────────────────────────────
            if ($mode === 'all' || $mode === 'factures') {
                $facLimit = ($mode === 'factures') ? 20 : 6;
                $venteBuilder = Vente::with(['client', 'boutique']);

                if ($boutiqueId) {
                    $venteBuilder->where('boutique_id', $boutiqueId);
                }

                if (!empty($query)) {
                    // If query is numeric, search exact ID or prefix
                    $cleanNum = preg_replace('/[^0-9]/', '', $query);
                    $venteBuilder->where(function ($q) use ($query, $cleanNum) {
                        if (!empty($cleanNum)) {
                            $q->where('id', (int)$cleanNum);
                        }
                        $q->orWhereHas('client', function ($clientQ) use ($query) {
                            $clientQ->where('nom', 'LIKE', "%{$query}%");
                        });
                    });
                }

                $ventes = $venteBuilder->orderBy('date_vente', 'desc')->limit($facLimit)->get();

                $results['factures'] = $ventes->map(function ($v) {
                    $year = Carbon::parse($v->date_vente ?? $v->created_at)->format('Y');
                    $numFacture = "FAC-{$year}-" . str_pad($v->id, 5, '0', STR_PAD_LEFT);
                    $restant = (float)($v->montant_restant ?? 0);

                    return [
                        'id' => $v->id,
                        'numero' => $numFacture,
                        'date' => Carbon::parse($v->date_vente ?? $v->created_at)->format('d/m/Y'),
                        'client_nom' => $v->client ? $v->client->nom : 'Client de passage',
                        'client_id' => $v->client_id,
                        'montant_total' => (float)$v->montant_total,
                        'montant_restant' => $restant,
                        'type_paiement' => $v->type_paiement,
                        'statut' => ($v->type_paiement === 'credit' && $restant > 0) ? 'credit' : 'paye'
                    ];
                });
            }

            $results['total_count'] = count($results['produits']) + count($results['clients']) + count($results['factures']);

            return response()->json($results, 200);
        } 
        catch (\Throwable $e) {
            Log::error('Global search error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'mode' => 'all',
                'query' => $rawQuery,
                'produits' => [],
                'clients' => [],
                'factures' => [],
                'total_count' => 0,
                'error' => $e->getMessage()
            ], 200);
        }
    }
}
