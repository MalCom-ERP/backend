<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fournisseurs')) {
            Schema::create('fournisseurs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
                $table->string('nom');
                $table->string('entreprise')->nullable();
                $table->string('telephone', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('adresse')->nullable();
                $table->string('pays_ville', 120)->nullable()->default('Bamako, Mali');
                $table->string('type_fournisseur', 40)->default('grossiste_local');
                $table->string('nif', 80)->nullable();
                $table->string('rccm', 80)->nullable();
                $table->integer('delai_paiement_jours')->default(30);
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['boutique_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('commandes_achats')) {
            Schema::create('commandes_achats', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 60)->unique();
                $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
                $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('statut', 30)->default('recu'); // en_commande, recu, annule
                $table->string('type_paiement', 30)->default('comptant'); // comptant, credit, mixte
                $table->string('moyen_paiement', 40)->default('especes'); // especes, wave, orange_money, moov_money, virement, cheque, credit
                $table->decimal('sous_total_ht', 15, 2)->default(0);
                $table->decimal('frais_transport', 15, 2)->default(0);
                $table->decimal('frais_douane', 15, 2)->default(0);
                $table->decimal('frais_manutention', 15, 2)->default(0);
                $table->decimal('remise', 15, 2)->default(0);
                $table->decimal('montant_total', 15, 2)->default(0);
                $table->decimal('montant_paye', 15, 2)->default(0);
                $table->decimal('montant_restant', 15, 2)->default(0);
                $table->date('date_commande');
                $table->date('date_echeance')->nullable();
                $table->timestamp('date_reception')->nullable();
                $table->string('numero_facture_fournisseur', 100)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['boutique_id', 'statut']);
                $table->index(['fournisseur_id', 'statut']);
            });
        }

        if (!Schema::hasTable('commande_achat_details')) {
            Schema::create('commande_achat_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commande_achat_id')->constrained('commandes_achats')->cascadeOnDelete();
                $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
                $table->string('unite_achat', 60)->nullable();
                $table->integer('quantite_conditionnement')->default(1);
                $table->integer('nombre_colis')->nullable();
                $table->integer('quantite_commandee')->default(0);
                $table->integer('quantite_recue')->default(0);
                $table->decimal('prix_achat_unitaire', 15, 2)->default(0);
                $table->decimal('frais_approche_unitaire', 15, 2)->default(0);
                $table->decimal('cout_revient_unitaire', 15, 2)->default(0);
                $table->decimal('montant_ligne', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('paiements_fournisseurs')) {
            Schema::create('paiements_fournisseurs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commande_achat_id')->constrained('commandes_achats')->cascadeOnDelete();
                $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();
                $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('montant', 15, 2);
                $table->string('moyen_paiement', 40)->default('especes');
                $table->string('reference_paiement', 120)->nullable();
                $table->date('date_paiement');
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->index(['boutique_id', 'date_paiement']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_fournisseurs');
        Schema::dropIfExists('commande_achat_details');
        Schema::dropIfExists('commandes_achats');
        Schema::dropIfExists('fournisseurs');
    }
};
