<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use App\Models\User;
use App\Models\Vente;
use App\Models\DetailVente;
use Illuminate\Support\Facades\Auth;

class BoutiqueController extends Controller
{
    public function allStats()
    {
        $user = Auth::user();
        if (in_array($user->role, ['admin', 'admin1'])) {
            $boutiques = Boutique::with('nature')
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('id', $user->boutique_id);
                })->get();
            if ($boutiques->isEmpty()) {
                $boutiques = Boutique::with('nature')->get();
            }
        } else {
            $boutiques = Boutique::with('nature')
                ->where('id', $user->boutique_id)
                ->get();
        }

        $reports = [];

        foreach ($boutiques as $b) {
            $salesQuery = \App\Models\Vente::where('boutique_id', $b->id)
                ->whereIn('statut', ['validee', 'payee', 'credit', 'terminee']);
            $revenue = (float)$salesQuery->sum('montant_total');
            $salesCount = $salesQuery->count();
            $usersCount = \App\Models\User::where('boutique_id', $b->id)->count();

            $reports[] = [
                'id' => $b->id,
                'nom' => $b->nom,
                'nature' => $b->nature ? [
                    'id' => $b->nature->id,
                    'name' => $b->nature->name,
                    'slug' => $b->nature->slug
                ] : null,
                'revenue' => $revenue,
                'sales_count' => $salesCount,
                'users_count' => $usersCount,
                'is_active' => $b->is_active
            ];
        }

        return response()->json($reports, 200);
    }

    public function stats(string $id)
    {
        $boutique = Boutique::with('nature')->findOrFail($id);

        $salesCount = Vente::where('boutique_id', $id)->whereIn('statut', ['validee', 'payee', 'credit', 'terminee'])->count();
        $totalRevenue = (float)Vente::where('boutique_id', $id)->whereIn('statut', ['validee', 'payee', 'credit', 'terminee'])->sum('montant_total');
        $usersCount = User::where('boutique_id', $id)->count();

        $topProducts = DetailVente::with('produit')
            ->whereHas('produit')
            ->whereHas('vente', function ($q) use ($id) {
                $q->where('boutique_id', $id);
            })
            ->selectRaw('produit_id, SUM(quantite) as total_qty, SUM(detail_ventes.montant_total) as total_amount')
            ->groupBy('produit_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return response()->json([
            'boutique' => $boutique,
            'stats' => [
                'sales_count' => $salesCount,
                'total_revenue' => $totalRevenue,
                'users_count' => $usersCount,
                'top_products' => $topProducts
            ]
        ], 200);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        if (in_array($user->role, ['admin', 'admin1'])) {
            $boutiques = Boutique::with('nature')
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('id', $user->boutique_id);
                })->get();
            if ($boutiques->isEmpty()) {
                $boutiques = Boutique::with('nature')->get();
            }
            return response()->json($boutiques, 200);
        }

        return response()->json(Boutique::with('nature')->where('id', $user->boutique_id)->get(), 200);
    }

    /**
     * Store a newly created resource in.
     */
    public function store(Request $request)
    {
        try {
            $fields = $request->validate([
                'nom' => 'required|string|unique:boutiques,nom',
                'adresse' => 'nullable|string',
                'telephone' => 'nullable|string',
                'email' => 'nullable|string|email',
                // PDF Customization
                'logo' => 'nullable|string',
                'description_facture' => 'nullable|string',
                'description_bordereau' => 'nullable|string',
                'description_recu' => 'nullable|string',
                'footer_facture' => 'nullable|string',
                'footer_bordereau' => 'nullable|string',
                'footer_recu' => 'nullable|string',
                'couleur_principale' => 'nullable|string',
                'couleur_secondaire' => 'nullable|string',
                'devise' => 'nullable|string',
                'format_facture' => 'nullable|string',
                'nature_id' => 'required|exists:natures,id',
            ]);

            $user = Auth::user();
            $boutiqueCount = Boutique::where('user_id', $user->id)->count();

            if ($boutiqueCount >= $user->boutique_limit) {
                return response()->json([
                    'message' => "Limite de boutiques atteinte ({$user->boutique_limit}). Veuillez contacter le support pour augmenter votre capacité."
                ], 403);
            }

            $fields['user_id'] = $user->id;

            // Rattachement automatique comme filiale si l'admin possède déjà une boutique principale
            $primaryBoutiqueId = $user->boutique_id ?: Boutique::where('user_id', $user->id)->whereNull('parent_id')->value('id');
            if ($primaryBoutiqueId) {
                $primaryBoutique = Boutique::find($primaryBoutiqueId);
                if ($primaryBoutique) {
                    $fields['parent_id'] = $primaryBoutique->id;
                    $fields['is_active'] = $primaryBoutique->is_active;
                    $fields['date_expiration_licence'] = $primaryBoutique->date_expiration_licence;
                } else {
                    $fields['is_active'] = true;
                }
            } else {
                $fields['is_active'] = true;
            }

            \Illuminate\Support\Facades\Log::info('Creating boutique with fields:', $fields);

            $boutique = Boutique::create($fields);

            if (isset($primaryBoutique) && $primaryBoutique) {
                $this->syncParentDataToNewFiliale($boutique, $primaryBoutique);
            }

            return response()->json($boutique, 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error creating boutique: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'message' => 'Erreur lors de la création de la boutique',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeWithManager(Request $request)
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
                // 1. Validate Boutique and Manager info
                $request->validate([
                    'boutique.nom' => 'required|string|unique:boutiques,nom',
                    'boutique.adresse' => 'nullable|string',
                    'boutique.telephone' => 'nullable|string',
                    'boutique.email' => 'nullable|string|email',
                    // PDF Customization
                    'boutique.logo' => 'nullable|string',
                    'boutique.description_facture' => 'nullable|string',
                    'boutique.description_bordereau' => 'nullable|string',
                    'boutique.description_recu' => 'nullable|string',
                    'boutique.footer_facture' => 'nullable|string',
                    'boutique.footer_bordereau' => 'nullable|string',
                    'boutique.footer_recu' => 'nullable|string',
                    'boutique.couleur_principale' => 'nullable|string',
                    'boutique.couleur_secondaire' => 'nullable|string',
                    'boutique.devise' => 'nullable|string',
                    'boutique.format_facture' => 'nullable|string',
                    'boutique.nature_id' => 'required|exists:natures,id',
                    
                    'manager.name' => 'required|string',
                    'manager.email' => 'required|string|email|unique:users,email',
                    'manager.telephone' => 'nullable|string',
                    'manager.password' => 'required|string|min:6',
                ]);

                // 2. Check Limit
                $user = Auth::user();
                $boutiqueCount = Boutique::where('user_id', $user->id)->count();

                if ($boutiqueCount >= $user->boutique_limit) {
                    throw new \Exception("Limite de boutiques atteinte ({$user->boutique_limit}).");
                }

                // 3. Create Boutique
                $boutiqueFields = $request->input('boutique');
                $boutiqueFields['user_id'] = $user->id; // The admin who created it

                // Rattachement automatique comme filiale si l'admin possède déjà une boutique principale
                $primaryBoutiqueId = $user->boutique_id ?: Boutique::where('user_id', $user->id)->whereNull('parent_id')->value('id');
                if ($primaryBoutiqueId) {
                    $primaryBoutique = Boutique::find($primaryBoutiqueId);
                    if ($primaryBoutique) {
                        $boutiqueFields['parent_id'] = $primaryBoutique->id;
                        $boutiqueFields['is_active'] = $primaryBoutique->is_active;
                        $boutiqueFields['date_expiration_licence'] = $primaryBoutique->date_expiration_licence;
                    } else {
                        $boutiqueFields['is_active'] = true;
                    }
                } else {
                    $boutiqueFields['is_active'] = true;
                }

                $boutique = Boutique::create($boutiqueFields);

                if (isset($primaryBoutique) && $primaryBoutique) {
                    $this->syncParentDataToNewFiliale($boutique, $primaryBoutique);
                }

                // 3. Create Manager User
                $managerFields = $request->input('manager');
                $user = User::create([
                    'name' => $managerFields['name'],
                    'email' => $managerFields['email'],
                    'password' => bcrypt($managerFields['password']),
                    'role' => 'gestionnaire',
                    'boutique_id' => $boutique->id,
                    'is_active' => true
                ]);

                return response()->json([
                    'message' => 'Boutique and Manager created successfully',
                    'boutique' => $boutique,
                    'manager' => $user
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la boutique et du gestionnaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(Boutique::with('nature')->findOrFail($id), 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $boutique = Boutique::findOrFail($id);

        $fields = $request->validate([
            'nom' => 'required|string|unique:boutiques,nom,' . $id,
            'adresse' => 'nullable|string',
            'telephone' => 'nullable|string',
            'email' => 'nullable|string|email',
            // PDF Customization
            'logo' => 'nullable|string',
            'description_facture' => 'nullable|string',
            'description_bordereau' => 'nullable|string',
            'description_recu' => 'nullable|string',
            'footer_facture' => 'nullable|string',
            'footer_bordereau' => 'nullable|string',
            'footer_recu' => 'nullable|string',
            'couleur_principale' => 'nullable|string',
            'couleur_secondaire' => 'nullable|string',
            'devise' => 'nullable|string',
            'format_facture' => 'nullable|string',
            'nature_id' => 'required|exists:natures,id',
        ]);

        $boutique->update($fields);

        return response()->json($boutique, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $boutique = Boutique::findOrFail($id);
        $boutique->delete();

        return response()->json(['message' => 'Boutique supprimée'], 200);
    }

    /**
     * Synchronise les années fiscales actives et les comptes comptables personnalisés
     * depuis la boutique principale vers une nouvelle filiale.
     */
    protected function syncParentDataToNewFiliale(Boutique $filiale, Boutique $parent): void
    {
        try {
            // 1. Synchroniser les années actives
            $parentAnnees = \App\Models\Annee::where('boutique_id', $parent->id)->get();
            foreach ($parentAnnees as $annee) {
                \App\Models\Annee::firstOrCreate(
                    ['annee' => $annee->annee, 'boutique_id' => $filiale->id],
                    ['is_active' => $annee->is_active]
                );
            }

            // 2. Synchroniser les comptes comptables personnalisés
            $parentComptes = \App\Models\CompteComptable::where('boutique_id', $parent->id)->get();
            foreach ($parentComptes as $compte) {
                \App\Models\CompteComptable::firstOrCreate(
                    ['numero' => $compte->numero, 'boutique_id' => $filiale->id],
                    [
                        'libelle' => $compte->libelle,
                        'classe' => $compte->classe,
                        'type' => $compte->type,
                        'sens_normal' => $compte->sens_normal,
                        'is_system' => false,
                        'is_active' => $compte->is_active,
                    ]
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Échec de synchronisation filiale depuis le parent: ' . $e->getMessage());
        }
    }
}
