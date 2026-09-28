<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActiveStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Non authentifié.',
                'error' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                'error' => 'User account is inactive',
                'is_active' => false
            ], 403);
        }

        // Check if user has a boutique and if it's active
        if ($user->boutique_id) {
            $boutique = \App\Models\Boutique::find($user->boutique_id);

            if ($boutique && !$boutique->is_active) {
                return response()->json([
                    'message' => 'Votre boutique a été bloquée. Veuillez contacter l\'administrateur.',
                    'error' => 'Boutique is blocked',
                    'boutique_active' => false
                ], 403);
            }

            if ($boutique && $boutique->isLicenceExpired()) {
                return response()->json([
                    'message' => "La licence de votre boutique « {$boutique->nom} » a expiré. Veuillez saisir votre clé d'activation.",
                    'error' => 'licence_expired',
                    'boutique_active' => false,
                    'is_licence_expired' => true
                ], 403);
            }
        }

        return $next($request);
    }
}
