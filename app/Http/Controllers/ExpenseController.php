<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $boutiqueId = $this->getBoutiqueId() ?: $request->boutique_id;
        $query = Expense::with('user');
        
        if ($boutiqueId) {
            $query->where('boutique_id', $boutiqueId);
        }

        if ($request->has('search') && !empty($request->search)) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('type', 'LIKE', "%{$s}%")
                  ->orWhere('description', 'LIKE', "%{$s}%")
                  ->orWhere('beneficiaire', 'LIKE', "%{$s}%")
                  ->orWhere('reference_piece', 'LIKE', "%{$s}%");
            });
        }

        if ($request->has('month') && $request->month) {
            $query->whereMonth('date', $request->month);
        }
        if ($request->has('year') && $request->year) {
            $query->whereYear('date', $request->year);
        }
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        if ($request->has('mode_paiement') && $request->mode_paiement) {
            $query->where('mode_paiement', $request->mode_paiement);
        }

        return response()->json($query->orderByDesc('date')->orderByDesc('id')->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $boutiqueId = $request->boutique_id ?: $this->getBoutiqueId();
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique introuvable.'], 422);
        }

        $fields = $request->validate([
            'type' => 'required|string',
            'montant' => 'required|numeric|min:0',
            'mode_paiement' => 'nullable|string',
            'reference_piece' => 'nullable|string|max:100',
            'beneficiaire' => 'nullable|string|max:191',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'boutique_id' => 'nullable|exists:boutiques,id',
        ]);

        $fields['boutique_id'] = $boutiqueId;
        $fields['user_id'] = Auth::id();
        $fields['mode_paiement'] = $fields['mode_paiement'] ?: 'especes';

        $expense = Expense::create($fields);

        // Écriture comptable automatique SYSCOHADA (Débit Charges Classe 6, Crédit Caisse/Banque)
        try {
            app(\App\Services\ComptaService::class)->enregistrerDepense($expense);
        } catch (\Exception $e) {
            // Continuer même si la compta n'est pas initialisée
        }

        return response()->json($expense, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        return response()->json($expense->load(['user', 'boutique']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $fields = $request->validate([
            'type' => 'required|string',
            'montant' => 'required|numeric|min:0',
            'mode_paiement' => 'nullable|string',
            'reference_piece' => 'nullable|string|max:100',
            'beneficiaire' => 'nullable|string|max:191',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        $fields['mode_paiement'] = $fields['mode_paiement'] ?: ($expense->mode_paiement ?: 'especes');

        try {
            $comptaService = app(\App\Services\ComptaService::class);
            $comptaService->enregistrerAnnulationDepense($expense);
            \App\Models\EcritureComptable::where('source_type', 'Expense')
                ->where('source_id', $expense->id)
                ->delete();
        } catch (\Exception $e) {}

        $expense->update($fields);

        try {
            $comptaService = app(\App\Services\ComptaService::class);
            $comptaService->enregistrerDepense($expense);
        } catch (\Exception $e) {}

        return response()->json($expense);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        try {
            app(\App\Services\ComptaService::class)->enregistrerAnnulationDepense($expense);
        } catch (\Exception $e) {}
        
        $expense->delete();
        return response()->json(['message' => 'Dépense supprimée avec succès']);
    }

    public function dashboard(Request $request)
    { 
        $boutiqueId = $this->getBoutiqueId() ?: $request->boutique_id;

        $query = Expense::query();
        if ($boutiqueId) {
            $query->where('boutique_id', $boutiqueId);
        }

        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        
        $totalByYear = (clone $query)->whereYear('date', $year)->sum('montant');
        $totalByMonth = (clone $query)->whereYear('date', $year)->whereMonth('date', $month)->sum('montant');
        
        $monthlyEvolution = (clone $query)->whereYear('date', $year)
            ->selectRaw('MONTH(date) as month, SUM(montant) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $breakdownByType = (clone $query)->whereYear('date', $year)
            ->selectRaw('type, SUM(montant) as total, COUNT(*) as count')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        $breakdownByMode = (clone $query)->whereYear('date', $year)
            ->selectRaw('COALESCE(mode_paiement, "especes") as mode, SUM(montant) as total')
            ->groupBy('mode')
            ->get();

        return response()->json([
            'total_year' => (float)($totalByYear ?? 0),
            'total_month' => (float)($totalByMonth ?? 0),
            'monthly_evolution' => $monthlyEvolution ?? [],
            'breakdown_by_type' => $breakdownByType ?? [],
            'breakdown_by_mode' => $breakdownByMode ?? [],
            'year' => $year ?? date('Y'),
            'month' => $month ?? date('m')
        ]);
    }
}
