<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\Client;
use App\Models\FactureVente;
use App\Models\Facture;
use App\Models\PaiementCredit;
use App\Models\Inventaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    /**
     * Liste des ventes à crédit.
     */
    public function index()
    {
        $boutique_id = $this->getBoutiqueId();
        $credits = Vente::with(['client', 'user'])
            ->where('boutique_id', $boutique_id)
            ->where('type_paiement', 'credit')
            // ->where('montant_restant', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($credits, 200);
    }

    /**
     * Liste des débiteurs (clients avec une dette totale).
     */
    public function debtors()
    {
        $boutique_id = $this->getBoutiqueId();
        $debtors = Client::whereHas('ventes', function ($q) use ($boutique_id) {
            $q->where('boutique_id', $boutique_id)
                ->where('type_paiement', 'credit')
                // ->where('montant_restant', '>', 0)
                ->orderBy('created_at','DESC');
        })
            ->withSum(['ventes as total_dette' => function ($q) use ($boutique_id) {
                $q->where('boutique_id', $boutique_id)
                    ->where('type_paiement', 'credit');
            }], 'montant_restant')
            ->get();

        return response()->json($debtors, 200);
    }

    /**
     * Enregistrer un nouveau paiement pour un crédit.
     */
    public function addPayment(Request $request)
    {
        $request->validate([
            'vente_id' => 'required|exists:ventes,id',
            'montant' => 'required|numeric|min:0.01',
            'date_paiement' => 'required|date',
            'mode_paiement' => 'string|nullable',
            'notes' => 'string|nullable',
        ]);

        $boutique_id = $this->getBoutiqueId();
        $vente = Vente::where('id', $request->vente_id)
            ->where('boutique_id', $boutique_id)
            ->firstOrFail();

        // Recompute the remaining amount based on total, advance and existing payments
        $credits = PaiementCredit::where('vente_id', $request->vente_id)
            ->where('boutique_id', $boutique_id)
            ->get();

        $montant_total_paye = $credits->sum('montant');
        $expectedRemaining = ($vente->montant_total - $vente->montant_avance) - $montant_total_paye;
        $expectedRemaining = $expectedRemaining < 0 ? 0 : $expectedRemaining;

        if ($request->montant > $expectedRemaining) {
            return response()->json(['message' => 'Le montant du paiement dépasse le reste à payer'], 400);
        }

        DB::beginTransaction();
        try {

            $paiement = PaiementCredit::create([
                'vente_id' => $vente->id,
                'boutique_id' => $boutique_id,
                'user_id' => Auth::id(),
                'montant' => $request->montant,
                'date_paiement' => $request->date_paiement,
                'mode_paiement' => $request->mode_paiement ?? 'Espèces',
                'notes' => $request->notes,
            ]);

            // Recompute totals after inserting the paiement
            $montant_total_paye += $request->montant;
            $newRemaining = ($vente->montant_total - $vente->montant_avance) - $montant_total_paye;

            if ($newRemaining <= 0) {
                $vente->montant_restant = 0;
                $vente->statut = 'payee';

                // Update facture status to payée if exists
                $factureVente = FactureVente::where('vente_id',$request->vente_id)->first();
                if ($factureVente) {
                    $facture = Facture::where('id',$factureVente->facture_id)->first();
                    if ($facture) {
                        $facture->statut = 'payée';
                    }
                    $facture->save();
                }
            } else {
                $vente->montant_restant = $newRemaining;
            }
            $vente->save();

            // Écriture comptable automatique SYSCOHADA (Débit Caisse, Crédit Client)
            app(\App\Services\ComptaService::class)->enregistrerPaiementCredit($paiement);

            DB::commit();
            return response()->json(['message' => 'Paiement enregistré avec succès', 'paiement' => $paiement], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de l\'enregistrement du paiement', 'error' => $e->getMessage()], 500);
        }
    }

    public function allPayments()
    {
        $boutique_id = $this->getBoutiqueId();
        $paiements = PaiementCredit::with(['vente.client', 'user'])
            ->where('boutique_id', $boutique_id)
            ->orderBy('date_paiement', 'desc')
            ->get();

        return response()->json($paiements, 200);
    }

    public function history($id)
    {
        $boutique_id = $this->getBoutiqueId();
        $movements = Inventaire::with(['produit.categorie', 'user'])
            ->where('vente_id', $id)
            ->where('boutique_id', $boutique_id)
            ->whereNotNull('produit_id')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($movements->isEmpty()) {
            $vente = Vente::with(['detailVentes.produit.categorie', 'user'])->find($id);
            if ($vente && $vente->detailVentes) {
                $virtualMovements = [];
                foreach ($vente->detailVentes as $dv) {
                    $virtualMovements[] = [
                        'id' => 'v-' . $dv->id,
                        'date' => $vente->date_vente ?? $vente->created_at,
                        'type' => 'retrait',
                        'quantite' => $dv->quantite,
                        'produit' => $dv->produit,
                        'user' => $vente->user,
                        'description' => 'Sortie stock vente initiale #' . $vente->id,
                    ];
                }
                return response()->json($virtualMovements, 200);
            }
        }

        return response()->json($movements, 200);
    }

    /**
     * Données pour le bordereau de paiement.
     */
    public function saleStatement($id)
    {
        $boutique_id = $this->getBoutiqueId();
        $vente = Vente::with(['client', 'user', 'detailVentes.produit.categorie', 'paiementsCredit.user', 'boutique'])
            ->where('id', $id)
            ->where('boutique_id', $boutique_id)
            ->firstOrFail();

        return response()->json($vente, 200);
    }
}
