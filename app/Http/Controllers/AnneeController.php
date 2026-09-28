<?php

namespace App\Http\Controllers;

use App\Models\Annee;
use App\Models\Boutique;
use Illuminate\Http\Request;

class AnneeController extends Controller
{
    public function index()
    {
        $boutique_id = $this->getBoutiqueId();
        $boutique = $boutique_id ? Boutique::find($boutique_id) : null;

        if ($boutique) {
            // S'assurer que les années actives de la boutique principale sont synchronisées vers la filiale
            $root = $boutique->getRootBoutique();
            if ($root->id !== $boutique->id) {
                $rootAnnees = Annee::where('boutique_id', $root->id)->get();
                foreach ($rootAnnees as $ra) {
                    Annee::firstOrCreate(
                        ['annee' => $ra->annee, 'boutique_id' => $boutique->id],
                        ['is_active' => $ra->is_active]
                    );
                }
            }
        }

        $annees = Annee::where(function ($q) use ($boutique_id) {
            $q->whereNull('boutique_id')
                ->orWhere('boutique_id', $boutique_id);
        })->orderBy('annee', 'desc')->get();

        return response()->json($annees, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'annee' => 'required|integer',
        ]);

        $boutique_id = $this->getBoutiqueId();
        $boutique = $boutique_id ? Boutique::find($boutique_id) : null;
        $groupBoutiqueIds = $boutique ? $boutique->getGroupBoutiqueIds() : ($boutique_id ? [$boutique_id] : []);

        $isActive = $request->has('is_active') ? (bool) $request->is_active : true;
        $annee = null;

        if (!empty($groupBoutiqueIds)) {
            // Synchroniser la création / activation de l'année sur l'ensemble des établissements du groupe (principale + filiales)
            foreach ($groupBoutiqueIds as $gid) {
                $created = Annee::updateOrCreate(
                    ['annee' => $request->annee, 'boutique_id' => $gid],
                    ['is_active' => $isActive]
                );
                if ($gid == $boutique_id) {
                    $annee = $created;
                }
            }
        } else {
            $annee = Annee::updateOrCreate(
                ['annee' => $request->annee, 'boutique_id' => $boutique_id],
                ['is_active' => $isActive]
            );
        }

        return response()->json($annee ?: Annee::where('annee', $request->annee)->first(), 201);
    }

    public function toggleStatus($id)
    {
        $annee = Annee::findOrFail($id);
        $newStatus = !$annee->is_active;
        $annee->is_active = $newStatus;
        $annee->save();

        // Si l'année est associée à une boutique avec filiales, synchroniser l'activation sur tout le groupe
        if ($annee->boutique_id) {
            $boutique = Boutique::find($annee->boutique_id);
            if ($boutique) {
                $groupBoutiqueIds = $boutique->getGroupBoutiqueIds();
                Annee::where('annee', $annee->annee)
                    ->whereIn('boutique_id', $groupBoutiqueIds)
                    ->update(['is_active' => $newStatus]);

                // S'assurer que chaque filiale possède bien l'enregistrement
                foreach ($groupBoutiqueIds as $gid) {
                    Annee::firstOrCreate(
                        ['annee' => $annee->annee, 'boutique_id' => $gid],
                        ['is_active' => $newStatus]
                    );
                }
            }
        }

        return response()->json($annee, 200);
    }

    public function destroy($id)
    {
        $annee = Annee::findOrFail($id);
        if ($annee->boutique_id) {
            $boutique = Boutique::find($annee->boutique_id);
            if ($boutique) {
                $groupBoutiqueIds = $boutique->getGroupBoutiqueIds();
                Annee::where('annee', $annee->annee)
                    ->whereIn('boutique_id', $groupBoutiqueIds)
                    ->delete();
                return response()->json(['message' => 'Année supprimée pour l\'ensemble des établissements du groupe'], 200);
            }
        }

        $annee->delete();
        return response()->json(['message' => 'Année supprimée'], 200);
    }
}
