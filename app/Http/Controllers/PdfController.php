<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\Boutique;
use App\Models\Inventaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    /**
     * Generate PDF based on document type
     */
    public function generatePdf(Request $request)
    {
        $request->validate([
            'type' => 'required|in:facture,bordereau,proforma,recu_credit,inventaire,rapport_journalier,journal,grand_livre,balance,compte_resultat,bilan',
            'id' => 'nullable|integer',
            'boutique_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'produit_id' => 'nullable|integer',
            'journal_id' => 'nullable|integer',
            'compte_id' => 'nullable|integer'
        ]);

        $type = $request->input('type');
        $id = $request->input('id', 0);

        try {
            // Load data based on type
            $data = $this->loadData($type, $id);
            
            // Select template
            $template = match($type) {
                'facture' => 'pdf.facture',
                'bordereau' => 'pdf.bordereau',
                'proforma' => 'pdf.proforma',
                'recu_credit' => 'pdf.recu_credit',
                'inventaire' => 'pdf.inventaire',
                'rapport_journalier' => 'pdf.rapport_journalier',
                'journal' => 'pdf.compta.journal',
                'grand_livre' => 'pdf.compta.grand_livre',
                'balance' => 'pdf.compta.balance',
                'compte_resultat' => 'pdf.compta.compte_resultat',
                'bilan' => 'pdf.compta.bilan',
            };

            // Configure PDF options
            $orientation = match($type) {
                'inventaire', 'journal', 'grand_livre', 'balance', 'bilan' => 'landscape',
                default => 'portrait'
            };
            
            // Generate PDF
            $pdf = PDF::loadView($template, $data)
                ->setPaper('a4', $orientation)
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'sans-serif'
                ]);

            $filename = ($id && $id > 0) ? "{$type}-{$id}.pdf" : "{$type}-" . now()->format('Y-m-d') . ".pdf";
            
            return $pdf->download($filename);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la génération du PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview PDF in browser
     */
    public function previewPdf(Request $request, $type, $id = 0)
    {
        try {
            $data = $this->loadData($type, $id);
            
            $template = match($type) {
                'facture' => 'pdf.facture',
                'bordereau' => 'pdf.bordereau',
                'proforma' => 'pdf.proforma',
                'recu_credit' => 'pdf.recu_credit',
                'inventaire' => 'pdf.inventaire',
                'rapport_journalier' => 'pdf.rapport_journalier',
                'journal' => 'pdf.compta.journal',
                'grand_livre' => 'pdf.compta.grand_livre',
                'balance' => 'pdf.compta.balance',
                'compte_resultat' => 'pdf.compta.compte_resultat',
                'bilan' => 'pdf.compta.bilan',
            };

            $orientation = match($type) {
                'inventaire', 'journal', 'grand_livre', 'balance', 'bilan' => 'landscape',
                default => 'portrait'
            };
            
            $pdf = PDF::loadView($template, $data)
                ->setPaper('a4', $orientation);

            return $pdf->stream();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la prévisualisation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load data based on document type
     */
    private function loadData($type, $id)
    {
        $user = Auth::user();
        $boutiqueId = request()->input('boutique_id') 
            ?: request()->header('X-Boutique-Id') 
            ?: $this->getBoutiqueId();

        $activeBoutique = ($boutiqueId ? Boutique::with('nature')->find($boutiqueId) : null) 
            ?: $this->getActiveBoutique() 
            ?: Boutique::with('nature')->first();

        $boutiqueId = $activeBoutique?->id;

        switch ($type) {
            case 'facture':
                // Check if the ID belongs to a Facture model grouping multiple sales
                $facture = \App\Models\Facture::with(['factureVentes.vente.detailVentes.produit', 'client', 'boutique'])
                    ->find($id);

                if ($facture && $facture->factureVentes->isNotEmpty()) {
                    $vente = $facture->factureVentes->first()->vente;
                    $vente->montant_total = $facture->montant_total;
                    $allDetails = collect();
                    foreach($facture->factureVentes as $fv) {
                        if($fv->vente) {
                            $allDetails = $allDetails->concat($fv->vente->detailVentes);
                        }
                    }
                    $vente->setRelation('detailVentes', $allDetails);
                    $boutique = $facture->boutique ?: $vente->boutique ?: $activeBoutique;
                } else {
                    $vente = Vente::with(['detailVentes.produit', 'client', 'user', 'boutique'])
                        ->findOrFail($id);
                    $boutique = $vente->boutique ?: $activeBoutique;
                }
                
                $strategy = \App\Services\NatureStrategyFactory::make($boutique);
                return [
                    'vente' => $vente,
                    'boutique' => $boutique,
                    'strategy' => $strategy,
                    'type' => $type,
                    'date' => now()
                ];

            case 'bordereau':
                $vente = Vente::with(['detailVentes.produit', 'client', 'user', 'boutique'])
                    ->findOrFail($id);
                $boutique = $vente->boutique ?: $activeBoutique;
                $strategy = \App\Services\NatureStrategyFactory::make($boutique);
                return [
                    'vente' => $vente,
                    'boutique' => $boutique,
                    'strategy' => $strategy,
                    'type' => $type,
                    'date' => now()
                ];

            case 'proforma':
                $vente = Vente::with(['detailVentes.produit', 'client', 'user', 'boutique'])
                    ->findOrFail($id);
                $boutique = $vente->boutique ?: $activeBoutique;
                $strategy = \App\Services\NatureStrategyFactory::make($boutique);
                return [
                    'vente' => $vente,
                    'boutique' => $boutique,
                    'strategy' => $strategy,
                    'type' => $type,
                    'date' => now()
                ];

            case 'recu_credit':
                $vente = Vente::with(['detailVentes.produit', 'client', 'user', 'boutique', 'paiementsCredit.user'])
                    ->findOrFail($id);
                $boutique = $vente->boutique ?: $activeBoutique;
                return [
                    'vente' => $vente,
                    'boutique' => $boutique,
                    'paiements' => $vente->paiementsCredit,
                    'date' => now()
                ];

            case 'inventaire':
                $boutique = $activeBoutique;

                // Get filters from request (passed from generatePdf)
                $startDate = request('start_date');
                $endDate = request('end_date');
                $produitId = request('produit_id');

                $inventaires = \App\Models\Inventaire::with(['produit.stock', 'user'])
                    ->where('boutique_id', $boutique->id)
                    ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                    ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                    ->when($produitId, fn($q) => $q->where('produit_id', $produitId))
                    ->orderBy('created_at', 'desc')
                    ->get();

                $stats = [
                    'totalEntrees' => $inventaires->where('type', 'ajout')->sum('quantite'),
                    'totalSorties' => $inventaires->where('type', 'retrait')->sum('quantite'),
                    'valeurAchatEntrante' => 0,
                    'valeurVenteSortante' => 0,
                ];

                foreach ($inventaires as $inv) {
                    if ($inv->type === 'retrait') {
                        $stats['valeurVenteSortante'] += ($inv->quantite * ($inv->produit->stock->prix_vente ?? 0));
                    } else {
                        $stats['valeurAchatEntrante'] += ($inv->quantite * ($inv->produit->stock->prix_achat ?? 0));
                    }
                }

                $stats['netMouvement'] = $stats['totalEntrees'] - $stats['totalSorties'];

                return [
                    'boutique' => $boutique,
                    'inventaires' => $inventaires,
                    'stats' => $stats,
                    'filters' => [
                        'start_date' => $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : null,
                        'end_date' => $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : null,
                    ],
                    'date' => now()
                ];

            case 'rapport_journalier':
                $controller = app(DailyReportController::class);
                if ($id && $id > 0) {
                    $report = \App\Models\DailyReport::with('boutique')->find($id);
                    if ($report) {
                        $date = $report->date->format('Y-m-d');
                        $data = $controller->loadReportData($report->boutique_id, $date);
                        $data['report'] = $report;
                        $data['fond_de_caisse'] = $report->fond_de_caisse;
                        $data['total_especes_theorique'] = $report->total_especes_theorique;
                        $data['total_especes_physique'] = $report->total_especes_physique;
                        $data['ecart_caisse'] = $report->ecart_caisse;
                        $data['billetage'] = $report->billetage ?: [];
                        $data['boutique'] = $report->boutique ?: $activeBoutique;
                        return $data;
                    }
                }
                
                $date = request('date') ? \Carbon\Carbon::parse(request('date'))->format('Y-m-d') : now()->format('Y-m-d');
                $data = $controller->loadReportData($activeBoutique->id, $date);
                $data['boutique'] = $activeBoutique;
                return $data;

            case 'journal':
                $boutique = $activeBoutique;
                $journalId = request('journal_id');
                $startDate = request('start_date') ?? request('date_debut');
                $endDate = request('end_date') ?? request('date_fin');
                $search = request('search');

                $comptaService = app(\App\Services\ComptaService::class);
                $ecritures = $comptaService->getJournalEcritures($boutique->id, $journalId, $startDate, $endDate, $search);

                $totalDebit = 0;
                $totalCredit = 0;
                foreach ($ecritures as $e) {
                    foreach ($e->lignes as $l) {
                        $totalDebit += (float) $l->debit;
                        $totalCredit += (float) $l->credit;
                    }
                }

                $journalModel = $journalId ? \App\Models\JournalComptable::find($journalId) : null;

                return [
                    'boutique' => $boutique,
                    'ecritures' => $ecritures,
                    'totalDebit' => $totalDebit,
                    'totalCredit' => $totalCredit,
                    'journal' => $journalModel,
                    'filters' => [
                        'start_date' => $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : null,
                        'end_date' => $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : null,
                        'journal_libelle' => $journalModel ? $journalModel->libelle : 'Tous les journaux',
                    ],
                    'date' => now()
                ];

            case 'grand_livre':
                $boutique = $activeBoutique;
                $compteId = request('compte_id');
                $startDate = request('start_date') ?? request('date_debut');
                $endDate = request('end_date') ?? request('date_fin');

                $comptaService = app(\App\Services\ComptaService::class);
                $grandLivre = $comptaService->getGrandLivre($boutique->id, $startDate, $endDate, $compteId);
                $compteModel = $compteId ? \App\Models\CompteComptable::find($compteId) : null;

                return [
                    'boutique' => $boutique,
                    'grandLivre' => $grandLivre,
                    'compte' => $compteModel,
                    'filters' => [
                        'start_date' => $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : null,
                        'end_date' => $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : null,
                        'compte_libelle' => $compteModel ? ($compteModel->numero . ' - ' . $compteModel->libelle) : 'Tous les comptes',
                    ],
                    'date' => now()
                ];

            case 'balance':
                $boutique = $activeBoutique;
                $startDate = request('start_date') ?? request('date_debut');
                $endDate = request('end_date') ?? request('date_fin');

                $comptaService = app(\App\Services\ComptaService::class);
                $balance = $comptaService->getBalanceGenerale($boutique->id, $startDate, $endDate);

                return [
                    'boutique' => $boutique,
                    'balance' => $balance,
                    'filters' => [
                        'start_date' => $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : null,
                        'end_date' => $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : null,
                    ],
                    'date' => now()
                ];

            case 'compte_resultat':
                $boutique = $activeBoutique;
                $startDate = request('start_date') ?? request('date_debut');
                $endDate = request('end_date') ?? request('date_fin');

                $comptaService = app(\App\Services\ComptaService::class);
                $resultat = $comptaService->getCompteResultat($boutique->id, $startDate, $endDate);

                return [
                    'boutique' => $boutique,
                    'resultat' => $resultat,
                    'filters' => [
                        'start_date' => $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : null,
                        'end_date' => $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : null,
                    ],
                    'date' => now()
                ];

            case 'bilan':
                $boutique = $activeBoutique;
                $startDate = request('start_date') ?? request('date_debut');
                $endDate = request('end_date') ?? request('date_fin');

                $comptaService = app(\App\Services\ComptaService::class);
                $bilan = $comptaService->getBilan($boutique->id, $startDate, $endDate);

                return [
                    'boutique' => $boutique,
                    'bilan' => $bilan,
                    'filters' => [
                        'start_date' => $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : null,
                        'end_date' => $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : null,
                    ],
                    'date' => now()
                ];

            default:
                throw new \Exception('Type de document invalide');
        }
    }
}
