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
        // 1. Table des comptes comptables (Plan comptable SYSCOHADA)
        Schema::create('comptes_comptables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->nullable()->constrained('boutiques')->nullOnDelete();
            $table->string('numero', 20)->index();
            $table->string('libelle', 255);
            $table->unsignedTinyInteger('classe'); // 1 à 8
            $table->enum('type', ['actif', 'passif', 'charge', 'produit']);
            $table->enum('sens_normal', ['debit', 'credit'])->default('debit');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['boutique_id', 'numero'], 'uniq_boutique_compte_numero');
        });

        // 2. Table des journaux comptables
        Schema::create('journaux_comptables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->nullable()->constrained('boutiques')->nullOnDelete();
            $table->string('code', 10); // VT, AC, CA, BQ, OD
            $table->string('libelle', 100);
            $table->timestamps();

            $table->unique(['boutique_id', 'code'], 'uniq_boutique_journal_code');
        });

        // 3. Table des pièces / écritures comptables
        Schema::create('ecritures_comptables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('journaux_comptables')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_ecriture')->index();
            $table->string('numero_piece', 50)->index();
            $table->string('libelle', 255);
            $table->string('source_type', 50)->nullable()->index(); // Vente, PaiementCredit, Expense, Manuel
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('statut', 20)->default('validee');
            $table->timestamps();
        });

        // 4. Lignes d'écritures (Partie double : Débit = Crédit)
        Schema::create('lignes_ecritures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecriture_id')->constrained('ecritures_comptables')->cascadeOnDelete();
            $table->foreignId('compte_id')->constrained('comptes_comptables')->cascadeOnDelete();
            $table->string('libelle', 255)->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_ecritures');
        Schema::dropIfExists('ecritures_comptables');
        Schema::dropIfExists('journaux_comptables');
        Schema::dropIfExists('comptes_comptables');
    }
};
