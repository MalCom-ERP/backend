<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mise à niveau de la table fournisseurs (legacy 2024 -> schéma Module 3 complet)
        if (Schema::hasTable('fournisseurs')) {
            if (DB::getDriverName() === 'mysql') {
                // Supprimer les anciens index uniques sur email / telephone pour éviter les blocages multi-boutiques
                $indexes = DB::select("SHOW INDEX FROM fournisseurs WHERE Non_unique = 0 AND Column_name IN ('email', 'telephone') AND Key_name != 'PRIMARY'");
                $dropped = [];
                foreach ($indexes as $idx) {
                    if (!in_array($idx->Key_name, $dropped, true)) {
                        DB::statement("ALTER TABLE fournisseurs DROP INDEX `{$idx->Key_name}`");
                        $dropped[] = $idx->Key_name;
                    }
                }

                if (Schema::hasColumn('fournisseurs', 'email')) {
                    DB::statement('ALTER TABLE fournisseurs MODIFY COLUMN email VARCHAR(255) NULL');
                }
                if (Schema::hasColumn('fournisseurs', 'telephone')) {
                    DB::statement('ALTER TABLE fournisseurs MODIFY COLUMN telephone VARCHAR(255) NULL');
                }
                if (Schema::hasColumn('fournisseurs', 'adresse')) {
                    DB::statement('ALTER TABLE fournisseurs MODIFY COLUMN adresse VARCHAR(255) NULL');
                }
                if (Schema::hasColumn('fournisseurs', 'ville')) {
                    DB::statement('ALTER TABLE fournisseurs MODIFY COLUMN ville VARCHAR(255) NULL');
                }
                if (Schema::hasColumn('fournisseurs', 'pays')) {
                    DB::statement('ALTER TABLE fournisseurs MODIFY COLUMN pays VARCHAR(255) NULL');
                }
            }

            Schema::table('fournisseurs', function (Blueprint $table) {
                if (!Schema::hasColumn('fournisseurs', 'boutique_id')) {
                    $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
                    $table->index('boutique_id', 'fournisseurs_boutique_id_idx');
                }
                if (!Schema::hasColumn('fournisseurs', 'entreprise')) {
                    $table->string('entreprise')->nullable()->after('nom');
                }
                if (!Schema::hasColumn('fournisseurs', 'pays_ville')) {
                    $table->string('pays_ville')->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('fournisseurs', 'type_fournisseur')) {
                    $table->string('type_fournisseur', 50)->default('local')->after('pays_ville');
                }
                if (!Schema::hasColumn('fournisseurs', 'nif')) {
                    $table->string('nif', 100)->nullable()->after('type_fournisseur');
                }
                if (!Schema::hasColumn('fournisseurs', 'rccm')) {
                    $table->string('rccm', 100)->nullable()->after('nif');
                }
                if (!Schema::hasColumn('fournisseurs', 'delai_paiement_jours')) {
                    $table->integer('delai_paiement_jours')->default(0)->after('rccm');
                }
                if (!Schema::hasColumn('fournisseurs', 'notes')) {
                    $table->text('notes')->nullable()->after('delai_paiement_jours');
                }
                if (!Schema::hasColumn('fournisseurs', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('notes');
                }
                if (!Schema::hasColumn('fournisseurs', 'deleted_at')) {
                    $table->softDeletes();
                }
            });

            // Rattacher les fournisseurs existants sans boutique_id et remplir pays_ville si présent
            $firstBoutiqueId = DB::table('boutiques')->orderBy('id')->value('id');
            if ($firstBoutiqueId) {
                DB::table('fournisseurs')
                    ->whereNull('boutique_id')
                    ->update(['boutique_id' => $firstBoutiqueId]);
            }

            if (Schema::hasColumn('fournisseurs', 'ville') && Schema::hasColumn('fournisseurs', 'pays')) {
                DB::table('fournisseurs')
                    ->whereNull('pays_ville')
                    ->where(function ($q) {
                        $q->whereNotNull('ville')->orWhereNotNull('pays');
                    })
                    ->orderBy('id')
                    ->chunkById(100, function ($rows) {
                        foreach ($rows as $row) {
                            $parts = array_filter([$row->ville ?? null, $row->pays ?? null]);
                            if (!empty($parts)) {
                                DB::table('fournisseurs')
                                    ->where('id', $row->id)
                                    ->update(['pays_ville' => implode(', ', $parts)]);
                            }
                        }
                    });
            }
        }

        // 2. Passage de boutiques.logo en LONGTEXT NULL + récupération/nettoyage des logos base64 tronqués
        if (Schema::hasTable('boutiques')) {
            if (Schema::hasColumn('boutiques', 'logo')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE boutiques MODIFY COLUMN logo LONGTEXT NULL');
                }
            } else {
                Schema::table('boutiques', function (Blueprint $table) {
                    $table->longText('logo')->nullable()->after('email');
                });
            }

            // Récupérer les logos complets éventuellement journalisés avant troncature MySQL
            $logPath = storage_path('logs/laravel.log');
            if (is_file($logPath) && is_readable($logPath)) {
                $handle = @fopen($logPath, 'r');
                if ($handle !== false) {
                    $recovered = [];
                    while (($line = fgets($handle)) !== false) {
                        $markerPos = strpos($line, 'Creating boutique with fields:');
                        if ($markerPos === false) {
                            continue;
                        }
                        $jsonStart = strpos($line, '{', $markerPos);
                        if ($jsonStart === false) {
                            continue;
                        }
                        $payload = json_decode(trim(substr($line, $jsonStart)), true);
                        if (
                            is_array($payload)
                            && !empty($payload['nom'])
                            && !empty($payload['logo'])
                            && is_string($payload['logo'])
                            && str_starts_with($payload['logo'], 'data:image/')
                            && strlen($payload['logo']) > 260
                        ) {
                            $recovered[$payload['nom']] = $payload['logo'];
                        }
                    }
                    fclose($handle);

                    foreach ($recovered as $nom => $fullLogo) {
                        $boutiques = DB::table('boutiques')->where('nom', $nom)->get(['id', 'logo']);
                        foreach ($boutiques as $b) {
                            $current = (string) ($b->logo ?? '');
                            if (
                                $current === ''
                                || strlen($current) <= 260
                                || str_starts_with($fullLogo, $current)
                            ) {
                                DB::table('boutiques')->where('id', $b->id)->update(['logo' => $fullLogo]);
                            }
                        }
                    }
                }
            }

            // Nettoyer tout reliquat de data-URI base64 tronquée à 255 caractères (illisible)
            DB::table('boutiques')
                ->where('logo', 'like', 'data:image/%')
                ->whereRaw('CHAR_LENGTH(logo) <= 260')
                ->update(['logo' => null]);
        }

        // 3. Module 5 : Traçabilité Métier (Péremption & Lots, IMEI / Série & Garantie, Emballages & Consignes)
        if (Schema::hasTable('produits')) {
            Schema::table('produits', function (Blueprint $table) {
                if (!Schema::hasColumn('produits', 'numero_lot')) {
                    $table->string('numero_lot', 100)->nullable();
                }
                if (!Schema::hasColumn('produits', 'date_peremption')) {
                    $table->date('date_peremption')->nullable();
                }
                if (!Schema::hasColumn('produits', 'alerte_peremption_jours')) {
                    $table->integer('alerte_peremption_jours')->default(30);
                }
                if (!Schema::hasColumn('produits', 'exige_imei_serie')) {
                    $table->boolean('exige_imei_serie')->default(false);
                }
                if (!Schema::hasColumn('produits', 'duree_garantie_mois')) {
                    $table->integer('duree_garantie_mois')->default(0);
                }
                if (!Schema::hasColumn('produits', 'est_consignable')) {
                    $table->boolean('est_consignable')->default(false);
                }
                if (!Schema::hasColumn('produits', 'type_emballage')) {
                    $table->string('type_emballage', 100)->nullable();
                }
                if (!Schema::hasColumn('produits', 'montant_consigne_unitaire')) {
                    $table->decimal('montant_consigne_unitaire', 15, 2)->default(0);
                }
            });
        }

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                if (!Schema::hasColumn('stocks', 'numero_lot')) {
                    $table->string('numero_lot', 100)->nullable();
                }
                if (!Schema::hasColumn('stocks', 'date_peremption')) {
                    $table->date('date_peremption')->nullable();
                }
            });
        }

        if (Schema::hasTable('detail_ventes')) {
            Schema::table('detail_ventes', function (Blueprint $table) {
                if (!Schema::hasColumn('detail_ventes', 'numero_lot')) {
                    $table->string('numero_lot', 100)->nullable();
                }
                if (!Schema::hasColumn('detail_ventes', 'date_peremption')) {
                    $table->date('date_peremption')->nullable();
                }
                if (!Schema::hasColumn('detail_ventes', 'numeros_serie')) {
                    $table->json('numeros_serie')->nullable();
                }
                if (!Schema::hasColumn('detail_ventes', 'garantie_mois')) {
                    $table->integer('garantie_mois')->default(0);
                }
                if (!Schema::hasColumn('detail_ventes', 'quantite_consigne')) {
                    $table->integer('quantite_consigne')->default(0);
                }
                if (!Schema::hasColumn('detail_ventes', 'montant_consigne')) {
                    $table->decimal('montant_consigne', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('detail_ventes', 'statut_consigne')) {
                    $table->string('statut_consigne', 30)->nullable();
                }
            });
        }

        if (Schema::hasTable('ventes')) {
            Schema::table('ventes', function (Blueprint $table) {
                if (!Schema::hasColumn('ventes', 'montant_consigne_total')) {
                    $table->decimal('montant_consigne_total', 15, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ventes') && Schema::hasColumn('ventes', 'montant_consigne_total')) {
            Schema::table('ventes', function (Blueprint $table) {
                $table->dropColumn('montant_consigne_total');
            });
        }

        if (Schema::hasTable('detail_ventes')) {
            Schema::table('detail_ventes', function (Blueprint $table) {
                foreach ([
                    'numero_lot',
                    'date_peremption',
                    'numeros_serie',
                    'garantie_mois',
                    'quantite_consigne',
                    'montant_consigne',
                    'statut_consigne',
                ] as $col) {
                    if (Schema::hasColumn('detail_ventes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                foreach (['numero_lot', 'date_peremption'] as $col) {
                    if (Schema::hasColumn('stocks', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('produits')) {
            Schema::table('produits', function (Blueprint $table) {
                foreach ([
                    'numero_lot',
                    'date_peremption',
                    'alerte_peremption_jours',
                    'exige_imei_serie',
                    'duree_garantie_mois',
                    'est_consignable',
                    'type_emballage',
                    'montant_consigne_unitaire',
                ] as $col) {
                    if (Schema::hasColumn('produits', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
