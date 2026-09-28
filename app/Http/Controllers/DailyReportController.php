<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Boutique;
use App\Models\Vente;
use App\Models\Expense;
use App\Models\PaiementCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Mail\DailyReportMail;
use Illuminate\Support\Facades\Mail;
use App\Services\WhatsAppService;

class DailyReportController extends Controller
{
    protected $whatsApp;

    public function __construct(WhatsAppService $whatsApp)
    {
        $this->whatsApp = $whatsApp;
    }

    public function index(Request $request)
    {
        $boutique_id = $this->getBoutiqueId();

        $query = DailyReport::with(['boutique', 'cloturePar:id,name'])
            ->when($boutique_id, fn($q) => $q->where('boutique_id', $boutique_id))
            ->orderBy('date', 'desc');

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('search') && !empty($request->search)) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('date', 'LIKE', "%{$s}%")
                  ->orWhere('statut_cloture', 'LIKE', "%{$s}%");
            });
        }

        $reports = $query->paginate(15);

        return response()->json($reports);
    }

    /**
     * État de la session de caisse en temps réel (Axe 4).
     */
    public function sessionStatus(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique introuvable'], 400);
        }

        $date = $request->date ? Carbon::parse($request->date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $boutique = Boutique::find($boutiqueId);

        $existingReport = DailyReport::with('cloturePar:id,name')
            ->where('boutique_id', $boutiqueId)
            ->whereDate('date', $date)
            ->first();

        $data = $this->loadReportData($boutiqueId, $date);

        $fondDeCaisse = $existingReport ? (float)$existingReport->fond_de_caisse : 0;
        $totalEspecesTheorique = $fondDeCaisse + $data['totaux']['especes_ventes'] + $data['totaux']['recouvrement_especes'] - $data['totaux']['depenses_especes'];

        $isClosed = $existingReport && $existingReport->statut_cloture === 'cloturee';

        return response()->json([
            'date' => $date,
            'boutique' => $boutique,
            'is_closed' => $isClosed,
            'statut_cloture' => $existingReport ? $existingReport->statut_cloture : 'ouverte',
            'cloture_info' => $existingReport ? [
                'id' => $existingReport->id,
                'cloture_par' => $existingReport->cloturePar ? $existingReport->cloturePar->name : 'Caissier',
                'created_at' => $existingReport->created_at ? $existingReport->created_at->format('H:i') : null,
                'total_especes_physique' => (float)$existingReport->total_especes_physique,
                'ecart_caisse' => (float)$existingReport->ecart_caisse,
                'notes_cloture' => $existingReport->notes_cloture,
                'billetage' => $existingReport->billetage,
            ] : null,
            'flux_caisse' => [
                'fond_de_caisse' => $fondDeCaisse,
                'encaissements_especes' => $data['totaux']['especes_ventes'],
                'recouvrements_especes' => $data['totaux']['recouvrement_especes'],
                'decaissements_especes' => $data['totaux']['depenses_especes'],
                'solde_theorique_especes' => round($totalEspecesTheorique, 2),
            ],
            'autres_modes' => [
                'orange_money' => $data['totaux']['orange_money'],
                'moov_money' => $data['totaux']['moov_money'],
                'wave' => $data['totaux']['wave'],
                'total_mobile_money' => $data['totaux']['mobile_money'],
                'carte_bancaire' => $data['totaux']['carte_bancaire'],
                'ventes_credit' => $data['totaux']['ventes_credit_net'],
                'recouvrement_autres' => $data['totaux']['recouvrement_autres'],
                'depenses_autres' => $data['totaux']['depenses_autres'],
            ],
            'synthese_financiere' => [
                'ventes_net' => $data['totaux']['ventes_net'],
                'total_depenses' => $data['totaux']['depenses'],
                'benefice_net' => $data['totaux']['benefice_net'],
                'nombre_ventes' => $data['stats']['nombre_ventes'],
                'nombre_depenses' => $data['stats']['nombre_depenses'],
            ],
            'ventes_recentes' => $data['ventes']->take(10),
            'depenses_recentes' => $data['depenses']->take(10),
        ], 200);
    }

    /**
     * Clôture de caisse interactive avec billetage et calcul d'écart (Axe 4).
     */
    public function cloturerCaisse(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique introuvable'], 400);
        }

        $request->validate([
            'date' => 'required|date',
            'fond_de_caisse' => 'nullable|numeric|min:0',
            'total_especes_physique' => 'required|numeric|min:0',
            'billetage' => 'nullable|array',
            'notes_cloture' => 'nullable|string',
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $boutique = Boutique::findOrFail($boutiqueId);

        $fondDeCaisse = (float)($request->fond_de_caisse ?: 0);
        $totalEspecesPhysique = (float)$request->total_especes_physique;

        $data = $this->loadReportData($boutiqueId, $date);

        $soldeTheorique = $fondDeCaisse + $data['totaux']['especes_ventes'] + $data['totaux']['recouvrement_especes'] - $data['totaux']['depenses_especes'];
        $ecart = $totalEspecesPhysique - $soldeTheorique;

        $report = DailyReport::updateOrCreate(
            [
                'boutique_id' => $boutiqueId,
                'date' => $date
            ],
            [
                'fond_de_caisse' => $fondDeCaisse,
                'total_especes_theorique' => round($soldeTheorique, 2),
                'total_especes_physique' => round($totalEspecesPhysique, 2),
                'ecart_caisse' => round($ecart, 2),
                'billetage' => $request->billetage ?: [],
                'total_ventes' => $data['totaux']['ventes_net'],
                'total_depenses' => $data['totaux']['depenses'],
                'benefice_net' => $data['totaux']['benefice_net'],
                'total_mobile_money' => $data['totaux']['mobile_money'],
                'total_carte_bancaire' => $data['totaux']['carte_bancaire'],
                'total_credit' => $data['totaux']['ventes_credit_net'],
                'total_recouvrement' => $data['totaux']['recouvrement_especes'] + $data['totaux']['recouvrement_autres'],
                'nombre_ventes' => $data['stats']['nombre_ventes'],
                'nombre_depenses' => $data['stats']['nombre_depenses'],
                'statut_cloture' => 'cloturee',
                'cloture_par_user_id' => Auth::id(),
                'notes_cloture' => $request->notes_cloture
            ]
        );

        // Générer le PDF A4 officiel
        try {
            $pdf = PDF::loadView('pdf.rapport_journalier', array_merge($data, [
                'report' => $report,
                'fond_de_caisse' => $fondDeCaisse,
                'total_especes_theorique' => $soldeTheorique,
                'total_especes_physique' => $totalEspecesPhysique,
                'ecart_caisse' => $ecart,
                'billetage' => $request->billetage ?: [],
            ]))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif'
            ]);

            $filename = "rapports/rapport-{$boutique->nom}-{$date}.pdf";
            Storage::put($filename, $pdf->output());
            $report->pdf_path = $filename;
            $report->save();
        } catch (\Exception $e) {}

        // Construire le message WhatsApp de synthèse
        $userNom = Auth::user() ? Auth::user()->name : 'Caissier';
        $statusEcart = $ecart == 0 ? '✅ Parfaitement équilibrée' : ($ecart > 0 ? "🔵 Excédent de +" . number_format($ecart, 0, ',', ' ') . " F" : "🔴 Déficit de " . number_format($ecart, 0, ',', ' ') . " F");
        $fondStr = number_format($fondDeCaisse, 0, ',', ' ') . ' FCFA';
        $ventesEspStr = number_format($data['totaux']['especes_ventes'], 0, ',', ' ') . ' FCFA';
        $recouvStr = number_format($data['totaux']['recouvrement_especes'], 0, ',', ' ') . ' FCFA';
        $depEspStr = number_format($data['totaux']['depenses_especes'], 0, ',', ' ') . ' FCFA';
        $theoStr = number_format($soldeTheorique, 0, ',', ' ') . ' FCFA';
        $physStr = number_format($totalEspecesPhysique, 0, ',', ' ') . ' FCFA';
        $ecartStr = number_format($ecart, 0, ',', ' ') . ' FCFA';
        $caStr = number_format($data['totaux']['ventes_net'], 0, ',', ' ') . ' FCFA';
        $depTotStr = number_format($data['totaux']['depenses'], 0, ',', ' ') . ' FCFA';
        $netStr = number_format($data['totaux']['benefice_net'], 0, ',', ' ') . ' FCFA';
        $mmStr = number_format($data['totaux']['mobile_money'], 0, ',', ' ') . ' FCFA';

        $whatsappMsg = "🔒 *CLÔTURE DE CAISSE - {$boutique->nom}*\n";
        $whatsappMsg .= "📅 Date : " . Carbon::parse($date)->format('d/m/Y') . "\n";
        $whatsappMsg .= "👤 Clôturé par : {$userNom}\n\n";
        $whatsappMsg .= "💵 *Espèces en Caisse :*\n";
        $whatsappMsg .= "• Fond Initial : {$fondStr}\n";
        $whatsappMsg .= "• Ventes Espèces : {$ventesEspStr}\n";
        if ($data['totaux']['recouvrement_especes'] > 0) {
            $whatsappMsg .= "• Recouvrements Créances : +{$recouvStr}\n";
        }
        $whatsappMsg .= "• Dépenses Espèces : -{$depEspStr}\n";
        $whatsappMsg .= "• Solde Théorique : {$theoStr}\n";
        $whatsappMsg .= "• Espèces Réelles (Billetage) : *{$physStr}*\n";
        $whatsappMsg .= "• Écart : *{$ecartStr}* ({$statusEcart})\n\n";
        if ($data['totaux']['mobile_money'] > 0) {
            $whatsappMsg .= "📱 Mobile Money : {$mmStr}\n";
        }
        $whatsappMsg .= "📊 *Performance Journalière :*\n";
        $whatsappMsg .= "• Chiffre d'Affaires Net : {$caStr}\n";
        $whatsappMsg .= "• Charges Totales : {$depTotStr}\n";
        $whatsappMsg .= "• *Bénéfice Net : {$netStr}*\n";
        $whatsappMsg .= "• Transactions : {$data['stats']['nombre_ventes']} vente(s)\n";

        $cleanTel = $boutique->telephone ? preg_replace('/[^0-9]/', '', $boutique->telephone) : null;
        if ($cleanTel && strlen($cleanTel) === 8) {
            $cleanTel = '223' . $cleanTel;
        }
        $whatsappLink = $cleanTel ? ("https://wa.me/{$cleanTel}?text=" . urlencode($whatsappMsg)) : null;

        return response()->json([
            'message' => 'Clôture de caisse enregistrée avec succès',
            'report' => $report->load(['boutique', 'cloturePar']),
            'ecart' => $ecart,
            'whatsapp_message' => $whatsappMsg,
            'whatsapp_link' => $whatsappLink,
            'ticket_z' => $this->buildTicketZData($report, $boutique, $data)
        ], 200);
    }

    /**
     * Génère les données structurées du Ticket Z pour impression thermique 80mm (Axe 4).
     */
    public function ticketZ($id)
    {
        $report = DailyReport::with(['boutique', 'cloturePar'])->findOrFail($id);
        $data = $this->loadReportData($report->boutique_id, $report->date->format('Y-m-d'));
        $ticketData = $this->buildTicketZData($report, $report->boutique, $data);

        return response()->json($ticketData, 200);
    }

    private function buildTicketZData($report, $boutique, $data)
    {
        return [
            'type' => 'TICKET_Z_CLOTURE',
            'boutique' => [
                'nom' => $boutique->nom,
                'adresse' => $boutique->adresse,
                'telephone' => $boutique->telephone,
            ],
            'report_id' => $report->id,
            'date' => $report->date->format('d/m/Y'),
            'heure_cloture' => $report->updated_at ? $report->updated_at->format('H:i:s') : date('H:i:s'),
            'cloture_par' => $report->cloturePar ? $report->cloturePar->name : 'Caissier',
            'fond_de_caisse' => (float)$report->fond_de_caisse,
            'ventes_especes' => (float)$data['totaux']['especes_ventes'],
            'recouvrements_especes' => (float)$data['totaux']['recouvrement_especes'],
            'depenses_especes' => (float)$data['totaux']['depenses_especes'],
            'solde_theorique' => (float)$report->total_especes_theorique,
            'especes_physique' => (float)$report->total_especes_physique,
            'ecart_caisse' => (float)$report->ecart_caisse,
            'mobile_money' => (float)$report->total_mobile_money,
            'carte_bancaire' => (float)$report->total_carte_bancaire,
            'ventes_credit' => (float)$report->total_credit,
            'total_ventes_net' => (float)$report->total_ventes,
            'total_depenses' => (float)$report->total_depenses,
            'benefice_net' => (float)$report->benefice_net,
            'nombre_ventes' => (int)$report->nombre_ventes,
            'nombre_depenses' => (int)$report->nombre_depenses,
            'billetage' => $report->billetage ?: [],
            'notes' => $report->notes_cloture
        ];
    }

    public function generate(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'boutique_id' => 'nullable|exists:boutiques,id',
            'regenerate' => 'nullable|boolean'
        ]);

        $boutiqueId = $this->getBoutiqueId();
        $date = Carbon::parse($request->date)->format('Y-m-d');

        try {
            $exists = DailyReport::where('boutique_id', $boutiqueId)->whereDate('date', $date)->exists();
            $report = $this->generateReport($boutiqueId, $date);

            return response()->json([
                'success' => true,
                'message' => $exists ? 'Rapport mis à jour avec succès' : 'Rapport généré avec succès',
                'report' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la génération du rapport',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $user = Auth::user();
        $report = DailyReport::with(['boutique', 'cloturePar'])->findOrFail($id);

        if ($user && $user->role !== 'admin' && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return response()->json($report);
    }

    public function download($id)
    {
        $user = Auth::user();
        $report = DailyReport::with('boutique')->findOrFail($id);

        if ($user && !in_array($user->role, ['admin', 'admin1']) && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $data = $this->loadReportData($report->boutique_id, $report->date->format('Y-m-d'));
        $pdf = PDF::loadView('pdf.rapport_journalier', array_merge($data, [
            'report' => $report,
            'fond_de_caisse' => $report->fond_de_caisse,
            'total_especes_theorique' => $report->total_especes_theorique,
            'total_especes_physique' => $report->total_especes_physique,
            'ecart_caisse' => $report->ecart_caisse,
            'billetage' => $report->billetage ?: [],
        ]))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif'
            ]);

        $filename = "rapport-{$report->boutique->nom}-{$report->date->format('Y-m-d')}.pdf";
        return $pdf->download($filename);
    }

    public function sendEmail($id)
    {
        $user = Auth::user();
        $report = DailyReport::with('boutique')->findOrFail($id);

        if ($user && $user->role !== 'admin' && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        try {
            $this->sendReportEmail($report);

            return response()->json([
                'success' => true,
                'message' => 'Rapport envoyé par email avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'envoi de l\'email',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sendWhatsApp($id)
    {
        $user = Auth::user();
        $report = DailyReport::with('boutique')->findOrFail($id);

        if ($user && $user->role !== 'admin' && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        try {
            $this->sendReportWhatsApp($report);

            return response()->json([
                'success' => true,
                'message' => 'Rapport envoyé via WhatsApp avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'envoi WhatsApp',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function generateReport($boutiqueId, $date)
    {
        $boutique = Boutique::findOrFail($boutiqueId);
        $data = $this->loadReportData($boutiqueId, $date);

        $report = DailyReport::updateOrCreate(
            [
                'boutique_id' => $boutiqueId,
                'date' => $date
            ],
            [
                'total_ventes' => $data['totaux']['ventes_net'],
                'total_depenses' => $data['totaux']['depenses'],
                'benefice_net' => $data['totaux']['benefice_net'],
                'total_mobile_money' => $data['totaux']['mobile_money'],
                'total_carte_bancaire' => $data['totaux']['carte_bancaire'],
                'total_credit' => $data['totaux']['ventes_credit_net'],
                'total_recouvrement' => $data['totaux']['recouvrement_especes'] + $data['totaux']['recouvrement_autres'],
                'nombre_ventes' => $data['stats']['nombre_ventes'],
                'nombre_depenses' => $data['stats']['nombre_depenses']
            ]
        );

        $pdf = PDF::loadView('pdf.rapport_journalier', array_merge($data, ['report' => $report]))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif'
            ]);

        $filename = "rapports/rapport-{$boutique->nom}-{$date}.pdf";
        Storage::put($filename, $pdf->output());

        $report->pdf_path = $filename;
        $report->save();

        return $report;
    }

    public function loadReportData($boutiqueId, $date)
    {
        $boutique = Boutique::findOrFail($boutiqueId);

        $ventes = Vente::with(['detailVentes.produit', 'client', 'user'])
            ->where('boutique_id', $boutiqueId)
            ->whereIn('statut', ['payee', 'validee'])
            ->whereDate('date_vente', $date)
            ->get();

        $venteCredit = Vente::with(['detailVentes.produit', 'client', 'user'])
            ->where('boutique_id', $boutiqueId)
            ->where('statut', 'credit')
            ->whereDate('date_vente', $date)
            ->get();

        $venteAnnulee = Vente::with(['detailVentes.produit', 'client', 'user'])
            ->where('boutique_id', $boutiqueId)
            ->where('statut', 'annulee')
            ->whereDate('date_vente', $date)
            ->get();

        $depenses = Expense::with('user')
            ->where('boutique_id', $boutiqueId)
            ->whereDate('date', $date)
            ->get();

        // Règlements de crédit encaissés ce jour
        $recouvrements = PaiementCredit::with('user')
            ->whereHas('vente', fn($q) => $q->where('boutique_id', $boutiqueId))
            ->whereDate('date_paiement', $date)
            ->get();

        // Calcul des flux par mode de paiement
        $especesVentes = 0;
        $orangeMoney = 0;
        $moovMoney = 0;
        $wave = 0;
        $carteBancaire = 0;

        foreach ($ventes as $v) {
            $mode = strtolower(trim($v->type_paiement ?: 'especes'));
            $montant = (float)$v->montant_total;

            if ($mode === 'especes' || $mode === 'comptant') {
                $especesVentes += $montant;
            } elseif (str_contains($mode, 'orange')) {
                $orangeMoney += $montant;
            } elseif (str_contains($mode, 'moov')) {
                $moovMoney += $montant;
            } elseif (str_contains($mode, 'wave')) {
                $wave += $montant;
            } elseif (str_contains($mode, 'carte') || str_contains($mode, 'cheque') || str_contains($mode, 'virement')) {
                $carteBancaire += $montant;
            } elseif ($mode === 'mixte' && is_array($v->details_paiement)) {
                $dp = $v->details_paiement;
                $especesVentes += (float)($dp['especes'] ?? 0);
                $orangeMoney += (float)($dp['orange_money'] ?? 0);
                $moovMoney += (float)($dp['moov_money'] ?? 0);
                $wave += (float)($dp['wave'] ?? 0);
                $carteBancaire += (float)($dp['carte'] ?? 0);
            } else {
                $especesVentes += $montant;
            }
        }

        // Recouvrements espèces vs autres
        $recouvrementEspeces = 0;
        $recouvrementAutres = 0;
        foreach ($recouvrements as $r) {
            $rmode = strtolower(trim($r->mode_paiement ?: 'espèces'));
            if ($rmode === 'espèces' || $rmode === 'especes' || $rmode === 'comptant') {
                $recouvrementEspeces += (float)$r->montant;
            } else {
                $recouvrementAutres += (float)$r->montant;
            }
        }

        // Dépenses espèces vs autres
        $depensesEspeces = 0;
        $depensesAutres = 0;
        foreach ($depenses as $d) {
            $dmode = strtolower(trim($d->mode_paiement ?: 'especes'));
            if ($dmode === 'especes' || $dmode === 'espèces' || $dmode === 'caisse') {
                $depensesEspeces += (float)$d->montant;
            } else {
                $depensesAutres += (float)$d->montant;
            }
        }

        $totalRemise = $ventes->sum('remise') + $venteCredit->sum('remise');
        $totalNetCash = $ventes->sum('montant_total');
        $totalNetCredit = $venteCredit->sum('montant_total');
        $ventes_net = $totalNetCash + $totalNetCredit;
        $ventesBrut = $ventes_net + $totalRemise;
        $depensesTotal = $depenses->sum('montant');
        $beneficeNet = $ventes_net - $depensesTotal;

        $mobileMoneyTotal = $orangeMoney + $moovMoney + $wave;

        $totaux = [
            'ventes_brut' => $ventesBrut,
            'remises' => $totalRemise,
            'ventes_net' => $ventes_net,
            'ventes_annulee_net' => $venteAnnulee->sum('montant_total'),
            'ventes_credit_net' => $totalNetCredit,
            'especes_ventes' => $especesVentes,
            'orange_money' => $orangeMoney,
            'moov_money' => $moovMoney,
            'wave' => $wave,
            'mobile_money' => $mobileMoneyTotal,
            'carte_bancaire' => $carteBancaire,
            'recouvrement_especes' => $recouvrementEspeces,
            'recouvrement_autres' => $recouvrementAutres,
            'depenses_especes' => $depensesEspeces,
            'depenses_autres' => $depensesAutres,
            'depenses' => $depensesTotal,
            'benefice_net' => $beneficeNet
        ];

        $stats = [
            'nombre_ventes' => $ventes->count() + $venteCredit->count(),
            'nombre_depenses' => $depenses->count(),
            'nombre_recouvrements' => $recouvrements->count(),
            'vente_moyenne' => ($ventes->count() + $venteCredit->count()) > 0 ? $ventes_net / ($ventes->count() + $venteCredit->count()) : 0,
            'ventes_credit' => $venteCredit->count(),
            'ventes_cash' => $ventes->count(),
            'ventes_annulee' => $venteAnnulee->count()
        ];

        return [
            'boutique' => $boutique,
            'date' => $date,
            'ventes' => $ventes,
            'ventes_annulee' => $venteAnnulee,
            'ventes_credit' => $venteCredit,
            'depenses' => $depenses,
            'recouvrements' => $recouvrements,
            'totaux' => $totaux,
            'stats' => $stats
        ];
    }

    private function sendReportEmail(DailyReport $report)
    {
        $admins = \App\Models\User::where('role', 'admin')
            ->where('boutique_id', $report->boutique_id)
            ->whereNotNull('email')
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new DailyReportMail($report));
        }

        $report->sent_at = now();
        $report->save();
    }

    private function sendReportWhatsApp(DailyReport $report)
    {
        $admins = \App\Models\User::where('role', 'admin')
            ->where('boutique_id', $report->boutique_id)
            ->get();

        $boutiqueName = $report->boutique->nom;
        $date = $report->date->format('d/m/Y');
        $ventes = number_format($report->total_ventes, 0, '.', ' ');
        $benefice = number_format($report->benefice_net, 0, '.', ' ');

        $message = "📊 *Rapport Journalier - {$boutiqueName}*\n";
        $message .= "📅 Date: {$date}\n\n";
        $message .= "💰 Ventes Net: {$ventes} CFA\n";
        $message .= "📉 Dépenses: " . number_format($report->total_depenses, 0, '.', ' ') . " CFA\n";
        $message .= "✨ Bénéfice Net: *{$benefice} CFA*\n\n";
        $message .= "📝 Lien du rapport: " . url("/api/reports/{$report->id}/download");

        foreach ($admins as $admin) {
            $phone = $admin->telephone ?? $report->boutique->telephone;
            if ($phone) {
                $this->whatsApp->sendMessage($phone, $message);
            }
        }
    }
}
