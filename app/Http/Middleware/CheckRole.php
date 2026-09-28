<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Aplatir et normaliser les rôles passés en paramètres (ex: 'admin,gestionnaire' ou ['admin', 'gestionnaire'])
        $allowedRoles = [];
        foreach ($roles as $r) {
            foreach (explode(',', $r) as $subRole) {
                $allowedRoles[] = strtolower(trim($subRole));
            }
        }

        $userRole = strtolower(trim($user->role ?? ''));

        // Si l'utilisateur est admin ou admin1 et que 'admin' est autorisé
        if (in_array($userRole, ['admin', 'admin1']) && in_array('admin', $allowedRoles)) {
            return $next($request);
        }

        if (!in_array($userRole, $allowedRoles)) {
            return response()->json([
                'message' => 'Unauthorized. Only ' . implode(' or ', $allowedRoles) . ' can access this resource.'
            ], 403);
        }

        return $next($request);
    }
}
