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
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'points_fidelite')) {
                $table->integer('points_fidelite')->default(0)->after('adresse');
            }
            if (!Schema::hasColumn('clients', 'plafond_credit')) {
                $table->decimal('plafond_credit', 15, 2)->default(0)->after('points_fidelite');
            }
            if (!Schema::hasColumn('clients', 'nif')) {
                $table->string('nif', 50)->nullable()->after('plafond_credit');
            }
            if (!Schema::hasColumn('clients', 'rccm')) {
                $table->string('rccm', 50)->nullable()->after('nif');
            }
            if (!Schema::hasColumn('clients', 'notes')) {
                $table->text('notes')->nullable()->after('rccm');
            }
        });

        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'date_echeance')) {
                $table->date('date_echeance')->nullable()->after('date_vente');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['points_fidelite', 'plafond_credit', 'nif', 'rccm', 'notes']);
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['date_echeance']);
        });
    }
};
