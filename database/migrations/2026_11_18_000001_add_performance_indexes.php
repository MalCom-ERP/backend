<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private function addIndexSafely(string $table, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $col) {
            if (!Schema::hasColumn($table, $col)) {
                return;
            }
        }
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        } catch (\Throwable $e) {
            // Index déjà existant : ignorer silencieusement pour rester idempotent
        }
    }

    private function dropIndexSafely(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        } catch (\Throwable $e) {
            // Ignorer si absent
        }
    }

    public function up(): void
    {
        $this->addIndexSafely('stocks', ['boutique_id', 'produit_id'], 'idx_stocks_boutique_produit');
        $this->addIndexSafely('stocks', ['boutique_id', 'quantite'], 'idx_stocks_boutique_qte');

        $this->addIndexSafely('produits', ['categorie_id'], 'idx_produits_categorie');
        $this->addIndexSafely('produits', ['reference'], 'idx_produits_reference');
        $this->addIndexSafely('produits', ['deleted_at', 'id'], 'idx_produits_deleted_id');

        $this->addIndexSafely('categories', ['boutique_id', 'deleted_at'], 'idx_categories_boutique_deleted');

        $this->addIndexSafely('ventes', ['boutique_id', 'date_vente'], 'idx_ventes_boutique_date');
        $this->addIndexSafely('ventes', ['boutique_id', 'statut', 'date_vente'], 'idx_ventes_boutique_statut_date');
        $this->addIndexSafely('ventes', ['boutique_id', 'type_paiement', 'statut'], 'idx_ventes_boutique_type_statut');
        $this->addIndexSafely('ventes', ['client_id'], 'idx_ventes_client');
        $this->addIndexSafely('ventes', ['user_id'], 'idx_ventes_user');

        $this->addIndexSafely('detail_ventes', ['vente_id'], 'idx_dv_vente');
        $this->addIndexSafely('detail_ventes', ['produit_id'], 'idx_dv_produit');

        $this->addIndexSafely('inventaires', ['boutique_id', 'date'], 'idx_inv_boutique_date');
        $this->addIndexSafely('inventaires', ['boutique_id', 'type', 'created_at'], 'idx_inv_boutique_type_created');
        $this->addIndexSafely('inventaires', ['produit_id'], 'idx_inv_produit');

        $this->addIndexSafely('expenses', ['boutique_id', 'date'], 'idx_expenses_boutique_date');

        $this->addIndexSafely('clients', ['boutique_id', 'nom'], 'idx_clients_boutique_nom');
        $this->addIndexSafely('clients', ['telephone'], 'idx_clients_telephone');

        $this->addIndexSafely('paiements_credit', ['vente_id', 'date_paiement'], 'idx_pc_vente_date');

        $this->addIndexSafely('transferts', ['boutique_source_id', 'created_at'], 'idx_tr_source_created');
        $this->addIndexSafely('transferts', ['boutique_dest_id', 'created_at'], 'idx_tr_dest_created');
    }

    public function down(): void
    {
        $this->dropIndexSafely('stocks', 'idx_stocks_boutique_produit');
        $this->dropIndexSafely('stocks', 'idx_stocks_boutique_qte');
        $this->dropIndexSafely('produits', 'idx_produits_categorie');
        $this->dropIndexSafely('produits', 'idx_produits_reference');
        $this->dropIndexSafely('produits', 'idx_produits_deleted_id');
        $this->dropIndexSafely('categories', 'idx_categories_boutique_deleted');
        $this->dropIndexSafely('ventes', 'idx_ventes_boutique_date');
        $this->dropIndexSafely('ventes', 'idx_ventes_boutique_statut_date');
        $this->dropIndexSafely('ventes', 'idx_ventes_boutique_type_statut');
        $this->dropIndexSafely('ventes', 'idx_ventes_client');
        $this->dropIndexSafely('ventes', 'idx_ventes_user');
        $this->dropIndexSafely('detail_ventes', 'idx_dv_vente');
        $this->dropIndexSafely('detail_ventes', 'idx_dv_produit');
        $this->dropIndexSafely('inventaires', 'idx_inv_boutique_date');
        $this->dropIndexSafely('inventaires', 'idx_inv_boutique_type_created');
        $this->dropIndexSafely('inventaires', 'idx_inv_produit');
        $this->dropIndexSafely('expenses', 'idx_expenses_boutique_date');
        $this->dropIndexSafely('clients', 'idx_clients_boutique_nom');
        $this->dropIndexSafely('clients', 'idx_clients_telephone');
        $this->dropIndexSafely('paiements_credit', 'idx_pc_vente_date');
        $this->dropIndexSafely('transferts', 'idx_tr_source_created');
        $this->dropIndexSafely('transferts', 'idx_tr_dest_created');
    }
};
