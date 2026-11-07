<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackupController;
use App\Models\Boutique;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupAccountsCommand extends Command
{
    /**
     * Signature de la commande Artisan.
     */
    protected $signature = 'backup:accounts {--retention=14 : Nombre de jours de conservation des sauvegardes}';

    /**
     * Description de la commande.
     */
    protected $description = 'Génère une sauvegarde automatique SQL par Compte Principal (et ses boutiques associées) ainsi qu\'une sauvegarde globale.';

    public function handle(): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(600);

        $retentionDays = max(1, (int)$this->option('retention'));
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupController = app(BackupController::class);

        $baseDir = storage_path('app/backups');
        $globalDir = "{$baseDir}/global";
        $accountsDir = "{$baseDir}/accounts";

        File::ensureDirectoryExists($globalDir);
        File::ensureDirectoryExists($accountsDir);

        $this->info("🚀 Démarrage de la sauvegarde automatique MalCom ({$timestamp})...");

        // 1. Sauvegarde globale intégrale
        $globalFile = "{$globalDir}/backup_global_{$timestamp}.sql";
        $outGlobal = fopen($globalFile, 'w');
        if ($outGlobal) {
            $backupController->writeFullDatabaseSqlDump($outGlobal);
            fclose($outGlobal);
            $this->info("✅ Sauvegarde globale créée : " . basename($globalFile));
        }

        // 2. Sauvegarde isolée pour chaque Compte Principal possédant au moins une boutique
        $ownerIds = Boutique::whereNotNull('user_id')->pluck('user_id')->unique()->toArray();
        $principalUsers = User::whereIn('id', $ownerIds)
            ->orWhere('role', 'admin')
            ->get()
            ->unique('id');

        $accountCount = 0;
        foreach ($principalUsers as $user) {
            $boutiqueIds = $backupController->getBoutiqueIdsForAccount($user);
            if (empty($boutiqueIds)) {
                continue;
            }

            $slug = Str::slug($user->name ?: 'compte-' . $user->id);
            $accountFolder = "{$accountsDir}/account_{$user->id}_{$slug}";
            File::ensureDirectoryExists($accountFolder);

            $filePath = "{$accountFolder}/backup_compte_{$user->id}_{$slug}_{$timestamp}.sql";
            $outAccount = fopen($filePath, 'w');
            if ($outAccount) {
                $backupController->writeScopedBoutiquesSqlDump(
                    $outAccount,
                    $boutiqueIds,
                    "COMPTE PRINCIPAL #{$user->id} : {$user->name} ({$user->email})",
                    $user->id
                );
                fclose($outAccount);
                $accountCount++;
                $this->info("   ↳ Compte Principal [{$user->name}] (" . count($boutiqueIds) . " boutique(s)) sauvegardé.");
            }
        }

        // 3. Rotation et nettoyage des sauvegardes de plus de X jours
        $cutoff = Carbon::now()->subDays($retentionDays)->getTimestamp();
        $deletedCount = 0;

        foreach (File::allFiles($baseDir) as $file) {
            if ($file->getMTime() < $cutoff && Str::endsWith($file->getFilename(), ['.sql', '.gz'])) {
                File::delete($file->getPathname());
                $deletedCount++;
            }
        }

        // Enregistrer les métadonnées de dernière exécution
        File::put("{$baseDir}/last_backup.json", json_encode([
            'last_run_at' => Carbon::now()->toIso8601String(),
            'last_run_formatted' => Carbon::now()->format('d/m/Y à H:i:s'),
            'accounts_backed_up' => $accountCount,
            'retention_days' => $retentionDays,
            'pruned_files' => $deletedCount,
        ], JSON_PRETTY_PRINT));

        $this->info("🎉 Sauvegarde terminée avec succès ({$accountCount} compte(s) principal(aux), {$deletedCount} ancien(s) fichier(s) purgé(s)).");
        return self::SUCCESS;
    }
}

