<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    /**
     * Obtenir l'objet boutique actuelle (chargé via middleware ou résolu dynamiquement).
     */
    protected function getActiveBoutique()
    {
        $boutiqueId = $this->getBoutiqueId();
        $boutique = request()->attributes->get('active_boutique');
        
        if (!$boutique || ($boutiqueId && $boutique->id !== $boutiqueId)) {
            if ($boutiqueId) {
                $boutique = \App\Models\Boutique::with('nature')->find($boutiqueId);
                if ($boutique) {
                    request()->attributes->set('active_boutique', $boutique);
                }
            }
        }
        
        return $boutique;
    }

    /**
     * Obtenir l'ID de la boutique actuelle de manière sécurisée et dynamique.
     */
    protected function getBoutiqueId()
    {
        $user = Auth::user();
        if (!$user) return null;

        // Priorité absolue : paramètre explicite de requête (input / query) ou header X-Boutique-Id
        $requestedId = request()->header('X-Boutique-Id') 
            ?: request()->input('boutique_id') 
            ?: request()->query('boutique_id');

        if ($requestedId === 'null' || $requestedId === 'undefined' || $requestedId === '' || $requestedId === null) {
            $requestedId = null;
        } else {
            $requestedId = (int) $requestedId;
        }

        // Seuls les administrateurs globaux et admin1 peuvent basculer librement d'une boutique à une autre
        if (in_array($user->role, ['admin', 'admin1'])) {
            if ($requestedId) {
                return $requestedId;
            }
            $boutique = request()->attributes->get('active_boutique');
            if ($boutique) {
                return $boutique->id;
            }
            if ($user->boutique_id) {
                return $user->boutique_id;
            }
            // Fallback automatique sur la première boutique existante pour le super admin
            return \App\Models\Boutique::first()?->id;
        }

        // Pour les autres rôles (vendeur, gestionnaire, comptable), la boutique assignée est impérative
        return $user->boutique_id ?: (\App\Models\Boutique::first()?->id);
    }
}
