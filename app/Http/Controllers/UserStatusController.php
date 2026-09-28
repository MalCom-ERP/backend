<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Boutique;

class UserStatusController extends Controller
{
    /**
     * Check the active status of the authenticated user
     */
    public function checkUserStatus(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié.',
                'is_active' => false,
                'boutique_active' => false
            ], 401);
        }

        $boutiqueActive = true;

        // Check if user has a boutique and if it's active
        if ($user->boutique_id) {
            $boutique = Boutique::find($user->boutique_id);
            if ($boutique) {
                $root = $boutique->getRootBoutique();
                $boutiqueActive = (bool) ($root->is_active && !$root->isLicenceExpired());
            } else {
                $boutiqueActive = false;
            }
        }

        return response()->json([
            'user_id' => $user->id,
            'is_active' => $user->is_active,
            'boutique_id' => $user->boutique_id,
            'boutique_active' => $boutiqueActive,
            'role' => $user->role,
            'boutique_limit' => $user->boutique_limit,
            'message' => 'Statut vérifié avec succès.'
        ], 200);
    }

    /**
     * Check the status of the user's boutique
     */
    public function checkBoutiqueStatus(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié.',
                'is_active' => false
            ], 401);
        }

        if (!$user->boutique_id) {
            return response()->json([
                'message' => 'Aucune boutique associée.',
                'is_active' => false
            ], 404);
        }

        $boutique = Boutique::find($user->boutique_id);

        if (!$boutique) {
            return response()->json([
                'message' => 'Boutique introuvable.',
                'is_active' => false
            ], 404);
        }

        $root = $boutique->getRootBoutique();
        $isRootActive = (bool) ($root->is_active && !$root->isLicenceExpired());

        return response()->json([
            'boutique_id' => $boutique->id,
            'boutique_nom' => $boutique->nom,
            'is_active' => $isRootActive,
            'is_licence_expired' => $root->isLicenceExpired(),
            'parent_id' => $boutique->parent_id,
            'root_boutique_id' => $root->id,
            'is_filiale' => $boutique->isFiliale(),
            'message' => 'Statut de la boutique vérifié avec succès.'
        ], 200);
    }
}
