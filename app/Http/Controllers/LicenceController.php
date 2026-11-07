<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Licence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LicenceController extends Controller
{
    /**
     * Valide et applique une clé de licence sur la boutique
     * Route publique (permet l'activation même si la session a expiré)
     */
    public function activer(Request $request)
    {
        $request->validate([
            'cle_licence' => 'required|string|min:8|max:64',
        ]);

        $cle = strtoupper(trim($request->cle_licence));

        $licence = Licence::where('cle_licence', $cle)->first();

        if (!$licence) {
            return response()->json([
                'error' => 'invalid_key',
                'message' => 'Clé d\'activation invalide ou inexistante. Veuillez vérifier votre saisie.'
            ], 404);
        }

        if ($licence->statut === 'revoquee') {
            return response()->json([
                'error' => 'revoked_key',
                'message' => 'Cette clé d\'activation a été révoquée. Veuillez contacter votre administrateur.'
            ], 403);
        }

        $boutique = Boutique::find($licence->boutique_id);

        if (!$boutique) {
            return response()->json([
                'error' => 'boutique_not_found',
                'message' => 'L\'établissement associé à cette clé n\'a pas été trouvé.'
            ], 404);
        }

        // Toujours opérer sur la boutique principale (racine du groupe d'établissements)
        $rootBoutique = $boutique->getRootBoutique();

        // Si la clé est déjà active et toujours valide pour ce groupe de boutiques
        if ($licence->statut === 'active' && !$rootBoutique->isLicenceExpired()) {
            $formattedExp = $rootBoutique->date_expiration_licence
                ? Carbon::parse($rootBoutique->date_expiration_licence)->format('d/m/Y à H:i')
                : 'Accès permanent (À vie)';

            $filialesCount = $rootBoutique->filiales()->count();

            return response()->json([
                'success' => true,
                'already_active' => true,
                'message' => "Licence active confirmée pour le groupe « {$rootBoutique->nom} »" . ($filialesCount > 0 ? " et ses {$filialesCount} filiale(s) !" : " !"),
                'boutique_id' => $rootBoutique->id,
                'boutique_nom' => $rootBoutique->nom,
                'date_expiration' => $rootBoutique->date_expiration_licence,
                'date_expiration_formatee' => $formattedExp,
                'is_unlimited' => $rootBoutique->hasUnlimitedLicence(),
                'jours_restants' => $rootBoutique->joursRestants(),
                'filiales_count' => $filialesCount,
                'jours_ajoutes' => 0,
            ], 200);
        }

        if ($licence->statut === 'active' || $licence->statut === 'expiree') {
            return response()->json([
                'error' => 'already_used',
                'message' => 'Cette clé d\'activation a déjà été utilisée le ' . ($licence->date_activation ? $licence->date_activation->format('d/m/Y') : '') . '.'
            ], 400);
        }

        DB::transaction(function () use ($licence, $rootBoutique) {
            $isUnlimited = $licence->duree_jours >= 90000;

            if ($isUnlimited) {
                // Licence illimitée / à vie
                $newExpiration = null;
            } else {
                // Si la boutique racine a déjà une date d'expiration future, on prolonge à partir de cette date
                $baseDate = ($rootBoutique->date_expiration_licence && Carbon::parse($rootBoutique->date_expiration_licence)->isFuture())
                    ? Carbon::parse($rootBoutique->date_expiration_licence)
                    : Carbon::now();

                $newExpiration = $baseDate->addDays($licence->duree_jours);
            }

            // Rattacher et activer la licence sur la boutique racine
            $licence->boutique_id = $rootBoutique->id;
            $licence->statut = 'active';
            $licence->date_activation = Carbon::now();
            $licence->date_expiration = $newExpiration;
            $licence->save();

            // Mettre à jour la boutique principale
            $rootBoutique->date_expiration_licence = $newExpiration;
            $rootBoutique->is_active = true;
            $rootBoutique->save();

            // Synchroniser automatiquement l'ensemble des filiales du groupe !
            $rootBoutique->syncLicenceToFiliales();
        });

        $formattedExp = $rootBoutique->date_expiration_licence
            ? Carbon::parse($rootBoutique->date_expiration_licence)->format('d/m/Y à H:i')
            : 'Accès permanent (À vie)';

        $filialesCount = $rootBoutique->filiales()->count();
        $filialesMsg = $filialesCount > 0 ? " (et ses {$filialesCount} filiale(s))" : "";

        return response()->json([
            'success' => true,
            'message' => "Licence activée avec succès pour le groupe « {$rootBoutique->nom} »{$filialesMsg} !",
            'boutique_id' => $rootBoutique->id,
            'boutique_nom' => $rootBoutique->nom,
            'date_expiration' => $rootBoutique->date_expiration_licence,
            'date_expiration_formatee' => $formattedExp,
            'is_unlimited' => $licence->duree_jours >= 90000,
            'jours_restants' => $rootBoutique->joursRestants(),
            'filiales_count' => $filialesCount,
            'jours_ajoutes' => $licence->duree_jours,
        ], 200);
    }

    /**
     * Récupère l'état de la licence d'une boutique (ou boutique courante/première si non spécifiée)
     */
    public function statut(Request $request, $boutique_id = null)
    {
        $boutique = $boutique_id ? Boutique::find($boutique_id) : Boutique::first();

        if (!$boutique) {
            return response()->json(['error' => 'Boutique introuvable.'], 404);
        }

        $root = $boutique->getRootBoutique();

        return response()->json([
            'boutique_id' => $boutique->id,
            'boutique_nom' => $boutique->nom,
            'root_boutique_id' => $root->id,
            'root_boutique_nom' => $root->nom,
            'is_filiale' => $boutique->isFiliale(),
            'is_active' => (bool) $root->is_active,
            'is_expired' => $root->isLicenceExpired(),
            'is_unlimited' => $root->hasUnlimitedLicence(),
            'date_expiration' => $root->date_expiration_licence,
            'date_expiration_formatee' => $root->date_expiration_licence ? \Carbon\Carbon::parse($root->date_expiration_licence)->format('d/m/Y') : null,
            'jours_restants' => $root->joursRestants(),
            'filiales_count' => $root->filiales()->count(),
        ], 200);
    }
}
