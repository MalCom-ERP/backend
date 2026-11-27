<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\CommandeAchat;
use App\Models\CommandeAchatDetail;
use App\Models\Fournisseur;
use App\Models\Inventaire;
use App\Models\PaiementFournisseur;
use App\Models\Produit;
use App\Models\Stock;
use App\Services\BoutiqueCacheService;
use App\Services\ComptaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FournisseurController extends Controller
{
    private const ACHAT_RELATIONS = [
        'fournisseur',
        'boutique',
        'user',
        'details.produit',
        'paiements.user',
    ];

    /**
     * Retourne les IDs des boutiques accessibles dans le même groupe (boutique principale + filiales/entrepôts)
     */
    private function getGroupBoutiqueIds(?int $boutiqueId): array
    {
        if (!$boutiqueId) {
            return [];
        }
        $boutique = Boutique::find($boutiqueId);
        return $boutique ? $boutique->getGroupBoutiqueIds() : [$boutiqueId];
    }

    /**
     * Liste des fournisseurs avec agrégats d'achats et dettes (Compte 401)
     */
    public function index(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $groupIds = $this->getGroupBoutiqueIds($boutiqueId);
        $fournisseurs = Fournisseur::where(function ($q) use ($groupIds) {
                $q->whereIn('boutique_id', $groupIds)
                    ->orWhereNull('boutique_id');
            })
            ->orderByDesc('is_active')
            ->orderBy('nom')
            ->get();

        // Calcul des statistiques par fournisseur sur la boutique active (ou tout le groupe si demandé)
        $scopeGroup = $request->boolean('scope_group', false);
        $achatsQuery = CommandeAchat::where('statut', '!=', 'annule');
        if ($scopeGroup) {
            $achatsQuery->whereIn('boutique_id', $groupIds);
        } else {
            $achatsQuery->where('boutique_id', $boutiqueId);
        }
        $achats = $achatsQuery->get();

        $parFournisseur = $achats->groupBy('fournisseur_id');
        $today = Carbon::today();

        $enriched = $fournisseurs->map(function ($f) use ($parFournisseur, $today) {
            $list = $parFournisseur->get($f->id, collect());
            $totalAchats = (float) $list->sum('montant_total');
            $totalPaye = (float) $list->sum('montant_paye');
            $detteActuelle = (float) $list->sum('montant_restant');
            $echuesCount = $list->filter(function ($a) use ($today) {
                return (float) $a->montant_restant > 0
                    && $a->date_echeance
                    && Carbon::parse($a->date_echeance)->lt($today);
            })->count();

            $arr = $f->toArray();
            $arr['total_achats'] = round($totalAchats, 2);
            $arr['total_paye'] = round($totalPaye, 2);
            $arr['dette_actuelle'] = round($detteActuelle, 2);
            $arr['commandes_count'] = $list->count();
            $arr['factures_echues_count'] = $echuesCount;
            return $arr;
        });

        $stats = [
            'total_fournisseurs' => $fournisseurs->where('is_active', true)->count(),
            'total_achats_periode' => round((float) $achats->sum('montant_total'), 2),
            'total_dettes_401' => round((float) $achats->sum('montant_restant'), 2),
            'commandes_en_attente' => $achats->where('statut', 'en_commande')->count(),
            'factures_echues_count' => $achats->filter(function ($a) use ($today) {
                return (float) $a->montant_restant > 0
                    && $a->date_echeance
                    && Carbon::parse($a->date_echeance)->lt($today);
            })->count(),
        ];

        return response()->json([
            'fournisseurs' => $enriched->values(),
            'stats' => $stats,
        ], 200);
    }

    /**
     * Création d'un fournisseur
     */
    public function store(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'entreprise' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'adresse' => 'nullable|string|max:255',
            'pays_ville' => 'nullable|string|max:120',
            'type_fournisseur' => 'nullable|string|max:40',
            'nif' => 'nullable|string|max:80',
            'rccm' => 'nullable|string|max:80',
            'delai_paiement_jours' => 'nullable|integer|min:0|max:365',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $fournisseur = Fournisseur::create([
            'boutique_id' => $boutiqueId,
            'nom' => $validated['nom'],
            'entreprise' => $validated['entreprise'] ?? null,
            'telephone' => $validated['telephone'] ?? null,
            'email' => $validated['email'] ?? null,
            'adresse' => $validated['adresse'] ?? null,
            'pays_ville' => $validated['pays_ville'] ?? 'Bamako, Mali',
            'type_fournisseur' => $validated['type_fournisseur'] ?? 'grossiste_local',
            'nif' => $validated['nif'] ?? null,
            'rccm' => $validated['rccm'] ?? null,
            'delai_paiement_jours' => $validated['delai_paiement_jours'] ?? 30,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Fournisseur créé avec succès',
            'fournisseur' => $fournisseur,
        ], 201);
    }

    /**
     * Mise à jour d'un fournisseur
     */
    public function update(Request $request, $id)
    {
        $boutiqueId = $this->getBoutiqueId();
        $groupIds = $this->getGroupBoutiqueIds($boutiqueId);

        $fournisseur = Fournisseur::where(function ($q) use ($groupIds) {
            $q->whereIn('boutique_id', $groupIds)
                ->orWhereNull('boutique_id');
        })->findOrFail($id);

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'entreprise' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'adresse' => 'nullable|string|max:255',
            'pays_ville' => 'nullable|string|max:120',
            'type_fournisseur' => 'nullable|string|max:40',
            'nif' => 'nullable|string|max:80',
            'rccm' => 'nullable|string|max:80',
            'delai_paiement_jours' => 'nullable|integer|min:0|max:365',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $fournisseur->update($validated);

        return response()->json([
            'message' => 'Fournisseur mis à jour avec succès',
            'fournisseur' => $fournisseur->fresh(),
        ], 200);
    }

    /**
     * Suppression logique d'un fournisseur
     */
    public function destroy(Request $request, $id)
    {
        $boutiqueId = $this->getBoutiqueId();
        $groupIds = $this->getGroupBoutiqueIds($boutiqueId);

        $fournisseur = Fournisseur::where(function ($q) use ($groupIds) {
            $q->whereIn('boutique_id', $groupIds)
                ->orWhereNull('boutique_id');
        })->findOrFail($id);
        $fournisseur->delete();

        return response()->json(['message' => 'Fournisseur supprimé avec succès'], 200);
    }

    private function canViewOrPayAchats(?\App\Models\User $user): bool
    {
        return $user !== null
            && ($user->isAdminRole() || strtolower(trim((string) $user->role)) === 'comptable');
    }

    /**
     * Liste des bons de commande & réceptions d'achats de la boutique active
     */
    public function achatsIndex(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$this->canViewOrPayAchats($user)) {
            return response()->json([
                'error' => "Accès refusé. Les achats et réceptions fournisseurs ne sont pas accessibles à votre rôle."
            ], 403);
        }

        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $query = CommandeAchat::with(self::ACHAT_RELATIONS)
            ->where('boutique_id', $boutiqueId);

        if ($request->filled('fournisseur_id')) {
            $query->where('fournisseur_id', (int) $request->input('fournisseur_id'));
        }

        if ($request->filled('statut') && $request->input('statut') !== 'tous') {
            $query->where('statut', $request->input('statut'));
        }

        $paiementStatut = $request->input('paiement_statut');
        $todayStr = Carbon::today()->format('Y-m-d');
        if ($paiementStatut === 'dette') {
            $query->where('montant_restant', '>', 0)->where('statut', '!=', 'annule');
        } elseif ($paiementStatut === 'solde') {
            $query->where('montant_restant', '<=', 0)->where('statut', '!=', 'annule');
        } elseif ($paiementStatut === 'en_retard') {
            $query->where('montant_restant', '>', 0)
                ->where('statut', '!=', 'annule')
                ->whereNotNull('date_echeance')
                ->where('date_echeance', '<', $todayStr);
        }

        $achats = $query->orderByDesc('date_commande')->orderByDesc('id')->get();

        // Stats globales de la boutique active (non filtrées par paiementStatut/statut)
        $allBoutiqueAchats = CommandeAchat::where('boutique_id', $boutiqueId)
            ->where('statut', '!=', 'annule')
            ->get();
        $today = Carbon::today();

        $stats = [
            'total_achats' => round((float) $allBoutiqueAchats->sum('montant_total'), 2),
            'total_frais_approche' => round((float) $allBoutiqueAchats->sum(function ($a) {
                return (float) $a->frais_transport + (float) $a->frais_douane + (float) $a->frais_manutention;
            }), 2),
            'total_dettes_401' => round((float) $allBoutiqueAchats->sum('montant_restant'), 2),
            'commandes_en_attente' => $allBoutiqueAchats->where('statut', 'en_commande')->count(),
            'dettes_en_retard' => round((float) $allBoutiqueAchats->filter(function ($a) use ($today) {
                return (float) $a->montant_restant > 0
                    && $a->date_echeance
                    && Carbon::parse($a->date_echeance)->lt($today);
            })->sum('montant_restant'), 2),
        ];

        return response()->json([
            'achats' => $achats,
            'stats' => $stats,
        ], 200);
    }

    /**
     * Détail d'un achat / bon de commande fournisseur
     */
    public function achatShow(Request $request, $id)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$this->canViewOrPayAchats($user)) {
            return response()->json([
                'error' => "Accès refusé. Les achats et réceptions fournisseurs ne sont pas accessibles à votre rôle."
            ], 403);
        }

        $boutiqueId = $this->getBoutiqueId();
        $achat = CommandeAchat::with(self::ACHAT_RELATIONS)
            ->where('boutique_id', $boutiqueId)
            ->findOrFail($id);

        return response()->json(['achat' => $achat], 200);
    }

    /**
     * Création d'un Bon de Commande (en_commande) ou Réception d'Achat directe (recu)
     */
    public function achatStore(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user || !$user->canManageReappro()) {
            return response()->json([
                'error' => "Accès refusé. Les achats et réceptions de stock sont strictement réservés à l'administrateur."
            ], 403);
        }

        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $validated = $request->validate([
            'fournisseur_id' => 'nullable|exists:fournisseurs,id',
            'statut' => 'nullable|in:en_commande,recu',
            'type_paiement' => 'nullable|in:comptant,credit,mixte',
            'moyen_paiement' => 'nullable|string|max:40',
            'frais_transport' => 'nullable|numeric|min:0',
            'frais_douane' => 'nullable|numeric|min:0',
            'frais_manutention' => 'nullable|numeric|min:0',
            'remise' => 'nullable|numeric|min:0',
            'montant_paye' => 'nullable|numeric|min:0',
            'date_commande' => 'nullable|date',
            'date_echeance' => 'nullable|date',
            'numero_facture_fournisseur' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|exists:produits,id',
            'lignes.*.quantite' => 'nullable|integer|min:0',
            'lignes.*.nombre_colis' => 'nullable|integer|min:0',
            'lignes.*.quantite_conditionnement' => 'nullable|integer|min:1',
            'lignes.*.unite_achat' => 'nullable|string|max:60',
            'lignes.*.prix_achat_unitaire' => 'required|numeric|min:0',
        ]);

        $statut = $validated['statut'] ?? 'recu';
        $typePaiement = $validated['type_paiement'] ?? 'comptant';
        $moyenPaiement = $validated['moyen_paiement'] ?? ($typePaiement === 'credit' ? 'credit' : 'especes');
        $fraisTransport = round((float) ($validated['frais_transport'] ?? 0), 2);
        $fraisDouane = round((float) ($validated['frais_douane'] ?? 0), 2);
        $fraisManutention = round((float) ($validated['frais_manutention'] ?? 0), 2);
        $remise = round((float) ($validated['remise'] ?? 0), 2);
        $dateCommande = $validated['date_commande'] ?? now()->format('Y-m-d');

        // Normaliser et valider chaque ligne
        $preparedLines = [];
        $sousTotalHt = 0;
        $totalQuantite = 0;

        foreach ($validated['lignes'] as $ligne) {
            $produit = Produit::findOrFail($ligne['produit_id']);
            $qCond = (int) ($ligne['quantite_conditionnement'] ?? $produit->quantite_conditionnement ?? 1);
            if ($qCond < 1) {
                $qCond = 1;
            }
            $nbColis = isset($ligne['nombre_colis']) ? (int) $ligne['nombre_colis'] : null;
            $qteUnites = (int) ($ligne['quantite'] ?? 0);
            if ($qteUnites <= 0 && $nbColis && $nbColis > 0) {
                $qteUnites = $nbColis * $qCond;
            }
            if ($qteUnites <= 0) {
                continue;
            }

            $prixAchatUnitaire = round((float) $ligne['prix_achat_unitaire'], 2);
            $montantLigne = round($qteUnites * $prixAchatUnitaire, 2);
            $sousTotalHt += $montantLigne;
            $totalQuantite += $qteUnites;

            $preparedLines[] = [
                'produit_id' => $produit->id,
                'unite_achat' => $ligne['unite_achat'] ?? ($nbColis ? ($produit->unite_conditionnement ?: 'Colis') : ($produit->unite_base ?: 'Unité')),
                'quantite_conditionnement' => $qCond,
                'nombre_colis' => $nbColis,
                'quantite_commandee' => $qteUnites,
                'quantite_recue' => $statut === 'recu' ? $qteUnites : 0,
                'prix_achat_unitaire' => $prixAchatUnitaire,
                'montant_ligne' => $montantLigne,
            ];
        }

        if (count($preparedLines) === 0) {
            return response()->json(['message' => 'Veuillez saisir au moins une quantité valide (> 0).'], 422);
        }

        $totalFrais = round($fraisTransport + $fraisDouane + $fraisManutention, 2);
        $montantTotal = max(0, round($sousTotalHt + $totalFrais - $remise, 2));

        // Répartition proportionnelle des frais d'approche pour le coût de revient débarqué
        foreach ($preparedLines as $idx => $pl) {
            $qte = $pl['quantite_commandee'];
            if ($totalFrais > 0 && $qte > 0) {
                if ($sousTotalHt > 0) {
                    $partFrais = $totalFrais * ($pl['montant_ligne'] / $sousTotalHt);
                } else {
                    $partFrais = $totalQuantite > 0 ? $totalFrais * ($qte / $totalQuantite) : 0;
                }
                $fraisUnitaire = round($partFrais / $qte, 2);
            } else {
                $fraisUnitaire = 0;
            }
            $preparedLines[$idx]['frais_approche_unitaire'] = $fraisUnitaire;
            $preparedLines[$idx]['cout_revient_unitaire'] = round($pl['prix_achat_unitaire'] + $fraisUnitaire, 2);
        }

        if ($typePaiement === 'comptant') {
            $montantPaye = $montantTotal;
            $montantRestant = 0;
        } elseif ($typePaiement === 'credit') {
            $montantPaye = 0;
            $montantRestant = $montantTotal;
        } else {
            $montantPaye = min($montantTotal, max(0, round((float) ($validated['montant_paye'] ?? 0), 2)));
            $montantRestant = max(0, round($montantTotal - $montantPaye, 2));
        }

        $dateEcheance = $validated['date_echeance'] ?? null;
        if ($montantRestant > 0 && empty($dateEcheance)) {
            $fournisseur = !empty($validated['fournisseur_id']) ? Fournisseur::find($validated['fournisseur_id']) : null;
            $delai = $fournisseur ? (int) $fournisseur->delai_paiement_jours : 30;
            $dateEcheance = Carbon::parse($dateCommande)->addDays(max(0, $delai))->format('Y-m-d');
        }

        $achat = DB::transaction(function () use (
            $boutiqueId,
            $user,
            $validated,
            $statut,
            $typePaiement,
            $moyenPaiement,
            $sousTotalHt,
            $fraisTransport,
            $fraisDouane,
            $fraisManutention,
            $remise,
            $montantTotal,
            $montantPaye,
            $montantRestant,
            $dateCommande,
            $dateEcheance,
            $preparedLines
        ) {
            do {
                $reference = 'ACH-' . date('Ymd') . '-' . Str::upper(Str::random(4));
            } while (CommandeAchat::where('reference', $reference)->exists());

            $achat = CommandeAchat::create([
                'reference' => $reference,
                'boutique_id' => $boutiqueId,
                'fournisseur_id' => $validated['fournisseur_id'] ?? null,
                'user_id' => $user?->id,
                'statut' => $statut,
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
                'date_reception' => $statut === 'recu' ? now() : null,
                'numero_facture_fournisseur' => $validated['numero_facture_fournisseur'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            foreach ($preparedLines as $lineData) {
                $lineData['commande_achat_id'] = $achat->id;
                CommandeAchatDetail::create($lineData);
            }

            if ($statut === 'recu') {
                if ($montantPaye > 0) {
                    PaiementFournisseur::create([
                        'commande_achat_id' => $achat->id,
                        'fournisseur_id' => $achat->fournisseur_id,
                        'boutique_id' => $boutiqueId,
                        'user_id' => $user?->id,
                        'montant' => $montantPaye,
                        'moyen_paiement' => $moyenPaiement === 'credit' ? 'especes' : $moyenPaiement,
                        'reference_paiement' => $achat->numero_facture_fournisseur,
                        'date_paiement' => $dateCommande,
                        'note' => $montantRestant > 0 ? 'Acompte initial à la réception' : 'Règlement comptant à la réception',
                    ]);
                }

                self::appliquerReceptionStockEtCompta($achat, $user?->id);
            }

            return $achat;
        });

        return response()->json([
            'message' => $statut === 'recu'
                ? 'Réception enregistrée, stock et comptabilité (401/601/611) mis à jour.'
                : 'Bon de commande fournisseur créé et mis en attente de réception.',
            'achat' => $achat->load(self::ACHAT_RELATIONS),
        ], 201);
    }

    /**
     * Valider la réception en stock d'un bon de commande 'en_commande'
     */
    public function receptionner(Request $request, $id)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user || !$user->canManageReappro()) {
            return response()->json([
                'error' => "Accès refusé. Les achats et réceptions de stock sont strictement réservés à l'administrateur."
            ], 403);
        }

        $boutiqueId = $this->getBoutiqueId();

        $achat = CommandeAchat::with(['details.produit', 'fournisseur', 'paiements'])
            ->where('boutique_id', $boutiqueId)
            ->findOrFail($id);

        if ($achat->statut === 'recu') {
            return response()->json(['message' => 'Ce bon de commande a déjà été réceptionné en stock.'], 400);
        }
        if ($achat->statut === 'annule') {
            return response()->json(['message' => 'Impossible de réceptionner une commande annulée.'], 400);
        }

        DB::transaction(function () use ($achat, $user) {
            foreach ($achat->details as $detail) {
                $detail->update(['quantite_recue' => $detail->quantite_commandee]);
            }

            $achat->update([
                'statut' => 'recu',
                'date_reception' => now(),
            ]);

            if ((float) $achat->montant_paye > 0 && $achat->paiements()->count() === 0) {
                PaiementFournisseur::create([
                    'commande_achat_id' => $achat->id,
                    'fournisseur_id' => $achat->fournisseur_id,
                    'boutique_id' => $achat->boutique_id,
                    'user_id' => $user?->id,
                    'montant' => $achat->montant_paye,
                    'moyen_paiement' => $achat->moyen_paiement === 'credit' ? 'especes' : $achat->moyen_paiement,
                    'reference_paiement' => $achat->numero_facture_fournisseur,
                    'date_paiement' => now()->format('Y-m-d'),
                    'note' => (float) $achat->montant_restant > 0
                        ? 'Acompte enregistré à la réception'
                        : 'Règlement comptant à la réception',
                ]);
            }

            self::appliquerReceptionStockEtCompta($achat->fresh(['details.produit', 'fournisseur']), $user?->id);
        });

        return response()->json([
            'message' => 'Marchandises réceptionnées en stock et comptabilisées avec succès.',
            'achat' => $achat->fresh(self::ACHAT_RELATIONS),
        ], 200);
    }

    /**
     * Enregistrer un règlement sur une dette fournisseur (Compte 401 SYSCOHADA)
     */
    public function enregistrerPaiement(Request $request, $id)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$this->canViewOrPayAchats($user)) {
            return response()->json([
                'error' => "Accès refusé. Le règlement des achats fournisseurs n'est pas accessible à votre rôle."
            ], 403);
        }

        $boutiqueId = $this->getBoutiqueId();

        $achat = CommandeAchat::with(['fournisseur'])
            ->where('boutique_id', $boutiqueId)
            ->findOrFail($id);

        if ($achat->statut === 'annule') {
            return response()->json(['message' => 'Impossible de régler une commande annulée.'], 400);
        }

        if ((float) $achat->montant_restant <= 0) {
            return response()->json(['message' => 'Cette facture fournisseur est déjà entièrement soldée.'], 400);
        }

        $validated = $request->validate([
            'montant' => 'required|numeric|min:1',
            'moyen_paiement' => 'required|string|max:40',
            'reference_paiement' => 'nullable|string|max:120',
            'date_paiement' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]);

        $montant = min(round((float) $validated['montant'], 2), round((float) $achat->montant_restant, 2));
        $datePaiement = $validated['date_paiement'] ?? now()->format('Y-m-d');

        $paiement = DB::transaction(function () use ($achat, $user, $validated, $montant, $datePaiement) {
            $paiement = PaiementFournisseur::create([
                'commande_achat_id' => $achat->id,
                'fournisseur_id' => $achat->fournisseur_id,
                'boutique_id' => $achat->boutique_id,
                'user_id' => $user?->id,
                'montant' => $montant,
                'moyen_paiement' => $validated['moyen_paiement'],
                'reference_paiement' => $validated['reference_paiement'] ?? null,
                'date_paiement' => $datePaiement,
                'note' => $validated['note'] ?? null,
            ]);

            $nouveauPaye = round((float) $achat->montant_paye + $montant, 2);
            $nouveauRestant = max(0, round((float) $achat->montant_total - $nouveauPaye, 2));

            $achat->update([
                'montant_paye' => $nouveauPaye,
                'montant_restant' => $nouveauRestant,
            ]);

            // Écriture SYSCOHADA : Débit 401 / Crédit 571 (ou 521 si virement/chèque)
            app(ComptaService::class)->enregistrerPaiementFournisseur($paiement, $achat);

            return $paiement;
        });

        return response()->json([
            'message' => 'Règlement fournisseur enregistré et imputé au compte 401 avec succès.',
            'paiement' => $paiement->load('user'),
            'achat' => $achat->fresh(self::ACHAT_RELATIONS),
        ], 201);
    }

    /**
     * Applique l'entrée en stock (avec coût de revient unitaire débarqué) et génère l'écriture SYSCOHADA
     */
    public static function appliquerReceptionStockEtCompta(CommandeAchat $achat, ?int $userId = null): void
    {
        $achat->loadMissing(['details.produit', 'fournisseur']);
        $boutiqueId = (int) $achat->boutique_id;
        $dateStr = now()->format('Y-m-d');

        foreach ($achat->details as $detail) {
            $qte = (int) ($detail->quantite_recue > 0 ? $detail->quantite_recue : $detail->quantite_commandee);
            if ($qte <= 0) {
                continue;
            }

            $coutRevient = (float) $detail->cout_revient_unitaire > 0
                ? (float) $detail->cout_revient_unitaire
                : (float) $detail->prix_achat_unitaire;

            $stock = Stock::where('produit_id', $detail->produit_id)
                ->where('boutique_id', $boutiqueId)
                ->first();

            $fallbackPrixVente = $stock
                ? (float) $stock->prix_vente
                : ((float) (Stock::where('produit_id', $detail->produit_id)->value('prix_vente') ?? 0));

            if ($stock) {
                $stock->update([
                    'quantite' => $stock->quantite + $qte,
                    'date_reapprovisionnement' => $dateStr,
                    'prix_achat' => $coutRevient > 0 ? $coutRevient : $stock->prix_achat,
                    'description' => "Réception achat {$achat->reference}",
                ]);
            } else {
                $stock = Stock::create([
                    'produit_id' => $detail->produit_id,
                    'boutique_id' => $boutiqueId,
                    'quantite' => $qte,
                    'date_reapprovisionnement' => $dateStr,
                    'prix_achat' => $coutRevient,
                    'prix_vente' => $fallbackPrixVente,
                    'description' => "Entrée initiale via achat {$achat->reference}",
                ]);
            }

            Inventaire::create([
                'produit_id' => $detail->produit_id,
                'boutique_id' => $boutiqueId,
                'user_id' => $userId ?: $achat->user_id,
                'quantite' => $qte,
                'type' => 'ajout',
                'prix_achat' => $coutRevient,
                'prix_vente' => $fallbackPrixVente,
                'remise' => 0,
                'description' => "Réception fournisseur {$achat->reference} (Coût revient: " . number_format($coutRevient, 0, ',', ' ') . " F)",
                'date' => $dateStr,
            ]);
        }

        app(ComptaService::class)->enregistrerAchatFournisseur($achat);
        BoutiqueCacheService::bump($boutiqueId, ['produits', 'dashboard']);
    }
}
