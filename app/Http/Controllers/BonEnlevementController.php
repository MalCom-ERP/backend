<?php

namespace App\Http\Controllers;

use App\Models\BonEnlevement;
use App\Models\BonEnlevementDetail;
use App\Models\Boutique;
use App\Models\Inventaire;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BonEnlevementController extends Controller
{
    private const RELATIONS = [
        'vente',
        'boutiqueEmettrice',
        'entrepot',
        'client',
        'user',
        'validePar',
        'details.produit',
    ];

    /**
     * Liste des bons d'enlèvement liés à la boutique active (émis par elle OU à retirer chez elle).
     */
    public function index(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $statut = $request->input('statut');

        $baseQuery = BonEnlevement::with(self::RELATIONS)
            ->where(function ($q) use ($boutiqueId) {
                $q->where('boutique_emettrice_id', $boutiqueId)
                  ->orWhere('entrepot_id', $boutiqueId);
            });

        $allForStats = (clone $baseQuery)->get();
        $stats = [
            'en_attente' => $allForStats->where('statut', 'en_attente')->count(),
            'partiel'    => $allForStats->where('statut', 'partiel')->count(),
            'livre'      => $allForStats->where('statut', 'livre')->count(),
            'annule'     => $allForStats->where('statut', 'annule')->count(),
            'total'      => $allForStats->count(),
        ];

        if ($statut && in_array($statut, ['en_attente', 'partiel', 'livre', 'annule'])) {
            $baseQuery->where('statut', $statut);
        }

        $bons = $baseQuery->orderByDesc('id')->get();

        return response()->json([
            'bons' => $bons,
            'stats' => $stats,
            'active_boutique_id' => (int) $boutiqueId,
        ], 200);
    }

    /**
     * Détail d'un bon d'enlèvement.
     */
    public function show($id)
    {
        $bon = BonEnlevement::with(self::RELATIONS)->findOrFail($id);
        return response()->json($bon, 200);
    }

    /**
     * Génère une référence unique séquentielle BE-YYYYMMDD-XXXX.
     */
    public static function generateReference(): string
    {
        $nextId = (BonEnlevement::max('id') ?? 0) + 1;
        return sprintf('BE-%s-%04d', now()->format('Ymd'), $nextId);
    }

    /**
     * Création d'un bon d'enlèvement (depuis une vente existante ou bon de sortie direct).
     */
    public function store(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique non identifiée'], 400);
        }

        $request->validate([
            'vente_id' => 'nullable|integer|exists:ventes,id',
            'entrepot_id' => 'nullable|integer|exists:boutiques,id',
            'client_id' => 'nullable|integer|exists:clients,id',
            'recepteur_nom' => 'nullable|string|max:150',
            'recepteur_telephone' => 'nullable|string|max:40',
            'chauffeur_immatriculation' => 'nullable|string|max:100',
            'motif_ou_note' => 'nullable|string|max:500',
            'lignes' => 'nullable|array',
            'lignes.*.produit_id' => 'required_with:lignes|integer|exists:produits,id',
            'lignes.*.quantite' => 'required_with:lignes|integer|min:1',
        ]);

        $user = Auth::user();
        $entrepotId = (int) ($request->input('entrepot_id') ?: $boutiqueId);
        Boutique::findOrFail($entrepotId);

        DB::beginTransaction();
        try {
            if ($request->filled('vente_id')) {
                $vente = Vente::with(['detailVentes.produit', 'client'])->findOrFail($request->vente_id);

                if ($vente->statut === 'proforma' || $vente->type_paiement === 'proforma') {
                    throw new \Exception("Impossible d'émettre un bon d'enlèvement sur un devis pro-forma.");
                }

                $existingActive = BonEnlevement::where('vente_id', $vente->id)
                    ->where('statut', '!=', 'annule')
                    ->first();
                if ($existingActive) {
                    throw new \Exception("Un bon d'enlèvement actif ({$existingActive->reference}) existe déjà pour cette vente.");
                }

                $emitterId = (int) $vente->boutique_id;
                $sameBoutique = ($entrepotId === $emitterId);
                $stockDejaDeduit = $sameBoutique;

                // Si l'enlèvement se fera finalement dans un autre dépôt que la boutique vendeuse,
                // la vente initiale avait déduit le stock de la boutique vendeuse : on le réintègre.
                if (!$sameBoutique) {
                    foreach ($vente->detailVentes as $dv) {
                        $stockEmitter = Stock::where('produit_id', $dv->produit_id)
                            ->where('boutique_id', $emitterId)
                            ->lockForUpdate()
                            ->first();
                        if ($stockEmitter) {
                            $stockEmitter->quantite += (int) $dv->quantite;
                            $stockEmitter->save();
                        }

                        Inventaire::create([
                            'produit_id' => $dv->produit_id,
                            'boutique_id' => $emitterId,
                            'user_id' => $user->id,
                            'vente_id' => $vente->id,
                            'quantite' => (int) $dv->quantite,
                            'type' => 'ajout',
                            'prix_achat' => $stockEmitter ? $stockEmitter->prix_achat : 0,
                            'prix_vente' => $dv->prix_unitaire ?? 0,
                            'description' => "Réintégration boutique : retrait transféré à l'entrepôt (Vente #{$vente->id})",
                            'date' => now()->format('Y-m-d'),
                        ]);
                    }
                }

                $bon = BonEnlevement::create([
                    'reference' => self::generateReference(),
                    'vente_id' => $vente->id,
                    'boutique_emettrice_id' => $emitterId,
                    'entrepot_id' => $entrepotId,
                    'client_id' => $vente->client_id,
                    'user_id' => $user->id,
                    'statut' => 'en_attente',
                    'stock_deja_deduit' => $stockDejaDeduit,
                    'recepteur_nom' => $request->input('recepteur_nom') ?: ($vente->client?->nom !== 'ANONYME' ? $vente->client?->nom : null),
                    'recepteur_telephone' => $request->input('recepteur_telephone') ?: $vente->client?->telephone,
                    'chauffeur_immatriculation' => $request->input('chauffeur_immatriculation'),
                    'motif_ou_note' => $request->input('motif_ou_note'),
                    'date_emission' => now(),
                ]);

                foreach ($vente->detailVentes as $dv) {
                    $emplacement = Stock::where('produit_id', $dv->produit_id)
                        ->where('boutique_id', $entrepotId)
                        ->value('emplacement');

                    BonEnlevementDetail::create([
                        'bon_enlevement_id' => $bon->id,
                        'produit_id' => $dv->produit_id,
                        'quantite_commandee' => (int) $dv->quantite,
                        'quantite_livree' => 0,
                        'emplacement' => $emplacement,
                    ]);
                }
            } else {
                $lignes = $request->input('lignes', []);
                if (empty($lignes)) {
                    throw new \Exception("Veuillez sélectionner une vente ou au moins un article pour créer le bon d'enlèvement.");
                }

                $bon = BonEnlevement::create([
                    'reference' => self::generateReference(),
                    'vente_id' => null,
                    'boutique_emettrice_id' => (int) $boutiqueId,
                    'entrepot_id' => $entrepotId,
                    'client_id' => $request->input('client_id'),
                    'user_id' => $user->id,
                    'statut' => 'en_attente',
                    'stock_deja_deduit' => false,
                    'recepteur_nom' => $request->input('recepteur_nom'),
                    'recepteur_telephone' => $request->input('recepteur_telephone'),
                    'chauffeur_immatriculation' => $request->input('chauffeur_immatriculation'),
                    'motif_ou_note' => $request->input('motif_ou_note'),
                    'date_emission' => now(),
                ]);

                foreach ($lignes as $ligne) {
                    $produitId = (int) $ligne['produit_id'];
                    $qte = (int) $ligne['quantite'];
                    $emplacement = Stock::where('produit_id', $produitId)
                        ->where('boutique_id', $entrepotId)
                        ->value('emplacement');

                    BonEnlevementDetail::create([
                        'bon_enlevement_id' => $bon->id,
                        'produit_id' => $produitId,
                        'quantite_commandee' => $qte,
                        'quantite_livree' => 0,
                        'emplacement' => $emplacement,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Bon d'enlèvement {$bon->reference} émis avec succès.",
                'bon' => $bon->load(self::RELATIONS),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Validation totale ou partielle de la sortie physique par le magasinier / responsable dépôt.
     */
    public function validerEnlevement(Request $request, $id)
    {
        $request->validate([
            'lignes' => 'required|array|min:1',
            'lignes.*.detail_id' => 'required|integer|exists:bon_enlevement_details,id',
            'lignes.*.quantite_a_livrer' => 'required|integer|min:0',
            'recepteur_nom' => 'nullable|string|max:150',
            'recepteur_telephone' => 'nullable|string|max:40',
            'chauffeur_immatriculation' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        DB::beginTransaction();
        try {
            $bon = BonEnlevement::with('details.produit')
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bon->statut === 'livre') {
                throw new \Exception("Ce bon d'enlèvement ({$bon->reference}) a déjà été intégralement livré.");
            }
            if ($bon->statut === 'annule') {
                throw new \Exception("Ce bon d'enlèvement ({$bon->reference}) est annulé.");
            }

            $totalALivrer = collect($request->lignes)->sum('quantite_a_livrer');
            if ($totalALivrer <= 0) {
                throw new \Exception("Veuillez saisir au moins une quantité supérieure à 0 à remettre.");
            }

            foreach ($request->lignes as $ligne) {
                $qteLivrer = (int) $ligne['quantite_a_livrer'];
                if ($qteLivrer <= 0) {
                    continue;
                }

                $detail = BonEnlevementDetail::where('id', $ligne['detail_id'])
                    ->where('bon_enlevement_id', $bon->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $reste = $detail->quantite_commandee - $detail->quantite_livree;
                $nomProd = $detail->produit?->nom ?? ('Produit #' . $detail->produit_id);

                if ($qteLivrer > $reste) {
                    throw new \Exception("Quantité à livrer ({$qteLivrer}) supérieure au reste à enlever ({$reste}) pour {$nomProd}.");
                }

                if (!$bon->stock_deja_deduit) {
                    $stock = Stock::where('produit_id', $detail->produit_id)
                        ->where('boutique_id', $bon->entrepot_id)
                        ->lockForUpdate()
                        ->first();

                    $dispo = $stock ? (int) $stock->quantite : 0;
                    if (!$stock || $dispo < $qteLivrer) {
                        throw new \Exception("Stock insuffisant dans l'entrepôt pour '{$nomProd}'. Disponible : {$dispo}, Demandé : {$qteLivrer}.");
                    }

                    $stock->quantite -= $qteLivrer;
                    $stock->save();

                    Inventaire::create([
                        'produit_id' => $detail->produit_id,
                        'boutique_id' => $bon->entrepot_id,
                        'user_id' => $user->id,
                        'vente_id' => $bon->vente_id,
                        'quantite' => $qteLivrer,
                        'type' => 'retrait',
                        'prix_achat' => $stock->prix_achat ?? 0,
                        'prix_vente' => $stock->prix_vente ?? 0,
                        'description' => "Sortie Entrepôt ({$bon->reference})" . ($request->filled('recepteur_nom') ? " — Retiré par {$request->recepteur_nom}" : ''),
                        'date' => now()->format('Y-m-d'),
                    ]);
                }

                $detail->quantite_livree += $qteLivrer;
                $detail->save();
            }

            $bon->load('details');
            $toutLivre = $bon->details->every(fn ($d) => $d->quantite_livree >= $d->quantite_commandee);

            $bon->statut = $toutLivre ? 'livre' : 'partiel';
            $bon->valide_par_user_id = $user->id;
            $bon->date_enlevement = now();

            if ($request->filled('recepteur_nom')) {
                $bon->recepteur_nom = $request->recepteur_nom;
            }
            if ($request->filled('recepteur_telephone')) {
                $bon->recepteur_telephone = $request->recepteur_telephone;
            }
            if ($request->filled('chauffeur_immatriculation')) {
                $bon->chauffeur_immatriculation = $request->chauffeur_immatriculation;
            }
            if ($request->filled('note')) {
                $bon->motif_ou_note = trim(($bon->motif_ou_note ? $bon->motif_ou_note . "\n" : '') . $request->note);
            }

            $bon->save();

            DB::commit();

            return response()->json([
                'message' => $toutLivre
                    ? "Enlèvement complet validé pour le bon {$bon->reference}."
                    : "Enlèvement partiel enregistré pour le bon {$bon->reference}.",
                'bon' => $bon->load(self::RELATIONS),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Annule un bon d'enlèvement non encore totalement livré et restitue le stock éventuellement déduit de l'entrepôt.
     */
    public function annuler($id)
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $bon = BonEnlevement::with('details')
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bon->statut === 'livre') {
                throw new \Exception("Impossible d'annuler un bon déjà intégralement livré.");
            }
            if ($bon->statut === 'annule') {
                throw new \Exception("Ce bon est déjà annulé.");
            }

            if (!$bon->stock_deja_deduit) {
                foreach ($bon->details as $detail) {
                    if ($detail->quantite_livree > 0) {
                        $stock = Stock::where('produit_id', $detail->produit_id)
                            ->where('boutique_id', $bon->entrepot_id)
                            ->lockForUpdate()
                            ->first();

                        if ($stock) {
                            $stock->quantite += $detail->quantite_livree;
                            $stock->save();
                        }

                        Inventaire::create([
                            'produit_id' => $detail->produit_id,
                            'boutique_id' => $bon->entrepot_id,
                            'user_id' => $user->id,
                            'vente_id' => $bon->vente_id,
                            'quantite' => $detail->quantite_livree,
                            'type' => 'ajout',
                            'prix_achat' => $stock ? ($stock->prix_achat ?? 0) : 0,
                            'prix_vente' => $stock ? ($stock->prix_vente ?? 0) : 0,
                            'description' => "Annulation Bon d'Enlèvement {$bon->reference} (retour dépôt)",
                            'date' => now()->format('Y-m-d'),
                        ]);
                    }
                }
            }

            $bon->statut = 'annule';
            $bon->save();

            DB::commit();

            return response()->json([
                'message' => "Bon d'enlèvement {$bon->reference} annulé avec succès.",
                'bon' => $bon->load(self::RELATIONS),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
