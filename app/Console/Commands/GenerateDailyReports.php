<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Boutique;
use App\Http\Controllers\DailyReportController;
use App\Mail\DailyReportMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class GenerateDailyReports extends Command
{
    protected $signature = 'reports:generate-daily {--date= : Date for the report (Y-m-d format)}';
    protected $description = 'Generate daily reports for all active boutiques and send via email';

    public function handle()
    {
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date'))->format('Y-m-d')
            : Carbon::yesterday()->format('Y-m-d');

        $this->info("Génération des rapports journaliers pour le {$date}...");

        $boutiques = Boutique::where('is_active', 1)->get();

        if ($boutiques->isEmpty()) {
            $this->warn('Aucune boutique active trouvée.');
            return 0;
        }

        $controller = app(DailyReportController::class);
        $successCount = 0;
        $errorCount = 0;

        foreach ($boutiques as $boutique) {
            try {
                $this->info("Traitement de la boutique: {$boutique->nom}");

                $report = $controller->generateReport($boutique->id, $date);

                $recipients = \App\Models\User::where(function ($q) use ($boutique) {
                        $q->where('id', $boutique->user_id)
                          ->orWhere('boutique_id', $boutique->id);
                    })
                    ->whereIn('role', ['admin', 'admin1', 'gestionnaire'])
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get();

                // Si aucun destinataire direct n'est trouvé, envoyer aux super-admins globaux
                if ($recipients->isEmpty()) {
                    $recipients = \App\Models\User::whereIn('role', ['admin', 'admin1'])
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->get();
                }

                if ($recipients->isEmpty()) {
                    $this->warn("  ⚠ Aucun administrateur ou gestionnaire avec email trouvé pour {$boutique->nom}");
                    continue;
                }

                foreach ($recipients as $recipient) {
                    Mail::to($recipient->email)->send(new DailyReportMail($report));
                    $this->info("  ✓ Email envoyé à {$recipient->email}");
                }

                // Send via WhatsApp
                $controller->sendWhatsApp($report->id);
                $this->info("  ✓ Notification WhatsApp envoyée");

                $report->sent_at = now();
                $report->save();

                $successCount++;
                $this->info("  ✓ Rapport généré et envoyé avec succès");

            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ✗ Erreur pour {$boutique->nom}: {$e->getMessage()}");
            }
        }

        $this->info("\n=== Résumé ===");
        $this->info("Boutiques traitées: {$boutiques->count()}");
        $this->info("Succès: {$successCount}");
        $this->info("Erreurs: {$errorCount}");

        return $errorCount > 0 ? 1 : 0;
    }
}
