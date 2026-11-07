<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user) {
            return response()->json([
                'error' => 'Utilisateur non trouvé'
            ], 401);
        }

        // Check if password matches (hashed)
        if (!Hash::check($fields['password'], $user->password)) {
            // Fallback: Check if password matches plain text (for migration)
            if ($fields['password'] === $user->password) {
                // Model has 'password' => 'hashed' cast, so simple assignment triggers hashing.
                // Do NOT use Hash::make() here to avoid double hashing.
                $user->password = $fields['password'];
                $user->save();
            } else {
                return response()->json([
                    'error' => 'Mot de passe ou email incorrect'
                ], 401);
            }
        }

        if (!$user->is_active) {
            return response()->json([
                'error' => 'Votre compte est en etat désactivé, veillez contacter votre admin.'
            ], 401);
        }

        // Vérification de l'abonnement / licence de la boutique
        if ($user->boutique_id) {
            $boutique = \App\Models\Boutique::find($user->boutique_id);
            if ($boutique && $boutique->isLicenceExpired()) {
                return response()->json([
                    'error' => 'licence_expired',
                    'message' => "La licence de votre boutique « {$boutique->nom} » a expiré. Veuillez saisir votre nouvelle clé d'activation.",
                    'boutique_id' => $boutique->id,
                    'boutique_nom' => $boutique->nom,
                    'date_expiration' => $boutique->date_expiration_licence
                ], 403);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'user_role' => $user->role,
            'boutique_id' => $user->boutique_id ?: (\App\Models\Boutique::first()?->id),
            'date_expiration' => $boutique?->date_expiration_licence,
            'date_expiration_formatee' => $boutique?->date_expiration_licence ? \Carbon\Carbon::parse($boutique->date_expiration_licence)->format('d/m/Y') : null,
            'is_unlimited' => $boutique?->hasUnlimitedLicence() ?? false,
            'jours_restants' => $boutique?->joursRestants(),
            'access_token' => $token
        ], 201);
    }

    public function logout()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->tokens()->delete();
        return response()->json(['message' => 'Déconnexion réussie'], 200);
    }

    public function updateProfil(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $fields = $request->validate([
            'nom' => 'nullable|string',
            'email' => 'nullable|string|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6'
        ]);

        if (isset($fields['nom'])) {
            $user->name = $fields['nom'];
        }
        if (isset($fields['email'])) {
            $user->email = $fields['email'];
        }
        if (isset($fields['password']) && !empty($fields['password'])) {
            $user->password = $fields['password'];
        }

        $user->save();
        return response()->json($user, 200);
    }

    public function index()
    {
        if (Auth::user()->role === 'admin') {
            $boutiqueId = request()->header('X-Boutique-Id') ?: Auth::user()->boutique_id;
            return response()->json(User::where('boutique_id', $boutiqueId)->get(), 200);
        }

        return response()->json(User::where('boutique_id', Auth::user()->boutique_id)->get(), 200);
    }

    public function showUser($id)
    {
        return response()->json(User::findOrFail($id), 200);
    }

    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
            'boutique_id' => 'required|integer|exists:boutiques,id'
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => $fields['password'], 
            'role' => $fields['role'],
            'boutique_id' => $fields['boutique_id']
        ]);

        return response()->json($user, 200);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email,' . $id,
            'role' => 'required|string',
            'boutique_id' => 'required|integer|exists:boutiques,id'
        ]);

        $user->name = $fields['name'];
        $user->email = $fields['email'];
        $user->role = $fields['role'];
        $user->boutique_id = $fields['boutique_id'];

        if (request()->has('is_active')) {
            $user->is_active = $request->is_active;
        }

        if ($request->password) {
            $user->password = $request->password;
        }

        $user->save();
        return response()->json($user, 200);
    }

    public function toggleUserStatus($id)
    {
        ['user'=>$id];
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        // Si l'utilisateur est désactivé, on révoque ses jetons
        if (!$user->is_active) {
            $user->tokens()->delete();
        }
 
        return response()->json([
            'message' => 'Statut utilisateur mis à jour',
            'is_active' => $user->is_active
        ], 200);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé'], 200);
    }

    public function getProfil($id)
    {
        $admin = User::find($id);
        if (!$admin) {
            return response()->json(['error' => "Admin n'existe pas !!!"], 404);
        }
        return response()->json($admin, 200);
    }
}
