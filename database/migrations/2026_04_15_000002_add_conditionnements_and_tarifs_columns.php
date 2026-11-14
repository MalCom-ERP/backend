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
        Schema::table('produits', function (Blueprint $table) {
            if (!Schema::hasColumn('produits', 'unite_base')) {
                $table->string('unite_base', 40)->nullable()->default('Unité')->after('nom');
            }
            if (!Schema::hasColumn('produits', 'unite_conditionnement')) {
                $table->string('unite_conditionnement', 40)->nullable()->after('unite_base');
            }
            if (!Schema::hasColumn('produits', 'quantite_conditionnement')) {
                $table->integer('quantite_conditionnement')->default(1)->after('unite_conditionnement');
            }
            if (!Schema::hasColumn('produits', 'prix_conditionnement')) {
                $table->decimal('prix_conditionnement', 15, 2)->nullable()->after('quantite_conditionnement');
            }
            if (!Schema::hasColumn('produits', 'prix_demi_gros')) {
                $table->decimal('prix_demi_gros', 15, 2)->nullable()->after('prix_detail');
            }
            if (!Schema::hasColumn('produits', 'seuil_demi_gros')) {
                $table->integer('seuil_demi_gros')->nullable()->after('prix_demi_gros');
            }
            if (!Schema::hasColumn('produits', 'seuil_gros')) {
                $table->integer('seuil_gros')->nullable()->after('prix_master');
            }
        });

        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'prix_demi_gros')) {
                $table->decimal('prix_demi_gros', 15, 2)->nullable()->after('prix_vente');
            }
            if (!Schema::hasColumn('stocks', 'prix_gros')) {
                $table->decimal('prix_gros', 15, 2)->nullable()->after('prix_demi_gros');
            }
        });

        Schema::table('detail_ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_ventes', 'unite_vente')) {
                $table->string('unite_vente', 50)->nullable()->after('quantite');
            }
            if (!Schema::hasColumn('detail_ventes', 'quantite_conditionnement')) {
                $table->integer('quantite_conditionnement')->default(1)->after('unite_vente');
            }
            if (!Schema::hasColumn('detail_ventes', 'mode_tarif')) {
                $table->string('mode_tarif', 20)->nullable()->default('detail')->after('quantite_conditionnement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_ventes', function (Blueprint $table) {
            foreach (['unite_vente', 'quantite_conditionnement', 'mode_tarif'] as $col) {
                if (Schema::hasColumn('detail_ventes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('stocks', function (Blueprint $table) {
            foreach (['prix_demi_gros', 'prix_gros'] as $col) {
                if (Schema::hasColumn('stocks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('produits', function (Blueprint $table) {
            foreach ([
                'unite_base',
                'unite_conditionnement',
                'quantite_conditionnement',
                'prix_conditionnement',
                'prix_demi_gros',
                'seuil_demi_gros',
                'seuil_gros',
            ] as $col) {
                if (Schema::hasColumn('produits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
