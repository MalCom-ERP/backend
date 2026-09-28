<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class FactureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $annee = null)
    {
        $boutique_id = $this->getBoutiqueId();

        $query = DB::table('factures as f')
            ->join('clients as c', 'c.id', '=', 'f.client_id')
            ->where('f.boutique_id', $boutique_id)
            ->select(
                'f.id as idFacture',
                'c.nom as nomClient',
                'c.telephone as telClient',
                'f.montant_total as montant',
                'f.statut',
                'f.date_facturation as dateVente',
                'f.created_at'
            );

        $selectedYear = $annee ?: $request->annee;
        if ($selectedYear) {
            $query->whereYear('f.date_facturation', $selectedYear);
        }

        $factures = $query->orderBy('f.date_facturation', 'desc')->get();

        return response()->json($factures, 200);
    }

    public function detailFacture($IDfacture)
    {
        $facture = Facture::with(['client', 'factureVentes.vente.detailVentes.produit', 'factureVentes.vente.boutique', 'boutique'])
            ->where('id', $IDfacture)
            ->first();

        $produitAchat = [];
        $totalAvance = 0;
        $totalRestant = 0;
        $boutique = null;
        $firstVente = null;

        if ($facture) {
            foreach ($facture->factureVentes as $fv) {
                if ($fv->vente) {
                    $totalAvance += ($fv->vente->montant_avance ?? 0);
                    $totalRestant += ($fv->vente->montant_restant ?? 0);
                    foreach ($fv->vente->detailVentes as $dv) {
                        $produitAchat[] = [
                            'nomProduit' => $dv->produit ? $dv->produit->nom : 'Produit inconnu',
                            'quantite' => $dv->quantite,
                            'prixUnitaire' => $dv->prix_unitaire,
                            'montant' => $dv->montant
                        ];
                    }
                }
            }
            $firstVente = $facture->factureVentes->isNotEmpty() ? $facture->factureVentes->first()->vente : null;
            $boutique = $facture->boutique ?: ($firstVente?->boutique);
            $nomClient = $facture->client?->nom ?? 'Client';
            $numeroClient = $facture->client?->telephone ?? '';
            $adresseClient = $facture->client?->adresse ?? 'N/A';
            $dateFacture = $facture->date_facturation;
            $montantTotal = $facture->montant_total;
            $statut = $facture->statut;
        } else {
            // Fallback: $IDfacture might be a direct Vente ID
            $vente = \App\Models\Vente::with(['client', 'detailVentes.produit', 'boutique', 'user'])->findOrFail($IDfacture);
            $firstVente = $vente;
            $boutique = $vente->boutique;
            $totalAvance = $vente->montant_avance ?? 0;
            $totalRestant = $vente->montant_restant ?? 0;
            $nomClient = $vente->client?->nom ?? 'Client';
            $numeroClient = $vente->client?->telephone ?? '';
            $adresseClient = $vente->client?->adresse ?? 'N/A';
            $dateFacture = $vente->date_vente;
            $montantTotal = $vente->montant_total;
            $statut = $vente->statut;

            foreach ($vente->detailVentes as $dv) {
                $produitAchat[] = [
                    'nomProduit' => $dv->produit ? $dv->produit->nom : 'Produit inconnu',
                    'quantite' => $dv->quantite,
                    'prixUnitaire' => $dv->prix_unitaire,
                    'montant' => $dv->montant
                ];
            }
        }

        if (!$boutique) {
            $boutique = $this->getActiveBoutique() ?: \App\Models\Boutique::first();
        }

        $nomBoutique = $boutique->nom ?? 'Ma Boutique';
        $adresseBoutique = $boutique->adresse ?? '-----';
        $telephoneBoutique = $boutique->telephone ?? '-----';
        $footerFacture = $boutique->footer_facture ?? 'Merci de votre visite !';
        $descriptionFacture = $boutique->description_facture ?? $boutique->description ?? 'Commerce Général & Vente au détail';
        $couleurPrincipale = $boutique->couleur_principale ?? '#4f46e5';
        $couleurSecondaire = $boutique->couleur_secondaire ?? '#10b981';
        $logo = $boutique->logo ?? '';

        $moyenPaiement = $firstVente ? ($firstVente->moyen_paiement ?: $firstVente->type_paiement) : ($statut ?? 'especes');
        $montantRecu = $firstVente ? ($firstVente->montant_recu ?? 0) : 0;
        $monnaieRendue = $firstVente ? ($firstVente->monnaie_rendue ?? 0) : 0;
        $nomCaissier = ($firstVente && $firstVente->user) ? $firstVente->user->name : 'Caissier';

        $response = [
            'nomClient' => $nomClient,
            'numeroClient' => $numeroClient,
            'adresseClient' => $adresseClient,
            'dateFacture' => $dateFacture,
            'montant_total' => $montantTotal,
            'montant_remis' => $firstVente ? ($firstVente->remise ?: ($firstVente->detailVentes->first()->remise ?? 0)) : 0,
            'montant_avance' => $totalAvance,
            'montant_restant' => $totalRestant,
            'moyen_paiement' => $moyenPaiement,
            'montant_recu' => $montantRecu,
            'monnaie_rendue' => $monnaieRendue,
            'nomCaissier' => $nomCaissier,
            'footerFacture' => $footerFacture,
            'descriptionFacture' => $descriptionFacture,
            'couleurPrincipale' => $couleurPrincipale,
            'couleurSecondaire' => $couleurSecondaire,
            'logo' => $logo,
            'nomBoutique' => $nomBoutique,
            'statut' => $statut,
            'adresseBoutique' => $adresseBoutique,
            'telephoneBoutique' => $telephoneBoutique,
            'nifBoutique' => $boutique->nif ?? '',
            'rccmBoutique' => $boutique->rccm ?? '',
            'produitAchat' => $produitAchat
        ];

        return response()->json([$response]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Facture $facture)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Facture $facture)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Facture $facture)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Facture $facture)
    {
        //
    }
}
