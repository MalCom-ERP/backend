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
        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'surplus')) {
                $table->decimal('surplus', 15, 2)->default(0)->after('remise');
            }
        });

        Schema::table('detail_ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_ventes', 'surplus')) {
                $table->decimal('surplus', 15, 2)->default(0)->after('remise');
            }
            if (!Schema::hasColumn('detail_ventes', 'surplus_unitaire')) {
                $table->decimal('surplus_unitaire', 15, 2)->default(0)->after('prix_unitaire');
            }
            if (!Schema::hasColumn('detail_ventes', 'prix_base')) {
                $table->decimal('prix_base', 15, 2)->nullable()->after('surplus_unitaire');
            }
        });

        Schema::table('inventaires', function (Blueprint $table) {
            if (!Schema::hasColumn('inventaires', 'surplus')) {
                $table->decimal('surplus', 15, 2)->default(0)->after('remise');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            if (Schema::hasColumn('ventes', 'surplus')) {
                $table->dropColumn('surplus');
            }
        });

        Schema::table('detail_ventes', function (Blueprint $table) {
            $cols = array_filter(['surplus', 'surplus_unitaire', 'prix_base'], fn($c) => Schema::hasColumn('detail_ventes', $c));
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('inventaires', function (Blueprint $table) {
            if (Schema::hasColumn('inventaires', 'surplus')) {
                $table->dropColumn('surplus');
            }
        });
    }
};
