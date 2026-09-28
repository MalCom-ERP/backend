<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureBoutiqueIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $boutiqueId = $request->header('X-Boutique-Id') ?: (Auth::user()?->boutique_id);

        \Log::debug('EnsureBoutiqueIsActive middleware', [
            'header_boutique_id' => $request->header('X-Boutique-Id'),
            'user_boutique_id' => Auth::user()?->boutique_id,
            'resolved_boutique_id' => $boutiqueId,
            'user_id' => Auth::id()
        ]);

        if ($boutiqueId && $boutiqueId !== 'null' && $boutiqueId !== '') {
            $boutique = \App\Models\Boutique::find($boutiqueId);
            if ($boutique) {
                $root = $boutique->getRootBoutique();

                if (!$root->is_active) {
                    \Log::warning("Access denied to inactive boutique/group {$root->id} by user " . Auth::id());
                    $nom = $boutique->isFiliale() ? "{$boutique->nom} (rattachée à {$root->nom})" : $boutique->nom;
                    return response()->json([
                        'error' => "Cette boutique ({$nom}) est désactivée. Accès refusé."
                    ], 403);
                }

                if ($root->isLicenceExpired()) {
                    \Log::warning("Access denied: expired licence for boutique/group {$root->id}");
                    $nom = $boutique->isFiliale() ? "« {$boutique->nom} » (Filiale de « {$root->nom} »)" : "« {$boutique->nom} »";
                    return response()->json([
                        'error' => 'licence_expired',
                        'message' => "La licence de votre groupe d'établissements {$nom} a expiré. Veuillez saisir votre clé d'activation.",
                        'boutique_id' => $root->id,
                        'boutique_nom' => $root->nom,
                        'date_expiration' => $root->date_expiration_licence
                    ], 403);
                }
            } else {
                \Log::error("Boutique {$boutiqueId} not found in middleware");
            }
        }

        return $next($request);
    }
}
