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
        Schema::create('licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained('boutiques')->onDelete('cascade');
            $table->string('cle_licence')->unique()->index();
            $table->integer('duree_jours')->default(30); // Durée en jours (ex: 30, 90, 365, 99999 pour illimité)
            $table->enum('statut', ['inutilisee', 'active', 'expiree', 'revoquee'])->default('inutilisee');
            $table->timestamp('date_activation')->nullable();
            $table->timestamp('date_expiration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable(); // Ex: Motif de paiement, contact client, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
