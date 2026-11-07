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
        Schema::table('annees', function (Blueprint $table) {
            // Supprimer la contrainte unique globale sur 'annee'
            $table->dropUnique('annees_annee_unique');

            // Ajouter une contrainte unique composite par boutique
            $table->unique(['annee', 'boutique_id'], 'annees_annee_boutique_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annees', function (Blueprint $table) {
            $table->dropUnique('annees_annee_boutique_unique');
            $table->unique('annee', 'annees_annee_unique');
        });
    }
};
