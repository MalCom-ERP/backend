<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Client;
use App\Models\PaiementCredit;
use App\Models\Vente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    /**
     * Liste complète des clients avec statistiques d'achats, solde dû et statut de fidélité.
     */
    public function index(Request $request)
    {
        $boutique_id = $this->getBoutiqueId();
        if (!$boutique_id) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $query = Client::where('nom', '<>', 'ANONYME');

        // Filtre de recherche
        if ($request->has('search') && !empty($request->search)) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('nom', 'LIKE', "%{$s}%")
                  ->orWhere('telephone', 'LIKE', "%{$s}%")
                  ->orWhere('email', 'LIKE', "%{$s}%")
                  ->orWhere('adresse', 'LIKE', "%{$s}%");
            });
        }

        $clients = $query->orderBy('nom', 'asc')->get();

        // Récupérer les ventes de la boutique pour tous les clients
        $ventesBoutique = Vente::where('boutique_id', $boutique_id)
            ->whereIn('statut', ['validee', 'payee', 'credit'])
            ->get()
            ->groupBy('client_id');

        $now = Carbon::now();

        $clientStats = $clients->map(function ($c) use ($ventesBoutique, $now) {
            $ventes = $ventesBoutique->get($c->id, collect());
            
            $totalAchats = (float)$ventes->sum('montant_total');
            $nbAchats = $ventes->count();
            
            // Solde débiteur (crédit)
            $ventesCredit = $ventes->where('type_paiement', 'credit')->where('montant_restant', '>', 0);
            $totalDette = (float)$ventesCredit->sum('montant_restant');

            // Dernier achat
            $derniereVente = $ventes->sortByDesc('date_vente')->first();
            $dernierAchat = $derniereVente ? $derniereVente->date_vente : null;

            // Retard de paiement / Ancienneté dette
            $hasOverdue = false;
            $maxDaysOverdue = 0;
            foreach ($ventesCredit as $vc) {
                $dateRef = $vc->date_echeance ? Carbon::parse($vc->date_echeance) : Carbon::parse($vc->date_vente);
                $days = $dateRef->diffInDays($now, false);
                if ($days > 0) {
                    $hasOverdue = true;
                    if ($days > $maxDaysOverdue) {
                        $maxDaysOverdue = (int)$days;
                    }
                }
            }

            // Calcul du niveau de fidélité (Tier)
            $points = (int)($c->points_fidelite ?: floor($totalAchats / 1000));
            $tier = 'Standard';
            $tierColor = '#64748b'; // slate
            if ($totalAchats >= 1000000 || $points >= 1000) {
                $tier = 'VIP Platine';
                $tierColor = '#8b5cf6'; // violet
            } elseif ($totalAchats >= 500000 || $points >= 500) {
                $tier = 'VIP Or';
                $tierColor = '#f59e0b'; // amber
            } elseif ($totalAchats >= 200000 || $points >= 200) {
                $tier = 'Argent';
                $tierColor = '#3b82f6'; // blue
            } elseif ($totalAchats >= 50000 || $points >= 50) {
                $tier = 'Bronze';
                $tierColor = '#d97706'; // copper
            }

            return [
                'id' => $c->id,
                'nom' => $c->nom,
                'telephone' => $c->telephone,
                'email' => $c->email,
                'adresse' => $c->adresse,
                'nif' => $c->nif,
                'rccm' => $c->rccm,
                'notes' => $c->notes,
                'plafond_credit' => (float)($c->plafond_credit ?: 0),
                'points_fidelite' => $points,
                'tier' => $tier,
                'tier_color' => $tierColor,
                'total_achats' => $totalAchats,
                'nb_achats' => $nbAchats,
                'total_dette' => $totalDette,
                'has_debt' => $totalDette > 0,
                'has_overdue' => $hasOverdue,
                'max_days_overdue' => $maxDaysOverdue,
                'dernier_achat' => $dernierAchat,
                'created_at' => $c->created_at ? $c->created_at->format('Y-m-d') : null
            ];
        });

        // Filtre débiteur uniquement
        if ($request->has('has_debt') && $request->has_debt == '1') {
            $clientStats = $clientStats->where('has_debt', true)->values();
        }

        // Filtre tier
        if ($request->has('tier') && !empty($request->tier)) {
            $clientStats = $clientStats->where('tier', $request->tier)->values();
        }

        return response()->json($clientStats, 200);
    }

    /**
     * Fiche Client 360° : Coordonnées, Historique des transactions, Crédits et Analyse de dette.
     */
    public function show($id)
    {
        $boutique_id = $this->getBoutiqueId();
        $client = Client::findOrFail($id);
        $boutique = Boutique::find($boutique_id);

        $ventes = Vente::with(['detailVentes.produit', 'user', 'paiementsCredit'])
            ->where('client_id', $client->id)
            ->where('boutique_id', $boutique_id)
            ->orderBy('date_vente', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totalAchats = (float)$ventes->where('statut', '!=', 'annulee')->sum('montant_total');
        $nbAchats = $ventes->where('statut', '!=', 'annulee')->count();
        $panierMoyen = $nbAchats > 0 ? round($totalAchats / $nbAchats, 0) : 0;

        // Paiements de crédit effectués
        $paiements = PaiementCredit::with(['user:id,name', 'vente:id,date_vente'])
            ->whereHas('vente', function ($q) use ($client, $boutique_id) {
                $q->where('client_id', $client->id)
                  ->where('boutique_id', $boutique_id);
            })
            ->orderBy('date_paiement', 'desc')
            ->get();

        // Calcul de la balance âgée de créance
        $now = Carbon::now();
        $detteTotale = 0;
        $aging = [
            'current' => 0,      // < 30 jours
            'days30_60' => 0,    // 30 à 60 jours
            'over60' => 0        // > 60 jours (critique)
        ];

        foreach ($ventes as $v) {
            if ($v->type_paiement === 'credit' && ($v->montant_restant ?? 0) > 0) {
                $solde = (float)$v->montant_restant;
                $detteTotale += $solde;

                $dateRef = $v->date_echeance ? Carbon::parse($v->date_echeance) : Carbon::parse($v->date_vente);
                $days = $dateRef->diffInDays($now, false);

                if ($days <= 30) {
                    $aging['current'] += $solde;
                } elseif ($days <= 60) {
                    $aging['days30_60'] += $solde;
                } else {
                    $aging['over60'] += $solde;
                }
            }
        }

        // Calcul des points de fidélité
        $points = (int)($client->points_fidelite ?: floor($totalAchats / 1000));
        $tier = 'Standard';
        $tierColor = '#64748b';
        if ($totalAchats >= 1000000 || $points >= 1000) {
            $tier = 'VIP Platine';
            $tierColor = '#8b5cf6';
        } elseif ($totalAchats >= 500000 || $points >= 500) {
            $tier = 'VIP Or';
            $tierColor = '#f59e0b';
        } elseif ($totalAchats >= 200000 || $points >= 200) {
            $tier = 'Argent';
            $tierColor = '#3b82f6';
        } elseif ($totalAchats >= 50000 || $points >= 50) {
            $tier = 'Bronze';
            $tierColor = '#d97706';
        }

        // Message WhatsApp préformaté avec détail des factures et dates d'échéance
        $whatsappMessage = null;
        $whatsappLink = null;
        if ($detteTotale > 0 && $client->telephone) {
            $cleanTel = preg_replace('/[^0-9]/', '', $client->telephone);
            if (strlen($cleanTel) === 8) {
                $cleanTel = '223' . $cleanTel;
            }
            $nomBoutique = $boutique ? $boutique->nom : 'Notre Boutique';
            $montantFormate = number_format($detteTotale, 0, ',', ' ') . ' FCFA';

            $lignesFactures = [];
            foreach ($ventesCredit as $vc) {
                $dateV = Carbon::parse($vc->date_vente)->format('d/m/Y');
                $echeanceStr = $vc->date_echeance ? Carbon::parse($vc->date_echeance)->format('d/m/Y') : 'Non spécifiée';
                $soldeFacture = number_format((float)$vc->montant_restant, 0, ',', ' ') . ' FCFA';
                $lignesFactures[] = "📋 Facture #FAC-{$vc->id} du {$dateV}\n   • Montant restant : {$soldeFacture}\n   • Date d'échéance : {$echeanceStr}";
            }

            $detailFacturesText = implode("\n\n", $lignesFactures);

            $text = "Bonjour M/Mme {$client->nom},\n\nNous espérons que vous allez bien. Sauf erreur de notre part, votre compte présente un solde débiteur total en cours de *{$montantFormate}* auprès de *{$nomBoutique}*.\n\n*Détail des factures à régulariser :*\n{$detailFacturesText}\n\nMerci de bien vouloir passer régulariser ce montant à votre convenance.\n\nCordialement,\n*{$nomBoutique}*";
            $whatsappMessage = $text;
            $whatsappLink = "https://wa.me/{$cleanTel}?text=" . urlencode($text);
        }

        return response()->json([
            'client' => array_merge($client->toArray(), [
                'points_fidelite' => $points,
                'tier' => $tier,
                'tier_color' => $tierColor,
                'total_achats' => $totalAchats,
                'nb_achats' => $nbAchats,
                'panier_moyen' => $panierMoyen,
                'total_dette' => $detteTotale,
                'plafond_credit' => (float)($client->plafond_credit ?: 0),
            ]),
            'ventes' => $ventes,
            'paiements' => $paiements,
            'aging' => $aging,
            'whatsapp_message' => $whatsappMessage,
            'whatsapp_link' => $whatsappLink
        ], 200);
    }

    /**
     * Création d'un nouveau client.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:191',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:191',
            'adresse' => 'nullable|string|max:191',
            'plafond_credit' => 'nullable|numeric|min:0',
            'nif' => 'nullable|string|max:50',
            'rccm' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $nom = strtoupper(trim($request->nom));
        $telephone = $request->telephone ? trim($request->telephone) : null;

        // Vérifier si un client existe déjà avec ce numéro
        if ($telephone) {
            $existant = Client::where('telephone', $telephone)->first();
            if ($existant) {
                return response()->json(['message' => "Un client existe déjà avec le numéro {$telephone} ({$existant->nom})."], 422);
            }
        }

        $client = Client::create([
            'nom' => $nom,
            'telephone' => $telephone,
            'email' => $request->email ? trim($request->email) : null,
            'adresse' => $request->adresse,
            'plafond_credit' => $request->plafond_credit ?: 0,
            'points_fidelite' => 0,
            'nif' => $request->nif,
            'rccm' => $request->rccm,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Client enregistré avec succès',
            'client' => $client
        ], 201);
    }

    /**
     * Modification d'un client.
     */
    public function update(Request $request, $id)
    {
        $client = Client::findOrFail($id);

        $request->validate([
            'nom' => 'required|string|max:191',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:191',
            'adresse' => 'nullable|string|max:191',
            'plafond_credit' => 'nullable|numeric|min:0',
            'nif' => 'nullable|string|max:50',
            'rccm' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $telephone = $request->telephone ? trim($request->telephone) : null;
        if ($telephone && $telephone !== $client->telephone) {
            $existant = Client::where('telephone', $telephone)->where('id', '!=', $client->id)->first();
            if ($existant) {
                return response()->json(['message' => "Le numéro {$telephone} est déjà utilisé par {$existant->nom}."], 422);
            }
        }

        $client->update([
            'nom' => strtoupper(trim($request->nom)),
            'telephone' => $telephone,
            'email' => $request->email ? trim($request->email) : null,
            'adresse' => $request->adresse,
            'plafond_credit' => $request->plafond_credit ?? $client->plafond_credit,
            'nif' => $request->nif,
            'rccm' => $request->rccm,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Fiche client mise à jour avec succès',
            'client' => $client
        ], 200);
    }

    /**
     * Suppression d'un client.
     */
    public function destroy($id)
    {
        $client = Client::findOrFail($id);
        
        $hasVentes = Vente::where('client_id', $client->id)->exists();
        if ($hasVentes) {
            return response()->json([
                'message' => 'Impossible de supprimer ce client car il possède un historique de ventes.'
            ], 422);
        }

        $client->delete();

        return response()->json(['message' => 'Client supprimé avec succès'], 200);
    }

    /**
     * Rapport d'ancienneté des créances (Aging Balance des Crédits).
     */
    public function agingReport()
    {
        $boutique_id = $this->getBoutiqueId();
        $boutique = Boutique::find($boutique_id);
        $now = Carbon::now();

        $ventesCredit = Vente::with('client')
            ->where('boutique_id', $boutique_id)
            ->where('type_paiement', 'credit')
            ->where('montant_restant', '>', 0)
            ->get();

        $totalReceivables = 0;
        $totalCurrent = 0;   // <= 30 jours
        $total30to60 = 0;    // 31 - 60 jours
        $totalOver60 = 0;    // > 60 jours

        $debtorsMap = [];

        foreach ($ventesCredit as $v) {
            $solde = (float)$v->montant_restant;
            $totalReceivables += $solde;

            $dateRef = $v->date_echeance ? Carbon::parse($v->date_echeance) : Carbon::parse($v->date_vente);
            $days = $dateRef->diffInDays($now, false);

            if ($days <= 30) {
                $totalCurrent += $solde;
            } elseif ($days <= 60) {
                $total30to60 += $solde;
            } else {
                $totalOver60 += $solde;
            }

            $cid = $v->client_id ?: 0;
            $cNom = $v->client ? $v->client->nom : 'Client Inconnu';
            $cTel = $v->client ? $v->client->telephone : null;

            if (!isset($debtorsMap[$cid])) {
                $cleanTel = $cTel ? preg_replace('/[^0-9]/', '', $cTel) : null;
                if ($cleanTel && strlen($cleanTel) === 8) {
                    $cleanTel = '223' . $cleanTel;
                }

                $debtorsMap[$cid] = [
                    'client_id' => $cid,
                    'nom' => $cNom,
                    'telephone' => $cTel,
                    'clean_tel' => $cleanTel,
                    'total_dette' => 0,
                    'current' => 0,
                    'days30_60' => 0,
                    'over60' => 0,
                    'oldest_date' => $v->date_vente,
                    'max_days' => $days > 0 ? (int)$days : 0,
                    'ventes_count' => 0,
                    'factures' => [],
                ];
            }

            $debtorsMap[$cid]['total_dette'] += $solde;
            $debtorsMap[$cid]['ventes_count']++;

            $dateV = Carbon::parse($v->date_vente)->format('d/m/Y');
            $echeanceStr = $v->date_echeance ? Carbon::parse($v->date_echeance)->format('d/m/Y') : 'Non spécifiée';
            $debtorsMap[$cid]['factures'][] = [
                'id' => $v->id,
                'date_vente' => $dateV,
                'date_echeance' => $echeanceStr,
                'montant_restant' => $solde,
                'montant_formate' => number_format($solde, 0, ',', ' ') . ' FCFA',
                'days' => $days > 0 ? (int)$days : 0,
            ];

            if ($days <= 30) {
                $debtorsMap[$cid]['current'] += $solde;
            } elseif ($days <= 60) {
                $debtorsMap[$cid]['days30_60'] += $solde;
            } else {
                $debtorsMap[$cid]['over60'] += $solde;
            }

            if ($days > $debtorsMap[$cid]['max_days']) {
                $debtorsMap[$cid]['max_days'] = (int)$days;
            }
        }

        // Ajouter message WhatsApp détaillé pour chaque débiteur
        $nomBoutique = $boutique ? $boutique->nom : 'Notre Boutique';
        $debtorsList = array_values(array_map(function ($d) use ($nomBoutique) {
            $montantF = number_format($d['total_dette'], 0, ',', ' ') . ' FCFA';

            $lignes = [];
            foreach ($d['factures'] as $fac) {
                $lignes[] = "📋 Facture #FAC-{$fac['id']} du {$fac['date_vente']}\n   • Montant restant : {$fac['montant_formate']}\n   • Date d'échéance : {$fac['date_echeance']}";
            }
            $detailFactures = implode("\n\n", $lignes);

            $msg = "Bonjour M/Mme {$d['nom']},\n\nSauf erreur de notre part, votre compte client présente un solde débiteur total en cours de *{$montantF}* auprès de *{$nomBoutique}*.\n\n*Détail des factures à régulariser :*\n{$detailFactures}\n\nMerci de bien vouloir procéder à son règlement à votre convenance.\n\nCordialement,\n*{$nomBoutique}*";

            $d['whatsapp_message'] = $msg;
            $d['whatsapp_link'] = $d['clean_tel'] ? ("https://wa.me/{$d['clean_tel']}?text=" . urlencode($msg)) : null;
            return $d;
        }, $debtorsMap));

        // Trier par dette totale décroissante
        usort($debtorsList, fn($a, $b) => $b['total_dette'] <=> $a['total_dette']);

        return response()->json([
            'total_receivables' => round($totalReceivables, 2),
            'total_current' => round($totalCurrent, 2),
            'total_30_60' => round($total30to60, 2),
            'total_over_60' => round($totalOver60, 2),
            'debtors_count' => count($debtorsList),
            'debtors' => $debtorsList
        ], 200);
    }

    /**
     * Top des clients fidèles.
     */
    public function clientFidele()
    {
        $boutique_id = $this->getBoutiqueId();
        
        $clients = Client::where('nom', '<>', 'ANONYME')
            ->whereHas('ventes', fn($q) => $q->where('boutique_id', $boutique_id)->whereIn('statut', ['validee', 'payee', 'credit']))
            ->withSum(['ventes as total_achats' => fn($q) => $q->where('boutique_id', $boutique_id)->whereIn('statut', ['validee', 'payee', 'credit'])], 'montant_total')
            ->withCount(['ventes as nb_achats' => fn($q) => $q->where('boutique_id', $boutique_id)->whereIn('statut', ['validee', 'payee', 'credit'])])
            ->orderByDesc('total_achats')
            ->limit(10)
            ->get();

        $ranked = $clients->map(function ($c, $idx) {
            $total = (float)$c->total_achats;
            $points = (int)($c->points_fidelite ?: floor($total / 1000));
            $tier = ($total >= 1000000 || $points >= 1000) ? 'VIP Platine' : (($total >= 500000) ? 'VIP Or' : 'Argent');

            return [
                'rank' => $idx + 1,
                'id' => $c->id,
                'nom' => $c->nom,
                'telephone' => $c->telephone,
                'total_achats' => $total,
                'nb_achats' => $c->nb_achats,
                'points_fidelite' => $points,
                'tier' => $tier
            ];
        });

        return response()->json($ranked, 200);
    }

    public function clientAnnee($annee)
    {
        return $this->index(request());
    }
}
