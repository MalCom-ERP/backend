<?php

namespace App\Http\Controllers;

use App\Models\Nature;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NatureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Nature::all(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|unique:natures,name',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        $fields['slug'] = Str::slug($fields['name']);

        $nature = Nature::create($fields);

        return response()->json($nature, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Nature $nature)
    {
        return response()->json($nature, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Nature $nature)
    {
        $fields = $request->validate([
            'name' => 'string|unique:natures,name,' . $nature->id,
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        if (isset($fields['name'])) {
            $fields['slug'] = Str::slug($fields['name']);
        }

        $nature->update($fields);

        return response()->json($nature, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Nature $nature)
    {
        // Check if any boutiques are using this nature
        if ($nature->boutiques()->exists()) {
            return response()->json(['error' => 'Impossible de supprimer une nature utilisée par des boutiques'], 400);
        }

        $nature->delete();

        return response()->json(['message' => 'Nature supprimée avec succès'], 200);
    }
}
