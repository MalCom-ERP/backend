<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Services\BoutiqueCacheService;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $boutique_id = $this->getBoutiqueId();
        $categories = BoutiqueCacheService::remember(
            $boutique_id,
            'categories',
            'index',
            120,
            function () use ($boutique_id) {
                return Categorie::where('boutique_id', $boutique_id)
                    ->orWhereNull('boutique_id')
                    ->orderByDesc('id')
                    ->get()
                    ->toArray();
            }
        );
        return response()->json(data: $categories);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //Creation d'une nouvelle categorie avec les donnees recues du formulaire qui contient les champs nom et description
        $categorie = new Categorie();
        $categorie->nom = $request->nom;
        $categorie->description = $request->description;
        $categorie->boutique_id = $this->getBoutiqueId();
        $categorie->save();
        BoutiqueCacheService::bump($categorie->boutique_id, ['categories', 'produits', 'dashboard']);
        return response()->json([
            'message' => 'Categorie creee avec succes',
            'id' => $categorie->id,
            'categorie' => $categorie
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Categorie $categorie)
    {
        // On retourne la categorie qui a ete trouvee
        $cat = Categorie::all()->find($categorie);

        return response()->json($$cat, 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($categorie)
    {
        // On retourne la categorie qui a ete trouvee
        ['id' => $categorie];
        $cat = Categorie::where("id", $categorie)->first();
        return response()->json($cat);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $categorie)
    {
        // On met a jour la categorie qui a ete trouvee
        ['id' => $categorie];
        $cat = Categorie::where('id', $categorie)->first();
        $cat->update($request->all());
        BoutiqueCacheService::bump($cat?->boutique_id, ['categories', 'produits', 'dashboard']);
        return response()->json(['message' => 'Categorie modifiee avec succes'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Categorie $categorie)
    {
        $boutiqueId = $categorie->boutique_id;
        $categorie->delete();
        BoutiqueCacheService::bump($boutiqueId, ['categories', 'produits', 'dashboard']);
        return response()->json(['message' => 'Categorie supprimee avec succes'], 200);
    }
}
