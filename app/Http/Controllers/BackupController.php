<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class BackupController extends Controller
{
    /**
     * Statistiques de la base de données pour le panneau d'administration.
     */
    public function stats(Request $request)
    {
        try {
            $dbName = config('database.connections.mysql.database');
            
            $tables = DB::select('SHOW TABLES');
            $tableCount = count($tables);

            // Calcul de la taille approximative de la base
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
     * Génère et télécharge un dump SQL complet en 1-clic (Axe 5).
     */
    public function downloadSql(Request $request)
    {
        try {
            // Augmenter la limite de mémoire et le temps d'exécution pour les gros dumps
            @ini_set('memory_limit', '512M');
            @set_time_limit(300);

            $dbName = config('database.connections.mysql.database');
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "backup_malcom_{$dbName}_{$timestamp}.sql";

            $headers = [
                'Content-Type' => 'application/sql; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function () use ($dbName, $timestamp) {
                $out = fopen('php://output', 'w');

                // En-tête SQL
                fwrite($out, "-- ========================================================\n");
                fwrite($out, "-- MALCOM ERP & POS - SAUVEGARDE DE LA BASE DE DONNÉES\n");
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

                    // Ignorer les tables temporaires ou de jobs si nécessaire, sinon tout sauvegarder
                    fwrite($out, "-- --------------------------------------------------------\n");
                    fwrite($out, "-- Structure de la table `{$tableName}`\n");
                    fwrite($out, "-- --------------------------------------------------------\n");
                    fwrite($out, "DROP TABLE IF EXISTS `{$tableName}`;\n");

                    $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                    if (!empty($createTable)) {
                        $createSql = $createTable[0]->{'Create Table'} ?? array_values((array)$createTable[0])[1] ?? '';
                        fwrite($out, $createSql . ";\n\n");
                    }

                    // Export des données par lots (offset / limit)
                    $count = DB::table($tableName)->count();
                    if ($count > 0) {
                        fwrite($out, "-- Données de la table `{$tableName}` ({$count} enregistrement(s))\n");

                        $offset = 0;
                        $batchSize = 200;

                        while (true) {
                            $rows = DB::table($tableName)->offset($offset)->limit($batchSize)->get();
                            if ($rows->isEmpty()) {
                                break;
                            }

                            $first = true;
                            $columns = [];

                            foreach ($rows as $row) {
                                $rowArr = (array)$row;

                                if ($first) {
                                    $columns = array_keys($rowArr);
                                    $quotedColumns = array_map(fn($col) => "`{$col}`", $columns);
                                    fwrite($out, "INSERT INTO `{$tableName}` (" . implode(', ', $quotedColumns) . ") VALUES\n");
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
                                        // Échappement des caractères spéciaux
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
                            $offset += $batchSize;
                        }
                    }

                    fwrite($out, "\n");
                }

                fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
                fwrite($out, "-- FIN DE SAUVEGARDE MALCOM\n");
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
}
