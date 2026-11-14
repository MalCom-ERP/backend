<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boutiques') && !Schema::hasColumn('boutiques', 'type_etablissement')) {
            Schema::table('boutiques', function (Blueprint $table) {
                $table->string('type_etablissement', 30)->default('boutique')->after('nature_id');
            });
        }

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                if (!Schema::hasColumn('stocks', 'seuil_alerte')) {
                    $table->integer('seuil_alerte')->default(5)->after('quantite');
                }
                if (!Schema::hasColumn('stocks', 'emplacement')) {
                    $table->string('emplacement', 120)->nullable()->after('seuil_alerte');
                }
            });
        }

        if (!Schema::hasTable('bons_enlevement')) {
            Schema::create('bons_enlevement', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 60)->unique();
                $table->foreignId('vente_id')->nullable()->constrained('ventes')->nullOnDelete();
                $table->foreignId('boutique_emettrice_id')->constrained('boutiques')->cascadeOnDelete();
                $table->foreignId('entrepot_id')->constrained('boutiques')->cascadeOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('valide_par_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('statut', 30)->default('en_attente'); // en_attente, partiel, livre, annule
                $table->boolean('stock_deja_deduit')->default(false);
                $table->string('recepteur_nom')->nullable();
                $table->string('recepteur_telephone', 40)->nullable();
                $table->string('chauffeur_immatriculation', 100)->nullable();
                $table->text('motif_ou_note')->nullable();
                $table->timestamp('date_emission')->nullable();
                $table->timestamp('date_enlevement')->nullable();
                $table->timestamps();

                $table->index(['boutique_emettrice_id', 'statut']);
                $table->index(['entrepot_id', 'statut']);
            });
        }

        if (!Schema::hasTable('bon_enlevement_details')) {
            Schema::create('bon_enlevement_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bon_enlevement_id')->constrained('bons_enlevement')->cascadeOnDelete();
                $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
                $table->integer('quantite_commandee')->default(0);
                $table->integer('quantite_livree')->default(0);
                $table->string('emplacement', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bon_enlevement_details');
        Schema::dropIfExists('bons_enlevement');

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                if (Schema::hasColumn('stocks', 'emplacement')) {
                    $table->dropColumn('emplacement');
                }
                if (Schema::hasColumn('stocks', 'seuil_alerte')) {
                    $table->dropColumn('seuil_alerte');
                }
            });
        }

        if (Schema::hasTable('boutiques') && Schema::hasColumn('boutiques', 'type_etablissement')) {
            Schema::table('boutiques', function (Blueprint $table) {
                $table->dropColumn('type_etablissement');
            });
        }
    }
};
