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

        // 1. Si la clé a déjà été activée au moins une fois, elle reste RÉUTILISABLE sur tous les
        //    navigateurs / postes (Admin, Gestionnaires, Vendeurs, Comptables, Filiales) tant qu'elle n'est pas expirée !
        if ($licence->statut === 'active') {
            $isUnlimited = $licence->duree_jours >= 90000 || $rootBoutique->hasUnlimitedLicence();
            $licenceExpired = !$isUnlimited && $licence->date_expiration && now()->greaterThan($licence->date_expiration);
            $boutiqueExpired = $rootBoutique->isLicenceExpired();

            if (!$licenceExpired || !$boutiqueExpired) {
                // S'assurer que la boutique racine et ses filiales sont bien synchronisées avec cette licence valide
                if ($boutiqueExpired && !$licenceExpired) {
                    $rootBoutique->date_expiration_licence = $isUnlimited ? null : $licence->date_expiration;
                    $rootBoutique->is_active = true;
                    $rootBoutique->save();
                    $rootBoutique->syncLicenceToFiliales();
                }

                $effectiveExp = $rootBoutique->date_expiration_licence ?: $licence->date_expiration;
                $formattedExp = ($isUnlimited || !$effectiveExp)
                    ? 'Accès permanent (À vie)'
                    : Carbon::parse($effectiveExp)->format('d/m/Y à H:i');

                $filialesCount = $rootBoutique->filiales()->count();

                return response()->json([
                    'success' => true,
                    'already_active' => true,
                    'message' => "Clé valide reconnue ! Accès déverrouillé sur cet appareil pour « {$rootBoutique->nom} »" . ($filialesCount > 0 ? " et ses {$filialesCount} filiale(s)." : "."),
                    'boutique_id' => $rootBoutique->id,
                    'boutique_nom' => $rootBoutique->nom,
                    'date_expiration' => $effectiveExp,
                    'date_expiration_formatee' => $formattedExp,
                    'is_unlimited' => $isUnlimited,
                    'jours_restants' => $rootBoutique->joursRestants(),
                    'filiales_count' => $filialesCount,
                    'jours_ajoutes' => 0,
                ], 200);
            }

            // Si la date d'expiration est dépassée, marquer la clé comme expirée
            $licence->statut = 'expiree';
            $licence->save();
        }

        if ($licence->statut === 'expiree' || $licence->isExpired()) {
            $expDateStr = $licence->date_expiration ? $licence->date_expiration->format('d/m/Y') : '';
            return response()->json([
                'error' => 'expired_key',
                'message' => 'Cette clé d\'activation a expiré' . ($expDateStr ? " le {$expDateStr}" : '') . '. Veuillez demander une nouvelle clé de renouvellement.'
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
     * Récupère l'état de la licence d'une boutique.
     * SÉCURITÉ : Si aucun boutique_id n'est fourni (ex: nouveau navigateur ou nouvelle application non activée),
     * on ne déverrouille JAMAIS automatiquement en prenant une autre boutique active de la base !
     * Le nouvel appareil doit obligatoirement saisir sa clé d'activation pour se déverrouiller.
     */
    public function statut(Request $request, $boutique_id = null)
    {
        if (!$boutique_id || $boutique_id === 'null' || $boutique_id === 'undefined') {
            return response()->json([
                'requires_activation' => true,
                'is_active' => false,
                'is_expired' => true,
                'message' => 'Veuillez saisir votre clé de licence pour activer l\'accès sur cet appareil.'
            ], 200);
        }

        $boutique = Boutique::find($boutique_id);

        if (!$boutique) {
            return response()->json([
                'requires_activation' => true,
                'is_active' => false,
                'is_expired' => true,
                'error' => 'Boutique introuvable.'
            ], 404);
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
