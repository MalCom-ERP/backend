<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enrichir la table daily_reports pour la gestion de session et clôture de caisse (Ticket Z)
        Schema::table('daily_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_reports', 'fond_de_caisse')) {
                $table->decimal('fond_de_caisse', 15, 2)->default(0)->after('date');
            }
            if (!Schema::hasColumn('daily_reports', 'total_especes_theorique')) {
                $table->decimal('total_especes_theorique', 15, 2)->default(0)->after('fond_de_caisse');
            }
            if (!Schema::hasColumn('daily_reports', 'total_especes_physique')) {
                $table->decimal('total_especes_physique', 15, 2)->default(0)->after('total_especes_theorique');
            }
            if (!Schema::hasColumn('daily_reports', 'ecart_caisse')) {
                $table->decimal('ecart_caisse', 15, 2)->default(0)->after('total_especes_physique');
            }
            if (!Schema::hasColumn('daily_reports', 'billetage')) {
                $table->json('billetage')->nullable()->after('ecart_caisse');
            }
            if (!Schema::hasColumn('daily_reports', 'total_mobile_money')) {
                $table->decimal('total_mobile_money', 15, 2)->default(0)->after('benefice_net');
            }
            if (!Schema::hasColumn('daily_reports', 'total_carte_bancaire')) {
                $table->decimal('total_carte_bancaire', 15, 2)->default(0)->after('total_mobile_money');
            }
            if (!Schema::hasColumn('daily_reports', 'total_credit')) {
                $table->decimal('total_credit', 15, 2)->default(0)->after('total_carte_bancaire');
            }
            if (!Schema::hasColumn('daily_reports', 'total_recouvrement')) {
                $table->decimal('total_recouvrement', 15, 2)->default(0)->after('total_credit');
            }
            if (!Schema::hasColumn('daily_reports', 'statut_cloture')) {
                $table->string('statut_cloture')->default('cloturee')->after('nombre_depenses');
            }
            if (!Schema::hasColumn('daily_reports', 'cloture_par_user_id')) {
                $table->foreignId('cloture_par_user_id')->nullable()->constrained('users')->nullOnDelete()->after('statut_cloture');
            }
            if (!Schema::hasColumn('daily_reports', 'notes_cloture')) {
                $table->text('notes_cloture')->nullable()->after('cloture_par_user_id');
            }
        });

        // Enrichir la table expenses pour le mode de décaissement et références
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'mode_paiement')) {
                $table->string('mode_paiement')->default('especes')->after('montant');
            }
            if (!Schema::hasColumn('expenses', 'reference_piece')) {
                $table->string('reference_piece')->nullable()->after('mode_paiement');
            }
            if (!Schema::hasColumn('expenses', 'beneficiaire')) {
                $table->string('beneficiaire')->nullable()->after('reference_piece');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn([
                'fond_de_caisse',
                'total_especes_theorique',
                'total_especes_physique',
                'ecart_caisse',
                'billetage',
                'total_mobile_money',
                'total_carte_bancaire',
                'total_credit',
                'total_recouvrement',
                'statut_cloture',
                'cloture_par_user_id',
                'notes_cloture'
            ]);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn([
                'mode_paiement',
                'reference_piece',
                'beneficiaire'
            ]);
        });
    }
};
