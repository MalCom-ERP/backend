<?php

use App\Models\CompteComptable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventes')) {
            Schema::table('ventes', function (Blueprint $table) {
                if (!Schema::hasColumn('ventes', 'details_paiement')) {
                    $table->json('details_paiement')->nullable()->after('moyen_paiement');
                }
                if (!Schema::hasColumn('ventes', 'reference_paiement')) {
                    $table->string('reference_paiement', 120)->nullable()->after('details_paiement');
                }
            });
        }

        if (Schema::hasTable('paiements_credit')) {
            Schema::table('paiements_credit', function (Blueprint $table) {
                if (!Schema::hasColumn('paiements_credit', 'reference_paiement')) {
                    $table->string('reference_paiement', 120)->nullable()->after('mode_paiement');
                }
            });
        }

        if (Schema::hasTable('comptes_comptables')) {
            CompteComptable::updateOrCreate(
                ['numero' => '558', 'boutique_id' => null],
                [
                    'libelle' => 'Instruments de monnaie électronique (Orange Money, Wave, Moov)',
                    'classe' => 5,
                    'type' => 'actif',
                    'sens_normal' => 'debit',
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ventes')) {
            Schema::table('ventes', function (Blueprint $table) {
                if (Schema::hasColumn('ventes', 'reference_paiement')) {
                    $table->dropColumn('reference_paiement');
                }
                if (Schema::hasColumn('ventes', 'details_paiement')) {
                    $table->dropColumn('details_paiement');
                }
            });
        }

        if (Schema::hasTable('paiements_credit')) {
            Schema::table('paiements_credit', function (Blueprint $table) {
                if (Schema::hasColumn('paiements_credit', 'reference_paiement')) {
                    $table->dropColumn('reference_paiement');
                }
            });
        }
    }
};
