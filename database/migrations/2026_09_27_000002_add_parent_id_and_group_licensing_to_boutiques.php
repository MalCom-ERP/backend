<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Boutique;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('boutiques', 'parent_id')) {
            Schema::table('boutiques', function (Blueprint $table) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('boutiques')
                    ->nullOnDelete();
            });
        }

        // Synchroniser les filiales existantes :
        // Pour chaque administrateur, sa première boutique (user->boutique_id ou la plus ancienne)
        // est la boutique principale du groupe. Toutes ses autres boutiques sont rattachées comme filiales.
        $admins = User::whereIn('role', ['admin', 'admin1'])->get();
        foreach ($admins as $admin) {
            $primaryBoutiqueId = $admin->boutique_id;
            if (!$primaryBoutiqueId) {
                $first = Boutique::where('user_id', $admin->id)->orderBy('id')->first();
                $primaryBoutiqueId = $first?->id;
            }

            if ($primaryBoutiqueId) {
                $primaryBoutique = Boutique::find($primaryBoutiqueId);

                // Mettre à jour les filiales de cet admin
                $filiales = Boutique::where('user_id', $admin->id)
                    ->where('id', '!=', $primaryBoutiqueId)
                    ->get();

                foreach ($filiales as $filiale) {
                    $filiale->parent_id = $primaryBoutiqueId;
                    if ($primaryBoutique) {
                        $filiale->date_expiration_licence = $primaryBoutique->date_expiration_licence;
                        $filiale->is_active = $primaryBoutique->is_active;
                    }
                    $filiale->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('boutiques', 'parent_id')) {
            Schema::table('boutiques', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
    }
};
