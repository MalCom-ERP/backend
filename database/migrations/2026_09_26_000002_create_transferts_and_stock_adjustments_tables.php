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
        // 1. Table transferts (Transferts inter-boutiques)
        if (!Schema::hasTable('transferts')) {
            Schema::create('transferts', function (Blueprint $table) {
                $table->id();
                $table->string('numero_transfert', 50)->unique();
                $table->foreignId('boutique_source_id')->constrained('boutiques')->onDelete('cascade');
                $table->foreignId('boutique_dest_id')->constrained('boutiques')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('recepteur_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('statut', 30)->default('valide'); // 'valide', 'en_transit', 'recu', 'annule'
                $table->dateTime('date_transfert');
                $table->dateTime('date_reception')->nullable();
                $table->text('note')->nullable();
                $table->integer('total_articles')->default(0);
                $table->integer('total_quantite')->default(0);
                $table->decimal('valeur_totale', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // 2. Table transfert_details
        if (!Schema::hasTable('transfert_details')) {
            Schema::create('transfert_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transfert_id')->constrained('transferts')->onDelete('cascade');
                $table->foreignId('produit_id')->constrained('produits')->onDelete('cascade');
                $table->integer('quantite');
                $table->decimal('prix_achat', 15, 2)->default(0);
                $table->decimal('prix_vente', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // 3. Assouplir le type d'inventaires pour accepter les transferts et ajustements
        try {
            DB::statement("ALTER TABLE inventaires MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'ajout'");
        } catch (\Throwable $e) {
            // Fallback si la colonne est déjà varchar
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfert_details');
        Schema::dropIfExists('transferts');
    }
};
