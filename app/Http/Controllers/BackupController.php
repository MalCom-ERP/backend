<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    /**
     * Récupère la liste des IDs de boutiques associées à un Compte Principal (ou propriétaire).
     */
    public function getBoutiqueIdsForAccount(User $user): array
    {
        if ($user->role === 'super_admin') {
            return Boutique::pluck('id')->map(fn($id) => (int)$id)->toArray();
        }

        // Boutiques dont ce compte est propriétaire
        $ownedIds = Boutique::where('user_id', $user->id)->pluck('id')->toArray();

        // Inclure aussi la boutique rattachée directement au compte et tout son groupe
        if ($user->boutique_id) {
            $b = Boutique::find($user->boutique_id);
            if ($b) {
                $ownedIds = array_merge($ownedIds, $b->getGroupBoutiqueIds());
            }
        }

        // Inclure toutes les filiales dont le parent appartient à ce propriétaire
        if (!empty($ownedIds)) {
            $childIds = Boutique::whereIn('parent_id', $ownedIds)->pluck('id')->toArray();
            $ownedIds = array_merge($ownedIds, $childIds);
        }

        return array_values(array_unique(array_map('intval', $ownedIds)));
    }

    /**
     * Statistiques de la base de données pour le panneau d'administration (filtré par Compte Principal).
     */
    public function stats(Request $request)
    {
        try {
            $user = Auth::user();
            $dbName = config('database.connections.mysql.database') ?: 'malcom_prod';

            if ($user && $user->role !== 'super_admin') {
                $boutiqueIds = $this->getBoutiqueIdsForAccount($user);
                $boutiquesNames = Boutique::whereIn('id', $boutiqueIds)->pluck('nom')->toArray();

                $totalRows = 0;
                $tablesCount = 18;
                if (!empty($boutiqueIds)) {
                    $totalRows += DB::table('boutiques')->whereIn('id', $boutiqueIds)->count();
                    $totalRows += DB::table('users')->whereIn('boutique_id', $boutiqueIds)->orWhere('id', $user->id)->count();
                    $totalRows += DB::table('categories')->whereIn('boutique_id', $boutiqueIds)->count();
                    $totalRows += DB::table('stocks')->whereIn('boutique_id', $boutiqueIds)->count();
                    $totalRows += DB::table('ventes')->whereIn('boutique_id', $boutiqueIds)->count();
                    $totalRows += DB::table('factures')->whereIn('boutique_id', $boutiqueIds)->count();
                    $totalRows += DB::table('inventaires')->whereIn('boutique_id', $boutiqueIds)->count();
                    $totalRows += DB::table('expenses')->whereIn('boutique_id', $boutiqueIds)->count();
                }

                $sizeMb = round(max(0.05, ($totalRows * 0.65) / 1024), 2);

                return response()->json([
                    'success' => true,
                    'database' => 'Compte : ' . $user->name . ' (' . count($boutiqueIds) . ' boutique(s))',
                    'boutiques' => $boutiquesNames,
                    'boutiques_count' => count($boutiqueIds),
                    'table_count' => $tablesCount,
                    'size_mb' => (float)$sizeMb,
                    'total_rows' => (int)$totalRows,
                    'generated_at' => Carbon::now()->toIso8601String()
                ]);
            }

            $tables = DB::select('SHOW TABLES');
            $tableCount = count($tables);

            $sizeQuery = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    SUM(table_rows) AS total_rows
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ", [$dbName]);

            $sizeMb = $sizeQuery[0]->size_mb ?? 0;
            $totalRows = $sizeQuery[0]->total_rows ?? 0;

            return response()->json([
                'success' => true,
                'database' => $dbName,
                'table_count' => $tableCount,
                'size_mb' => (float)$sizeMb,
                'total_rows' => (int)$totalRows,
                'generated_at' => Carbon::now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de récupérer les statistiques de la base : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère et télécharge un dump SQL en 1-clic depuis l'application Angular (isolé par Compte Principal).
     */
    public function downloadSql(Request $request)
    {
        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(300);

            $user = Auth::user();
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

            // Si c'est un administrateur de boutique (Compte Principal), dumper uniquement toutes les boutiques de son compte !
            if ($user && $user->role !== 'super_admin') {
                $boutiqueIds = $this->getBoutiqueIdsForAccount($user);
                $slug = Str::slug($user->name ?: 'compte-' . $user->id);
                $filename = "backup_compte_{$slug}_{$timestamp}.sql";

                $headers = [
                    'Content-Type' => 'application/sql; charset=utf-8',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0'
                ];

                $callback = function () use ($user, $boutiqueIds) {
                    $out = fopen('php://output', 'w');
                    $this->writeScopedBoutiquesSqlDump(
                        $out,
                        $boutiqueIds,
                        "COMPTE PRINCIPAL : {$user->name} ({$user->email})",
                        $user->id
                    );
                    fclose($out);
                };

                return Response::stream($callback, 200, $headers);
            }

            // Sinon (SuperAdmin), dump global complet
            $dbName = config('database.connections.mysql.database') ?: 'malcom_prod';
            $filename = "backup_global_malcom_{$dbName}_{$timestamp}.sql";

            $headers = [
                'Content-Type' => 'application/sql; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function () {
                $out = fopen('php://output', 'w');
                $this->writeFullDatabaseSqlDump($out);
                fclose($out);
            };

            return Response::stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la sauvegarde : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Écrit un dump SQL complet (toutes les tables de la base) dans le flux fourni ($out).
     */
    public function writeFullDatabaseSqlDump($out): void
    {
        $dbName = config('database.connections.mysql.database') ?: 'malcom_prod';

        fwrite($out, "-- ========================================================\n");
        fwrite($out, "-- MALCOM ERP & POS - SAUVEGARDE INTÉGRALE (SUPERADMIN)\n");
        fwrite($out, "-- Base de données : {$dbName}\n");
        fwrite($out, "-- Date de sauvegarde : " . Carbon::now()->format('d/m/Y H:i:s') . "\n");
        fwrite($out, "-- ========================================================\n\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($out, "SET NAMES utf8mb4;\n\n");

        $tables = DB::select('SHOW TABLES');
        $tableProp = "Tables_in_{$dbName}";

        foreach ($tables as $t) {
            $tableName = $t->$tableProp ?? array_values((array)$t)[0];

            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "-- Structure de la table `{$tableName}`\n");
            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "DROP TABLE IF EXISTS `{$tableName}`;\n");

            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($createTable)) {
                $createSql = $createTable[0]->{'Create Table'} ?? array_values((array)$createTable[0])[1] ?? '';
                fwrite($out, $createSql . ";\n\n");
            }

            $count = DB::table($tableName)->count();
            if ($count > 0) {
                fwrite($out, "-- Données de la table `{$tableName}` ({$count} enregistrement(s))\n");
                $offset = 0;
                $batchSize = 250;

                while (true) {
                    $rows = DB::table($tableName)->offset($offset)->limit($batchSize)->get();
                    if ($rows->isEmpty()) {
                        break;
                    }
                    $this->writeInsertBatch($out, $tableName, $rows);
                    $offset += $batchSize;
                }
            }
            fwrite($out, "\n");
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($out, "-- FIN DE SAUVEGARDE INTÉGRALE MALCOM\n");
    }

    /**
     * Écrit un dump SQL isolé pour un Compte Principal (toutes ses boutiques associées)
     * ou pour une boutique spécifique dans le flux fourni ($out).
     */
    public function writeScopedBoutiquesSqlDump($out, array $boutiqueIds, string $scopeTitle, ?int $ownerUserId = null): void
    {
        $boutiques = Boutique::whereIn('id', $boutiqueIds)->get();
        $boutiqueNames = $boutiques->pluck('nom')->implode(', ');

        fwrite($out, "-- ======================================================================\n");
        fwrite($out, "-- MALCOM ERP & POS - DUMP SQL CIBLÉ PAR COMPTE PRINCIPAL / BOUTIQUE(S)\n");
        fwrite($out, "-- Périmètre : {$scopeTitle}\n");
        fwrite($out, "-- Boutiques incluses (" . count($boutiqueIds) . ") : " . ($boutiqueNames ?: 'Aucune') . "\n");
        fwrite($out, "-- IDs Boutiques : [" . implode(', ', $boutiqueIds) . "]\n");
        fwrite($out, "-- Date de génération : " . Carbon::now()->format('d/m/Y H:i:s') . "\n");
        fwrite($out, "-- ======================================================================\n\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($out, "SET NAMES utf8mb4;\n\n");

        if (empty($boutiqueIds)) {
            fwrite($out, "-- Aucune boutique associée à ce compte.\n");
            fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
            return;
        }

        // Collecte des IDs liés pour les tables relationnelles
        $natureIds = $boutiques->pluck('nature_id')->filter()->unique()->values()->toArray();
        $ownerIds = $boutiques->pluck('user_id')->filter()->unique()->values()->toArray();
        if ($ownerUserId && !in_array($ownerUserId, $ownerIds)) {
            $ownerIds[] = $ownerUserId;
        }

        $produitIdsFromStock = Schema::hasTable('stocks')
            ? DB::table('stocks')->whereIn('boutique_id', $boutiqueIds)->pluck('produit_id')->unique()->toArray()
            : [];
        $produitIdsFromInv = Schema::hasTable('inventaires')
            ? DB::table('inventaires')->whereIn('boutique_id', $boutiqueIds)->whereNotNull('produit_id')->pluck('produit_id')->unique()->toArray()
            : [];
        $venteIds = Schema::hasTable('ventes')
            ? DB::table('ventes')->whereIn('boutique_id', $boutiqueIds)->pluck('id')->toArray()
            : [];
        $produitIdsFromVentes = (!empty($venteIds) && Schema::hasTable('detail_ventes'))
            ? DB::table('detail_ventes')->whereIn('vente_id', $venteIds)->pluck('produit_id')->unique()->toArray()
            : [];
        $produitIds = array_values(array_unique(array_merge($produitIdsFromStock, $produitIdsFromInv, $produitIdsFromVentes)));

        $factureIds = Schema::hasTable('factures')
            ? DB::table('factures')->whereIn('boutique_id', $boutiqueIds)->pluck('id')->toArray()
            : [];

        $clientIdsFromVentes = Schema::hasTable('ventes')
            ? DB::table('ventes')->whereIn('boutique_id', $boutiqueIds)->whereNotNull('client_id')->pluck('client_id')->unique()->toArray()
            : [];
        $clientIdsFromFactures = Schema::hasTable('factures')
            ? DB::table('factures')->whereIn('boutique_id', $boutiqueIds)->whereNotNull('client_id')->pluck('client_id')->unique()->toArray()
            : [];
        $clientIds = array_values(array_unique(array_merge([1], $clientIdsFromVentes, $clientIdsFromFactures)));

        $transfertIds = Schema::hasTable('transferts')
            ? DB::table('transferts')
                ->whereIn('boutique_source_id', $boutiqueIds)
                ->orWhereIn('boutique_dest_id', $boutiqueIds)
                ->pluck('id')->toArray()
            : [];

        $ecritureIds = Schema::hasTable('ecritures_comptables')
            ? DB::table('ecritures_comptables')->whereIn('boutique_id', $boutiqueIds)->pluck('id')->toArray()
            : [];

        // Définition ordonnée des tables et de leurs requêtes filtrées pour ce compte / groupe de boutiques
        $scopedQueries = [
            'natures' => fn() => Schema::hasTable('natures')
                ? DB::table('natures')->whereIn('id', !empty($natureIds) ? $natureIds : [1])->get()
                : collect(),
            'boutiques' => fn() => Schema::hasTable('boutiques')
                ? DB::table('boutiques')->whereIn('id', $boutiqueIds)->get()
                : collect(),
            'users' => fn() => Schema::hasTable('users')
                ? DB::table('users')->where(function ($q) use ($boutiqueIds, $ownerIds) {
                    $q->whereIn('boutique_id', $boutiqueIds);
                    if (!empty($ownerIds)) {
                        $q->orWhereIn('id', $ownerIds);
                    }
                })->get()
                : collect(),
            'licences' => fn() => Schema::hasTable('licences')
                ? DB::table('licences')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'annees' => fn() => Schema::hasTable('annees')
                ? DB::table('annees')->whereIn('boutique_id', $boutiqueIds)->orWhereNull('boutique_id')->get()
                : collect(),
            'categories' => fn() => Schema::hasTable('categories')
                ? DB::table('categories')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'produits' => fn() => (Schema::hasTable('produits') && !empty($produitIds))
                ? DB::table('produits')->whereIn('id', $produitIds)->get()
                : collect(),
            'stocks' => fn() => Schema::hasTable('stocks')
                ? DB::table('stocks')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'clients' => fn() => (Schema::hasTable('clients') && !empty($clientIds))
                ? DB::table('clients')->whereIn('id', $clientIds)->get()
                : collect(),
            'ventes' => fn() => Schema::hasTable('ventes')
                ? DB::table('ventes')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'detail_ventes' => fn() => (Schema::hasTable('detail_ventes') && !empty($venteIds))
                ? DB::table('detail_ventes')->whereIn('vente_id', $venteIds)->get()
                : collect(),
            'factures' => fn() => Schema::hasTable('factures')
                ? DB::table('factures')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'facture_ventes' => fn() => (Schema::hasTable('facture_ventes') && (!empty($factureIds) || !empty($venteIds)))
                ? DB::table('facture_ventes')->whereIn('facture_id', $factureIds)->orWhereIn('vente_id', $venteIds)->get()
                : collect(),
            'paiements_credit' => fn() => Schema::hasTable('paiements_credit')
                ? DB::table('paiements_credit')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'inventaires' => fn() => Schema::hasTable('inventaires')
                ? DB::table('inventaires')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'expenses' => fn() => Schema::hasTable('expenses')
                ? DB::table('expenses')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'daily_reports' => fn() => Schema::hasTable('daily_reports')
                ? DB::table('daily_reports')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'transferts' => fn() => (Schema::hasTable('transferts') && !empty($transfertIds))
                ? DB::table('transferts')->whereIn('id', $transfertIds)->get()
                : collect(),
            'transfert_details' => fn() => (Schema::hasTable('transfert_details') && !empty($transfertIds))
                ? DB::table('transfert_details')->whereIn('transfert_id', $transfertIds)->get()
                : collect(),
            'journaux_comptables' => fn() => Schema::hasTable('journaux_comptables')
                ? DB::table('journaux_comptables')->whereIn('boutique_id', $boutiqueIds)->orWhereNull('boutique_id')->get()
                : collect(),
            'comptes_comptables' => fn() => Schema::hasTable('comptes_comptables')
                ? DB::table('comptes_comptables')->whereIn('boutique_id', $boutiqueIds)->orWhereNull('boutique_id')->get()
                : collect(),
            'ecritures_comptables' => fn() => Schema::hasTable('ecritures_comptables')
                ? DB::table('ecritures_comptables')->whereIn('boutique_id', $boutiqueIds)->get()
                : collect(),
            'lignes_ecritures' => fn() => (Schema::hasTable('lignes_ecritures') && !empty($ecritureIds))
                ? DB::table('lignes_ecritures')->whereIn('ecriture_id', $ecritureIds)->get()
                : collect(),
        ];

        foreach ($scopedQueries as $tableName => $fetcher) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "-- Table `{$tableName}` (Structure + Données filtrées)\n");
            fwrite($out, "-- --------------------------------------------------------\n");

            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($createTable)) {
                $createSql = $createTable[0]->{'Create Table'} ?? array_values((array)$createTable[0])[1] ?? '';
                $createIfNotExists = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $createSql);
                fwrite($out, $createIfNotExists . ";\n\n");
            }

            $rows = $fetcher();
            if ($rows && $rows->isNotEmpty()) {
                fwrite($out, "-- Données pour `{$tableName}` (" . $rows->count() . " enregistrement(s))\n");
                foreach ($rows->chunk(200) as $chunk) {
                    $this->writeInsertBatch($out, $tableName, $chunk, true);
                }
            } else {
                fwrite($out, "-- Aucune donnée pour `{$tableName}` sur ce périmètre\n\n");
            }
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($out, "-- FIN DU DUMP SQL CIBLÉ MALCOM\n");
    }

    /**
     * Écrit un lot d'instructions INSERT ou REPLACE INTO pour une table.
     */
    private function writeInsertBatch($out, string $tableName, $rows, bool $useReplace = false): void
    {
        $first = true;
        $columns = [];
        $verb = $useReplace ? 'REPLACE INTO' : 'INSERT INTO';

        foreach ($rows as $row) {
            $rowArr = (array)$row;

            if ($first) {
                $columns = array_keys($rowArr);
                $quotedColumns = array_map(fn($col) => "`{$col}`", $columns);
                fwrite($out, "{$verb} `{$tableName}` (" . implode(', ', $quotedColumns) . ") VALUES\n");
                $first = false;
            } else {
                fwrite($out, ",\n");
            }

            $values = [];
            foreach ($columns as $col) {
                $val = $rowArr[$col] ?? null;
                if (is_null($val)) {
                    $values[] = 'NULL';
                } elseif (is_numeric($val) && !is_string($val)) {
                    $values[] = $val;
                } else {
                    $escaped = str_replace(
                        ['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                        ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
                        (string)$val
                    );
                    $values[] = "'{$escaped}'";
                }
            }

            fwrite($out, "(" . implode(', ', $values) . ")");
        }

        fwrite($out, ";\n\n");
    }
}
