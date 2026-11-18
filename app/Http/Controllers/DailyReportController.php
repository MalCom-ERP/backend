<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Boutique;
use App\Models\Vente;
use App\Models\Expense;
use App\Models\PaiementCredit;
use App\Models\Stock;
use App\Models\CommandeAchat;
use App\Models\PaiementFournisseur;
use App\Models\Inventaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Mail\DailyReportMail;
use Illuminate\Support\Facades\Mail;
use App\Services\WhatsAppService;

class DailyReportController extends Controller
{
    protected $whatsApp;
    private ?array $dailyReportColumnsCache = null;

    public function __construct(WhatsAppService $whatsApp)
    {
        $this->whatsApp = $whatsApp;
    }

    private function getDailyReportColumns(): array
    {
        if ($this->dailyReportColumnsCache === null) {
            $this->dailyReportColumnsCache = Schema::hasTable('daily_reports')
                ? array_flip(Schema::getColumnListing('daily_reports'))
                : [];
        }
        return $this->dailyReportColumnsCache;
    }

    private function dailyReportHasColumn(string $column): bool
    {
        return isset($this->getDailyReportColumns()[$column]);
    }

    private function assignDailyReportAttributes(DailyReport $report, array $attributes): void
    {
        $cols = $this->getDailyReportColumns();
        foreach ($attributes as $key => $value) {
            if (isset($cols[$key])) {
                $report->setAttribute($key, $value);
            }
        }
    }

    private function prepareBoutiqueForPdf(?Boutique $boutique): ?Boutique
    {
        if (!$boutique) {
            return null;
        }
        $clone = clone $boutique;
        $clone->logo = method_exists($boutique, 'getLogoForPdf') ? $boutique->getLogoForPdf() : null;
        return $clone;
    }

    private function isGlobalAdmin($user): bool
    {
        return $user && in_array($user->role, ['admin', 'admin1', 'superadmin'], true);
    }

    private function safeBoutiqueSlug(?Boutique $boutique): string
    {
        $slug = Str::slug($boutique?->nom ?: 'boutique');
        return $slug !== '' ? $slug : 'boutique';
    }

    public function index(Request $request)
    {
        $boutique_id = $this->getBoutiqueId();
        $hasClotureCol = $this->dailyReportHasColumn('cloture_par_user_id');
        $hasStatutCol = $this->dailyReportHasColumn('statut_cloture');

        $relations = ['boutique'];
        if ($hasClotureCol) {
            $relations[] = 'cloturePar:id,name';
        }

        $query = DailyReport::with($relations)
            ->when($boutique_id, fn($q) => $q->where('boutique_id', $boutique_id))
            ->orderBy('date', 'desc');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s, $hasStatutCol) {
                $q->where('date', 'LIKE', "%{$s}%");
                if ($hasStatutCol) {
                    $q->orWhere('statut_cloture', 'LIKE', "%{$s}%");
                }
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

        $existingQuery = DailyReport::where('boutique_id', $boutiqueId)->whereDate('date', $date);
        if ($this->dailyReportHasColumn('cloture_par_user_id')) {
            $existingQuery->with('cloturePar:id,name');
        }
        $existingReport = $existingQuery->first();

        $data = $this->loadReportData($boutiqueId, $date);

        $fondDeCaisse = $existingReport ? (float)($existingReport->fond_de_caisse ?? 0) : 0;
        $totalEspecesTheorique = $fondDeCaisse + $data['totaux']['especes_ventes'] + $data['totaux']['recouvrement_especes'] - $data['totaux']['depenses_especes'];

        $isClosed = $existingReport && ($existingReport->statut_cloture ?? null) === 'cloturee';
        $clotureParNom = ($existingReport && $existingReport->relationLoaded('cloturePar') && $existingReport->cloturePar)
            ? $existingReport->cloturePar->name
            : 'Caissier';

        return response()->json([
            'date' => $date,
            'boutique' => $boutique,
            'is_closed' => $isClosed,
            'statut_cloture' => $existingReport ? ($existingReport->statut_cloture ?? 'ouverte') : 'ouverte',
            'cloture_info' => $existingReport ? [
                'id' => $existingReport->id,
                'cloture_par' => $clotureParNom,
                'created_at' => $existingReport->created_at ? $existingReport->created_at->format('H:i') : null,
                'total_especes_physique' => (float)($existingReport->total_especes_physique ?? 0),
                'ecart_caisse' => (float)($existingReport->ecart_caisse ?? 0),
                'notes_cloture' => $existingReport->notes_cloture ?? null,
                'billetage' => $existingReport->billetage ?? [],
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
                'total_surplus' => $data['totaux']['surplus'] ?? 0,
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

        $report = DailyReport::where('boutique_id', $boutiqueId)
            ->whereDate('date', $date)
            ->first();

        if (!$report) {
            $report = new DailyReport();
            $report->boutique_id = $boutiqueId;
            $report->date = $date;
        }

        $this->assignDailyReportAttributes($report, [
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
            'notes_cloture' => $request->notes_cloture,
        ]);
        $report->save();

        // Générer le PDF A4 officiel
        try {
            $pdfBoutique = $this->prepareBoutiqueForPdf($boutique);
            $pdf = PDF::loadView('pdf.rapport_journalier', array_merge($data, [
                'boutique' => $pdfBoutique,
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

            $slug = $this->safeBoutiqueSlug($boutique);
            $filename = "rapports/rapport-{$slug}-{$date}.pdf";
            Storage::put($filename, $pdf->output());
            $report->pdf_path = $filename;
            $report->save();
        } catch (\Throwable $e) {
            \Log::warning("Génération PDF clôture ignorée : " . $e->getMessage());
        }

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

        $reloadRelations = ['boutique'];
        if ($this->dailyReportHasColumn('cloture_par_user_id')) {
            $reloadRelations[] = 'cloturePar:id,name';
        }
        $loadedReport = $report->load($reloadRelations);

        return response()->json([
            'message' => 'Clôture de caisse enregistrée avec succès',
            'report' => $loadedReport,
            'totaux' => $data['totaux'],
            'ecart' => $ecart,
            'whatsapp_message' => $whatsappMsg,
            'whatsapp_link' => $whatsappLink,
            'ticket_z' => $this->buildTicketZData($loadedReport, $boutique, $data)
        ], 200);
    }

    /**
     * Génère les données structurées du Ticket Z pour impression thermique 80mm (Axe 4).
     */
    public function ticketZ($id)
    {
        $relations = ['boutique'];
        if ($this->dailyReportHasColumn('cloture_par_user_id')) {
            $relations[] = 'cloturePar:id,name';
        }
        $report = DailyReport::with($relations)->findOrFail($id);
        $data = $this->loadReportData($report->boutique_id, $report->date->format('Y-m-d'));
        $ticketData = $this->buildTicketZData($report, $report->boutique, $data);

        return response()->json($ticketData, 200);
    }

    private function buildTicketZData($report, $boutique, $data)
    {
        $clotureParNom = ($report->relationLoaded('cloturePar') && $report->cloturePar)
            ? $report->cloturePar->name
            : 'Caissier';

        return [
            'type' => 'TICKET_Z_CLOTURE',
            'boutique' => [
                'nom' => $boutique?->nom ?? 'MALCOM COMMERCE',
                'adresse' => $boutique?->adresse ?? '',
                'telephone' => $boutique?->telephone ?? '',
            ],
            'report_id' => $report->id,
            'date' => $report->date->format('d/m/Y'),
            'heure_cloture' => $report->updated_at ? $report->updated_at->format('H:i:s') : date('H:i:s'),
            'cloture_par' => $clotureParNom,
            'fond_de_caisse' => (float)($report->fond_de_caisse ?? 0),
            'ventes_especes' => (float)$data['totaux']['especes_ventes'],
            'recouvrements_especes' => (float)$data['totaux']['recouvrement_especes'],
            'depenses_especes' => (float)$data['totaux']['depenses_especes'],
            'solde_theorique' => (float)($report->total_especes_theorique ?? 0),
            'especes_physique' => (float)($report->total_especes_physique ?? 0),
            'ecart_caisse' => (float)($report->ecart_caisse ?? 0),
            'mobile_money' => (float)($report->total_mobile_money ?? $data['totaux']['mobile_money']),
            'carte_bancaire' => (float)($report->total_carte_bancaire ?? $data['totaux']['carte_bancaire']),
            'ventes_credit' => (float)($report->total_credit ?? $data['totaux']['ventes_credit_net']),
            'total_ventes_net' => (float)($report->total_ventes ?? $data['totaux']['ventes_net']),
            'total_depenses' => (float)($report->total_depenses ?? $data['totaux']['depenses']),
            'benefice_net' => (float)($report->benefice_net ?? $data['totaux']['benefice_net']),
            'nombre_ventes' => (int)($report->nombre_ventes ?? $data['stats']['nombre_ventes']),
            'nombre_depenses' => (int)($report->nombre_depenses ?? $data['stats']['nombre_depenses']),
            'billetage' => $report->billetage ?: [],
            'notes' => $report->notes_cloture ?? null
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
        if (!$boutiqueId) {
            return response()->json(['success' => false, 'message' => 'Boutique introuvable'], 400);
        }
        $date = Carbon::parse($request->date)->format('Y-m-d');

        try {
            $exists = DailyReport::where('boutique_id', $boutiqueId)->whereDate('date', $date)->exists();
            $report = $this->generateReport($boutiqueId, $date);

            return response()->json([
                'success' => true,
                'message' => $exists ? 'Rapport mis à jour avec succès' : 'Rapport généré avec succès',
                'report' => $report
            ]);
        } catch (\Throwable $e) {
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
        $relations = ['boutique'];
        if ($this->dailyReportHasColumn('cloture_par_user_id')) {
            $relations[] = 'cloturePar:id,name';
        }
        $report = DailyReport::with($relations)->findOrFail($id);

        if ($user && !$this->isGlobalAdmin($user) && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return response()->json($report);
    }

    public function download($id)
    {
        $user = Auth::user();
        $relations = ['boutique'];
        if ($this->dailyReportHasColumn('cloture_par_user_id')) {
            $relations[] = 'cloturePar:id,name';
        }
        $report = DailyReport::with($relations)->findOrFail($id);

        if ($user && !$this->isGlobalAdmin($user) && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $data = $this->loadReportData($report->boutique_id, $report->date->format('Y-m-d'));
        $boutiqueModel = $report->boutique ?: $data['boutique'];
        $pdf = PDF::loadView('pdf.rapport_journalier', array_merge($data, [
            'boutique' => $this->prepareBoutiqueForPdf($boutiqueModel),
            'report' => $report,
            'fond_de_caisse' => $report->fond_de_caisse ?? 0,
            'total_especes_theorique' => $report->total_especes_theorique ?? 0,
            'total_especes_physique' => $report->total_especes_physique ?? 0,
            'ecart_caisse' => $report->ecart_caisse ?? 0,
            'billetage' => $report->billetage ?: [],
        ]))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif'
            ]);

        $slug = $this->safeBoutiqueSlug($boutiqueModel);
        $filename = "rapport-{$slug}-{$report->date->format('Y-m-d')}.pdf";
        return $pdf->download($filename);
    }

    public function sendEmail($id)
    {
        $user = Auth::user();
        $report = DailyReport::with('boutique')->findOrFail($id);

        if ($user && !$this->isGlobalAdmin($user) && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        try {
            $this->sendReportEmail($report);

            return response()->json([
                'success' => true,
                'message' => 'Rapport envoyé par email avec succès'
            ]);
        } catch (\Throwable $e) {
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

        if ($user && !$this->isGlobalAdmin($user) && $report->boutique_id !== $user->boutique_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        try {
            $this->sendReportWhatsApp($report);

            return response()->json([
                'success' => true,
                'message' => 'Rapport envoyé via WhatsApp avec succès'
            ]);
        } catch (\Throwable $e) {
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

        $report = DailyReport::where('boutique_id', $boutiqueId)
            ->whereDate('date', $date)
            ->first();

        if (!$report) {
            $report = new DailyReport();
            $report->boutique_id = $boutiqueId;
            $report->date = $date;
        }

        $this->assignDailyReportAttributes($report, [
            'total_ventes' => $data['totaux']['ventes_net'],
            'total_depenses' => $data['totaux']['depenses'],
            'benefice_net' => $data['totaux']['benefice_net'],
            'total_mobile_money' => $data['totaux']['mobile_money'],
            'total_carte_bancaire' => $data['totaux']['carte_bancaire'],
            'total_credit' => $data['totaux']['ventes_credit_net'],
            'total_recouvrement' => $data['totaux']['recouvrement_especes'] + $data['totaux']['recouvrement_autres'],
            'nombre_ventes' => $data['stats']['nombre_ventes'],
            'nombre_depenses' => $data['stats']['nombre_depenses'],
        ]);
        $report->save();

        try {
            $pdf = PDF::loadView('pdf.rapport_journalier', array_merge($data, [
                'boutique' => $this->prepareBoutiqueForPdf($boutique),
                'report' => $report,
            ]))
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'sans-serif'
                ]);

            $slug = $this->safeBoutiqueSlug($boutique);
            $filename = "rapports/rapport-{$slug}-{$date}.pdf";
            Storage::put($filename, $pdf->output());

            $report->pdf_path = $filename;
            $report->save();
        } catch (\Throwable $e) {
            \Log::warning("Génération PDF rapport ignorée : " . $e->getMessage());
        }

        return $report;
    }

    /**
     * Calcule les agrégats financiers et la ventilation par canal de paiement
     * pour un ensemble donné de ventes, recouvrements et dépenses.
     */
    private function computePeriodFinancials($ventes, $venteCredit, $venteAnnulee, $recouvrements, $depenses): array
    {
        $especesVentes = 0;
        $orangeMoney = 0;
        $moovMoney = 0;
        $wave = 0;
        $carteBancaire = 0;

        foreach ($ventes as $v) {
            $mode = strtolower(trim($v->moyen_paiement ?: ($v->type_paiement ?: 'especes')));
            $montant = (float)$v->montant_total;

            if ($mode === 'especes' || $mode === 'comptant' || $mode === 'contant') {
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

        // Ajouter les avances/acomptes encaissés immédiatement sur les ventes à crédit de la période
        foreach ($venteCredit as $vc) {
            $avance = (float)($vc->montant_avance ?? 0);
            if ($avance > 0) {
                $dp = is_array($vc->details_paiement) ? $vc->details_paiement : null;
                $dpSum = $dp ? (
                    (float)($dp['especes'] ?? 0) +
                    (float)($dp['orange_money'] ?? 0) +
                    (float)($dp['moov_money'] ?? 0) +
                    (float)($dp['wave'] ?? 0) +
                    (float)($dp['carte'] ?? 0)
                ) : 0;

                if ($dp && $dpSum > 0) {
                    $especesVentes += (float)($dp['especes'] ?? 0);
                    $orangeMoney += (float)($dp['orange_money'] ?? 0);
                    $moovMoney += (float)($dp['moov_money'] ?? 0);
                    $wave += (float)($dp['wave'] ?? 0);
                    $carteBancaire += (float)($dp['carte'] ?? 0);
                } else {
                    $modeAvance = strtolower(trim($vc->moyen_paiement ?: 'especes'));
                    if (str_contains($modeAvance, 'orange')) {
                        $orangeMoney += $avance;
                    } elseif (str_contains($modeAvance, 'moov')) {
                        $moovMoney += $avance;
                    } elseif (str_contains($modeAvance, 'wave')) {
                        $wave += $avance;
                    } elseif (str_contains($modeAvance, 'carte') || str_contains($modeAvance, 'cheque') || str_contains($modeAvance, 'virement')) {
                        $carteBancaire += $avance;
                    } else {
                        $especesVentes += $avance;
                    }
                }
            }
        }

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

        $totalRemise = (float)$ventes->sum('remise') + (float)$venteCredit->sum('remise');
        $totalNetCash = (float)$ventes->sum('montant_total');
        $totalNetCredit = (float)$venteCredit->sum('montant_total');
        $ventesNet = $totalNetCash + $totalNetCredit;
        $ventesBrut = $ventesNet + $totalRemise;
        $depensesTotal = (float)$depenses->sum('montant');
        $beneficeNet = $ventesNet - $depensesTotal;
        $mobileMoneyTotal = $orangeMoney + $moovMoney + $wave;
        $totalRecouvrements = $recouvrementEspeces + $recouvrementAutres;
        $totalEncaisseReel = $especesVentes + $mobileMoneyTotal + $carteBancaire + $totalRecouvrements;
        $nbVentes = $ventes->count() + $venteCredit->count();

        return [
            'totaux' => [
                'ventes_brut' => $ventesBrut,
                'remises' => $totalRemise,
                'surplus' => (float)$ventes->sum('surplus') + (float)$venteCredit->sum('surplus'),
                'ventes_net' => $ventesNet,
                'ventes_cash_net' => $totalNetCash,
                'ventes_annulee_net' => (float)$venteAnnulee->sum('montant_total'),
                'ventes_credit_net' => $totalNetCredit,
                'especes_ventes' => $especesVentes,
                'orange_money' => $orangeMoney,
                'moov_money' => $moovMoney,
                'wave' => $wave,
                'mobile_money' => $mobileMoneyTotal,
                'carte_bancaire' => $carteBancaire,
                'recouvrement_especes' => $recouvrementEspeces,
                'recouvrement_autres' => $recouvrementAutres,
                'total_recouvrements' => $totalRecouvrements,
                'total_encaisse_reel' => $totalEncaisseReel,
                'depenses_especes' => $depensesEspeces,
                'depenses_autres' => $depensesAutres,
                'depenses' => $depensesTotal,
                'benefice_net' => $beneficeNet
            ],
            'stats' => [
                'nombre_ventes' => $nbVentes,
                'nombre_depenses' => $depenses->count(),
                'nombre_recouvrements' => $recouvrements->count(),
                'vente_moyenne' => $nbVentes > 0 ? $ventesNet / $nbVentes : 0,
                'ventes_credit' => $venteCredit->count(),
                'ventes_cash' => $ventes->count(),
                'ventes_annulee' => $venteAnnulee->count()
            ],
        ];
    }

    public function loadReportData($boutiqueId, $date)
    {
        $boutique = Boutique::findOrFail($boutiqueId);

        $ventes = Vente::with(['detailVentes.produit', 'client', 'user'])
            ->where('boutique_id', $boutiqueId)
            ->whereIn('statut', ['payee', 'validee'])
            ->where('type_paiement', '!=', 'credit')
            ->where('type_paiement', '!=', 'proforma')
            ->whereDate('date_vente', $date)
            ->get();

        $venteCredit = Vente::with(['detailVentes.produit', 'client', 'user'])
            ->where('boutique_id', $boutiqueId)
            ->where('type_paiement', 'credit')
            ->whereIn('statut', ['credit', 'payee', 'validee'])
            ->whereDate('date_vente', $date)
            ->get();

        $venteAnnulee = Vente::with(['detailVentes.produit', 'client', 'user'])
            ->where('boutique_id', $boutiqueId)
            ->whereIn('statut', ['annulee', 'Credit annule'])
            ->whereDate('date_vente', $date)
            ->get();

        $depenses = Expense::with('user')
            ->where('boutique_id', $boutiqueId)
            ->whereDate('date', $date)
            ->get();

        $recouvrements = Schema::hasTable('paiements_credit')
            ? PaiementCredit::with('user')
                ->whereHas('vente', fn($q) => $q->where('boutique_id', $boutiqueId))
                ->whereDate('date_paiement', $date)
                ->get()
            : collect();

        $financials = $this->computePeriodFinancials($ventes, $venteCredit, $venteAnnulee, $recouvrements, $depenses);

        return [
            'boutique' => $boutique,
            'date' => $date,
            'ventes' => $ventes,
            'ventes_annulee' => $venteAnnulee,
            'ventes_credit' => $venteCredit,
            'depenses' => $depenses,
            'recouvrements' => $recouvrements,
            'totaux' => $financials['totaux'],
            'stats' => $financials['stats']
        ];
    }

    /**
     * Résout les bornes [Lundi..Dimanche] (ou start_date..end_date explicite, max 31 jours) pour le rapport hebdomadaire.
     */
    public function resolveWeekBounds(Request $request): array
    {
        if ($request->filled('week') && preg_match('/^(\d{4})-W(\d{2})$/', trim((string) $request->input('week')), $m)) {
            $start = Carbon::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
            return [$start, $end];
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->input('start_date'))->startOfDay();
            $end = Carbon::parse($request->input('end_date'))->startOfDay();
            if ($end->lt($start)) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'La date de fin doit être postérieure ou égale à la date de début.'
                ], 422));
            }
            if ($start->diffInDays($end) > 31) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'La période du rapport hebdomadaire ne peut pas dépasser 31 jours.'
                ], 422));
            }
            return [$start, $end];
        }

        $anchor = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))
            : ($request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::now());

        $start = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $end = $anchor->copy()->endOfWeek(Carbon::SUNDAY);

        return [$start, $end];
    }

    /**
     * Charge la synthèse managériale hebdomadaire de la boutique.
     * IMPORTANT : Ne contient volontairement AUCUNE liste des produits achetés ou vendus.
     */
    public function loadWeeklyReportData(int $boutiqueId, Carbon $startDate, Carbon $endDate): array
    {
        $boutique = Boutique::with('nature')->findOrFail($boutiqueId);
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');
        $daysCount = $startDate->diffInDays($endDate) + 1;

        $ventes = Vente::with(['detailVentes:id,vente_id,produit_id,quantite,prix_unitaire,montant_total,remise', 'user:id,name'])
            ->where('boutique_id', $boutiqueId)
            ->whereIn('statut', ['payee', 'validee'])
            ->where('type_paiement', '!=', 'credit')
            ->where('type_paiement', '!=', 'proforma')
            ->whereDate('date_vente', '>=', $startStr)
            ->whereDate('date_vente', '<=', $endStr)
            ->get();

        $venteCredit = Vente::with(['detailVentes:id,vente_id,produit_id,quantite,prix_unitaire,montant_total,remise', 'user:id,name'])
            ->where('boutique_id', $boutiqueId)
            ->where('type_paiement', 'credit')
            ->whereIn('statut', ['credit', 'payee', 'validee'])
            ->whereDate('date_vente', '>=', $startStr)
            ->whereDate('date_vente', '<=', $endStr)
            ->get();

        $venteAnnulee = Vente::where('boutique_id', $boutiqueId)
            ->whereIn('statut', ['annulee', 'Credit annule'])
            ->whereDate('date_vente', '>=', $startStr)
            ->whereDate('date_vente', '<=', $endStr)
            ->get();

        $depenses = Expense::where('boutique_id', $boutiqueId)
            ->whereDate('date', '>=', $startStr)
            ->whereDate('date', '<=', $endStr)
            ->get();

        $recouvrements = Schema::hasTable('paiements_credit')
            ? PaiementCredit::whereHas('vente', fn($q) => $q->where('boutique_id', $boutiqueId))
                ->whereDate('date_paiement', '>=', $startStr)
                ->whereDate('date_paiement', '<=', $endStr)
                ->get()
            : collect();

        $financials = $this->computePeriodFinancials($ventes, $venteCredit, $venteAnnulee, $recouvrements, $depenses);
        $totaux = $financials['totaux'];
        $stats = $financials['stats'];

        // Coût d'achat global des marchandises vendues & marge brute (aucune liste de produits exposée)
        $stockCostByProduct = Stock::where('boutique_id', $boutiqueId)->pluck('prix_achat', 'produit_id');
        $allValidSales = $ventes->concat($venteCredit);
        $coutAchatMarchandises = 0;
        $totalUnitesVendues = 0;

        foreach ($allValidSales as $sale) {
            foreach ($sale->detailVentes as $dv) {
                $qte = (float) ($dv->quantite ?? 0);
                $pa = (float) ($stockCostByProduct[$dv->produit_id] ?? 0);
                $totalUnitesVendues += $qte;
                $coutAchatMarchandises += ($qte * $pa);
            }
        }

        $ventesNet = $totaux['ventes_net'];
        $margeBrute = $ventesNet - $coutAchatMarchandises;
        $tauxMargeBrute = $ventesNet > 0 ? round(($margeBrute / $ventesNet) * 100, 1) : 0;
        $resultatNetReel = $margeBrute - $totaux['depenses'];

        $totaux['cout_achat_estime'] = round($coutAchatMarchandises, 2);
        $totaux['marge_brute'] = round($margeBrute, 2);
        $totaux['taux_marge_pct'] = $tauxMargeBrute;
        $totaux['resultat_net_reel'] = round($resultatNetReel, 2);

        $stats['total_unites_vendues'] = $totalUnitesVendues;
        $stats['moyenne_journaliere_ca'] = $daysCount > 0 ? round($ventesNet / $daysCount, 2) : 0;

        // Comparaison avec la semaine / période précédente (N-1)
        $prevStart = $startDate->copy()->subDays($daysCount);
        $prevEnd = $startDate->copy()->subDay();
        $prevVentes = Vente::where('boutique_id', $boutiqueId)
            ->where(function ($q) {
                $q->where(function ($q1) {
                    $q1->whereIn('statut', ['payee', 'validee'])
                       ->where('type_paiement', '!=', 'credit')
                       ->where('type_paiement', '!=', 'proforma');
                })->orWhere(function ($q2) {
                    $q2->where('type_paiement', 'credit')
                       ->whereIn('statut', ['credit', 'payee', 'validee']);
                });
            })
            ->whereDate('date_vente', '>=', $prevStart->format('Y-m-d'))
            ->whereDate('date_vente', '<=', $prevEnd->format('Y-m-d'))
            ->get(['id', 'montant_total']);

        $prevCa = (float) $prevVentes->sum('montant_total');
        $prevNbVentes = $prevVentes->count();
        $prevDepenses = (float) Expense::where('boutique_id', $boutiqueId)
            ->whereDate('date', '>=', $prevStart->format('Y-m-d'))
            ->whereDate('date', '<=', $prevEnd->format('Y-m-d'))
            ->sum('montant');

        $variationCaPct = $prevCa > 0 ? round((($ventesNet - $prevCa) / $prevCa) * 100, 1) : null;
        $variationVentesPct = $prevNbVentes > 0 ? round((($stats['nombre_ventes'] - $prevNbVentes) / $prevNbVentes) * 100, 1) : null;

        // Évolution jour par jour sur la période sélectionnée
        $joursFr = [0 => 'Dim', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam'];
        $cashByDate = $ventes->groupBy(fn($v) => Carbon::parse($v->date_vente)->format('Y-m-d'));
        $creditByDate = $venteCredit->groupBy(fn($v) => Carbon::parse($v->date_vente)->format('Y-m-d'));
        $depensesByDate = $depenses->groupBy(fn($d) => Carbon::parse($d->date)->format('Y-m-d'));
        $recouvByDate = $recouvrements->groupBy(fn($r) => Carbon::parse($r->date_paiement)->format('Y-m-d'));

        $dailyBreakdown = [];
        $bestDay = null;
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $dKey = $cursor->format('Y-m-d');
            $dayCashCol = $cashByDate->get($dKey, collect());
            $dayCreditCol = $creditByDate->get($dKey, collect());
            $dayDepCol = $depensesByDate->get($dKey, collect());
            $dayRecCol = $recouvByDate->get($dKey, collect());

            $caComptant = (float) $dayCashCol->sum('montant_total');
            $caCredit = (float) $dayCreditCol->sum('montant_total');
            $caJour = $caComptant + $caCredit;
            $depJour = (float) $dayDepCol->sum('montant');
            $recJour = (float) $dayRecCol->sum('montant');
            $nbVtesJour = $dayCashCol->count() + $dayCreditCol->count();

            $entry = [
                'date' => $dKey,
                'date_fr' => $cursor->format('d/m/Y'),
                'jour_label' => ($joursFr[$cursor->dayOfWeek] ?? '') . ' ' . $cursor->format('d/m'),
                'nb_ventes' => $nbVtesJour,
                'ca_comptant' => $caComptant,
                'ca_credit' => $caCredit,
                'ca_total' => $caJour,
                'depenses' => $depJour,
                'recouvrements' => $recJour,
                'solde_net' => $caJour - $depJour,
                'is_best_day' => false,
            ];
            $dailyBreakdown[] = $entry;

            if ($bestDay === null || $caJour > $bestDay['ca_total']) {
                $bestDay = $entry;
            }
            $cursor->addDay();
        }

        if ($bestDay !== null && $bestDay['ca_total'] > 0) {
            foreach ($dailyBreakdown as &$dayRow) {
                if ($dayRow['date'] === $bestDay['date']) {
                    $dayRow['is_best_day'] = true;
                }
            }
            unset($dayRow);
        } else {
            $bestDay = null;
        }

        // Dépenses regroupées par catégorie
        $depensesParCategorie = [];
        foreach ($depenses->groupBy(fn($d) => strtoupper(trim((string) ($d->type ?: 'DIVERS')))) as $cat => $items) {
            $mnt = (float) $items->sum('montant');
            $depensesParCategorie[] = [
                'categorie' => $cat,
                'count' => $items->count(),
                'montant' => $mnt,
                'part_pct' => $totaux['depenses'] > 0 ? round(($mnt / $totaux['depenses']) * 100, 1) : 0,
            ];
        }
        usort($depensesParCategorie, fn($a, $b) => $b['montant'] <=> $a['montant']);

        // Synthèse Crédits & Clients (sans liste d'articles)
        $hasMontantRestant = Schema::hasColumn('ventes', 'montant_restant');
        $encoursCreditGlobal = $hasMontantRestant
            ? (float) Vente::where('boutique_id', $boutiqueId)
                ->where('type_paiement', 'credit')
                ->where('statut', 'credit')
                ->sum('montant_restant')
            : 0;
        $avancesCreditSemaine = (float) $venteCredit->sum('montant_avance');
        $nouveauResteCreditSemaine = $hasMontantRestant
            ? (float) $venteCredit->sum('montant_restant')
            : max(0, $totaux['ventes_credit_net'] - $avancesCreditSemaine);

        $clientsSummary = [
            'clients_uniques_servis' => $allValidSales->pluck('client_id')->filter()->unique()->count(),
            'nouveaux_credits_semaine' => $totaux['ventes_credit_net'],
            'avances_sur_credits' => $avancesCreditSemaine,
            'reste_nouveaux_credits' => $nouveauResteCreditSemaine,
            'recouvrements_nb' => $recouvrements->count(),
            'recouvrements_montant' => $totaux['total_recouvrements'],
            'encours_credit_global' => $encoursCreditGlobal,
        ];

        // Synthèse globale des achats & approvisionnements (TOTAUX UNIQUEMENT, zéro liste de produits)
        $achatsSummary = [
            'nb_commandes' => 0,
            'montant_total' => 0,
            'montant_paye' => 0,
            'reste_a_payer' => 0,
            'paiements_fournisseurs_semaine' => 0,
            'nb_entrees_stock' => 0,
            'unites_entrees_stock' => 0,
        ];

        if (Schema::hasTable('commandes_achats')) {
            $achatsQuery = CommandeAchat::where('boutique_id', $boutiqueId)
                ->where(function ($q) use ($startStr, $endStr) {
                    $q->where(fn($q1) => $q1->whereDate('date_commande', '>=', $startStr)->whereDate('date_commande', '<=', $endStr))
                      ->orWhere(fn($q2) => $q2->whereDate('created_at', '>=', $startStr)->whereDate('created_at', '<=', $endStr));
                })
                ->where('statut', '!=', 'annulee')
                ->get(['id', 'montant_total', 'montant_paye', 'montant_restant']);

            $achatsSummary['nb_commandes'] = $achatsQuery->count();
            $achatsSummary['montant_total'] = (float) $achatsQuery->sum('montant_total');
            $achatsSummary['montant_paye'] = (float) $achatsQuery->sum('montant_paye');
            $achatsSummary['reste_a_payer'] = (float) $achatsQuery->sum('montant_restant');
        }

        if (Schema::hasTable('paiements_fournisseurs')) {
            $achatsSummary['paiements_fournisseurs_semaine'] = (float) PaiementFournisseur::where('boutique_id', $boutiqueId)
                ->whereDate('date_paiement', '>=', $startStr)
                ->whereDate('date_paiement', '<=', $endStr)
                ->sum('montant');
        }

        if (Schema::hasTable('inventaires')) {
            $entreesQ = Inventaire::where('boutique_id', $boutiqueId)
                ->where('type', 'ajout')
                ->where(function ($q) use ($startStr, $endStr) {
                    $q->whereDate('date', '>=', $startStr)->whereDate('date', '<=', $endStr);
                });
            $achatsSummary['nb_entrees_stock'] = (clone $entreesQ)->count();
            $achatsSummary['unites_entrees_stock'] = (float) (clone $entreesQ)->sum('quantite');
        }

        // Santé globale du stock (compteurs et valorisations globales UNIQUEMENT, zéro liste de produits)
        $stocks = Stock::where('boutique_id', $boutiqueId)
            ->whereHas('produit')
            ->get(['quantite', 'seuil_alerte', 'prix_achat', 'prix_vente']);

        $enStockCount = 0;
        $ruptureCount = 0;
        $alerteCount = 0;
        $valStockAchat = 0;
        $valStockVente = 0;
        foreach ($stocks as $stk) {
            $qte = (float) ($stk->quantite ?? 0);
            $seuil = (float) ($stk->seuil_alerte ?? 5);
            if ($qte > 0) {
                $enStockCount++;
                $valStockAchat += ($qte * (float) ($stk->prix_achat ?? 0));
                $valStockVente += ($qte * (float) ($stk->prix_vente ?? 0));
                if ($qte <= $seuil) {
                    $alerteCount++;
                }
            } else {
                $ruptureCount++;
            }
        }

        $stockHealth = [
            'total_references' => $stocks->count(),
            'en_stock_count' => $enStockCount,
            'alerte_count' => $alerteCount,
            'rupture_count' => $ruptureCount,
            'valeur_stock_achat' => round($valStockAchat, 2),
            'valeur_stock_vente' => round($valStockVente, 2),
            'marge_latente_stock' => round($valStockVente - $valStockAchat, 2),
        ];

        // Performance par vendeur / caissier sur la semaine
        $performanceVendeurs = [];
        foreach ($allValidSales->groupBy(fn($s) => $s->user_id ?: 0) as $uid => $salesGroup) {
            $firstSale = $salesGroup->first();
            $sellerName = ($firstSale && $firstSale->user) ? $firstSale->user->name : 'Équipe Comptoir';
            $caVendeur = (float) $salesGroup->sum('montant_total');
            $nbVtesVendeur = $salesGroup->count();
            $performanceVendeurs[] = [
                'nom' => $sellerName,
                'nb_ventes' => $nbVtesVendeur,
                'ca_total' => $caVendeur,
                'part_pct' => $ventesNet > 0 ? round(($caVendeur / $ventesNet) * 100, 1) : 0,
                'panier_moyen' => $nbVtesVendeur > 0 ? round($caVendeur / $nbVtesVendeur, 0) : 0,
            ];
        }
        usort($performanceVendeurs, fn($a, $b) => $b['ca_total'] <=> $a['ca_total']);

        // Suivi des clôtures de caisse sur la semaine
        $dailyReportsWeek = DailyReport::where('boutique_id', $boutiqueId)
            ->whereDate('date', '>=', $startStr)
            ->whereDate('date', '<=', $endStr)
            ->get();

        $cloturesResume = [
            'jours_periode' => $daysCount,
            'rapports_generes' => $dailyReportsWeek->count(),
            'caisses_cloturees' => $this->dailyReportHasColumn('statut_cloture')
                ? $dailyReportsWeek->where('statut_cloture', 'cloturee')->count()
                : $dailyReportsWeek->count(),
            'cumul_ecart_caisse' => $this->dailyReportHasColumn('ecart_caisse')
                ? (float) $dailyReportsWeek->sum('ecart_caisse')
                : 0,
        ];

        return [
            'boutique' => $boutique,
            'periode' => [
                'start_date' => $startStr,
                'end_date' => $endStr,
                'start_fr' => $startDate->format('d/m/Y'),
                'end_fr' => $endDate->format('d/m/Y'),
                'semaine_iso' => $startDate->isoWeek(),
                'annee' => $startDate->isoWeekYear(),
                'jours_count' => $daysCount,
            ],
            'comparaison' => [
                'prev_start_fr' => $prevStart->format('d/m/Y'),
                'prev_end_fr' => $prevEnd->format('d/m/Y'),
                'prev_ca' => $prevCa,
                'prev_nb_ventes' => $prevNbVentes,
                'prev_depenses' => $prevDepenses,
                'variation_ca_pct' => $variationCaPct,
                'variation_ventes_pct' => $variationVentesPct,
            ],
            'totaux' => $totaux,
            'stats' => $stats,
            'best_day' => $bestDay,
            'daily_breakdown' => $dailyBreakdown,
            'depenses_par_categorie' => $depensesParCategorie,
            'clients_summary' => $clientsSummary,
            'achats_summary' => $achatsSummary,
            'stock_health' => $stockHealth,
            'performance_vendeurs' => $performanceVendeurs,
            'clotures_resume' => $cloturesResume,
        ];
    }

    /**
     * Aperçu JSON du rapport hebdomadaire (sans liste de produits achetés/vendus).
     */
    public function weeklySummary(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique introuvable'], 400);
        }

        [$startDate, $endDate] = $this->resolveWeekBounds($request);

        try {
            $data = $this->loadWeeklyReportData($boutiqueId, $startDate, $endDate);
            return response()->json($data, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du calcul du rapport hebdomadaire',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Téléchargement PDF A4 du rapport hebdomadaire de boutique (sans liste de produits).
     */
    public function downloadWeekly(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique introuvable'], 400);
        }

        [$startDate, $endDate] = $this->resolveWeekBounds($request);

        try {
            $data = $this->loadWeeklyReportData($boutiqueId, $startDate, $endDate);
            $boutiqueModel = $data['boutique'];
            $data['boutique'] = $this->prepareBoutiqueForPdf($boutiqueModel);

            $pdf = PDF::loadView('pdf.rapport_hebdomadaire', $data)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'sans-serif'
                ]);

            $slug = $this->safeBoutiqueSlug($boutiqueModel);
            $filename = "rapport-hebdomadaire-{$slug}-{$startDate->format('Y-m-d')}_au_{$endDate->format('Y-m-d')}.pdf";
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la génération du PDF hebdomadaire',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function sendReportEmail(DailyReport $report)
    {
        $admins = \App\Models\User::whereIn('role', ['admin', 'admin1'])
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
        $admins = \App\Models\User::whereIn('role', ['admin', 'admin1'])
            ->where('boutique_id', $report->boutique_id)
            ->get();

        $boutiqueName = $report->boutique?->nom ?? 'MalCom';
        $date = $report->date->format('d/m/Y');
        $ventes = number_format($report->total_ventes, 0, '.', ' ');
        $benefice = number_format($report->benefice_net, 0, '.', ' ');

        $message = "📊 *Rapport Journalier - {$boutiqueName}*\n";
        $message .= "📅 Date: {$date}\n\n";
        $message .= "💰 Ventes Net: {$ventes} CFA\n";
        $message .= "📉 Dépenses: " . number_format($report->total_depenses, 0, '.', ' ') . " CFA\n";
        $message .= "✨ Bénéfice Net: *{$benefice} CFA*\n\n";
        $message .= "📝 Lien du rapport: " . url("/api/rapports/{$report->id}/download");

        foreach ($admins as $admin) {
            $phone = $admin->telephone ?? $report->boutique?->telephone;
            if ($phone) {
                $this->whatsApp->sendMessage($phone, $message);
            }
        }
    }
}
