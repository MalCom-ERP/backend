<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert enum to VARCHAR to allow new payment modes smoothly
        try {
            DB::statement("ALTER TABLE ventes MODIFY type_paiement VARCHAR(50) DEFAULT 'contant'");
        } catch (\Throwable $e) {
            // fallback if already modified or sqlite
        }

        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'moyen_paiement')) {
                $table->string('moyen_paiement', 50)->default('especes')->after('type_paiement');
            }
            if (!Schema::hasColumn('ventes', 'montant_recu')) {
                $table->decimal('montant_recu', 15, 2)->default(0)->after('montant_avance');
            }
            if (!Schema::hasColumn('ventes', 'monnaie_rendue')) {
                $table->decimal('monnaie_rendue', 15, 2)->default(0)->after('montant_recu');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            if (Schema::hasColumn('ventes', 'monnaie_rendue')) {
                $table->dropColumn('monnaie_rendue');
            }
            if (Schema::hasColumn('ventes', 'montant_recu')) {
                $table->dropColumn('montant_recu');
            }
            if (Schema::hasColumn('ventes', 'moyen_paiement')) {
                $table->dropColumn('moyen_paiement');
            }
        });
    }
};
