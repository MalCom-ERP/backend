<?php

namespace App\Services;

use App\Models\CompteComptable;
use App\Models\JournalComptable;
use App\Models\EcritureComptable;
use App\Models\LigneEcriture;
use App\Models\Vente;
use App\Models\PaiementCredit;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ComptaService
{
    /**
     * Trouver ou récupérer un compte par son numéro (cherche d'abord en local, sinon compte système)
     */
    public function getCompte(string $numero, ?int $boutiqueId = null): ?CompteComptable
    {
        $clean = rtrim($numero, '0');
        $candidates = array_unique(array_filter([
            $numero,
            $clean,
            $clean . '000',
            $clean . '100',
            strlen($numero) === 3 ? $numero . '1' : null,
            strlen($numero) === 3 ? $numero . '100' : null,
            substr($numero, 0, 4),
            substr($numero, 0, 3)
        ]));

        $compte = null;
        if ($boutiqueId) {
            $compte = CompteComptable::whereIn('numero', $candidates)
                ->where('boutique_id', $boutiqueId)
                ->where('is_active', true)
                ->first();
        }

        if (!$compte) {
            $compte = CompteComptable::whereIn('numero', $candidates)
                ->whereNull('boutique_id')
                ->where('is_active', true)
                ->first();
        }

        return $compte;
    }

    /**
     * Trouver ou récupérer un journal par son code
     */
    public function getJournal(string $code, ?int $boutiqueId = null): ?JournalComptable
    {
        $journal = null;
        if ($boutiqueId) {
            $journal = JournalComptable::where('code', $code)
                ->where('boutique_id', $boutiqueId)
                ->first();
        }

        if (!$journal) {
            $journal = JournalComptable::where('code', $code)
                ->whereNull('boutique_id')
                ->first();
        }

        return $journal;
    }

    /**
     * Générer le prochain numéro de pièce
     */
    public function generateNumeroPiece(string $journalCode, int $boutiqueId): string
    {
        $year = date('Y');
        $journal = $this->getJournal($journalCode, $boutiqueId);
        $journalId = $journal ? $journal->id : 1;

        $count = EcritureComptable::where('boutique_id', $boutiqueId)
            ->where('journal_id', $journalId)
            ->whereYear('date_ecriture', $year)
            ->count() + 1;

        return sprintf('%s-%s-%05d', $journalCode, $year, $count);
    }

    /**
     * Créer une écriture comptable avec contrôle strict de la partie double (Débit = Crédit)
     */
    public function createEcriture(
        int $boutiqueId,
        string $journalCode,
        string $date,
        string $libelle,
        array $lignes,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $userId = null
    ): EcritureComptable {
        $journal = $this->getJournal($journalCode, $boutiqueId);
        if (!$journal) {
            throw new \Exception("Journal comptable '{$journalCode}' introuvable.");
        }

        // Vérifier l'équilibre Débit / Crédit et la validité des lignes
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lignes as $idx => $ligne) {
            $deb = (float) ($ligne['debit'] ?? 0);
            $cred = (float) ($ligne['credit'] ?? 0);

            if ($deb < 0 || $cred < 0) {
                throw new \Exception("Ligne #" . ($idx + 1) . " : Les montants comptables ne peuvent pas être négatifs.");
            }
            if ($deb > 0 && $cred > 0) {
                throw new \Exception("Ligne #" . ($idx + 1) . " : Une ligne comptable doit être exclusivement au débit ou au crédit, jamais les deux.");
            }
            if ($deb == 0 && $cred == 0) {
                throw new \Exception("Ligne #" . ($idx + 1) . " : Le montant débit ou crédit doit être supérieur à zéro.");
            }

            $totalDebit += $deb;
            $totalCredit += $cred;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception("Écriture déséquilibrée : Total Débit ({$totalDebit}) != Total Crédit ({$totalCredit}).");
        }

        if ($totalDebit <= 0) {
            throw new \Exception("Une écriture comptable doit avoir un montant supérieur à zéro.");
        }

        $numeroPiece = $this->generateNumeroPiece($journalCode, $boutiqueId);

        return DB::transaction(function () use ($boutiqueId, $journal, $date, $numeroPiece, $libelle, $sourceType, $sourceId, $userId, $lignes) {
            $ecriture = EcritureComptable::create([
                'boutique_id' => $boutiqueId,
                'journal_id' => $journal->id,
                'user_id' => $userId,
                'date_ecriture' => $date,
                'numero_piece' => $numeroPiece,
                'libelle' => $libelle,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'statut' => 'validee',
            ]);

            foreach ($lignes as $ligne) {
                $compte = null;
                if (!empty($ligne['compte_id'])) {
                    $compte = CompteComptable::find($ligne['compte_id']);
                } elseif (!empty($ligne['numero'])) {
                    $compte = $this->getCompte($ligne['numero'], $boutiqueId);
                }

                if (!$compte) {
                    throw new \Exception("Compte comptable introuvable pour la ligne : " . json_encode($ligne));
                }

                // Sécurité multi-tenant : vérifier que le compte appartient soit au plan système soit à la boutique
                if ($compte->boutique_id !== null && (int)$compte->boutique_id !== (int)$boutiqueId) {
                    throw new \Exception("Accès non autorisé : Le compte '{$compte->numero}' n'appartient pas à cette boutique.");
                }

                LigneEcriture::create([
                    'ecriture_id' => $ecriture->id,
                    'compte_id' => $compte->id,
                    'libelle' => $ligne['libelle'] ?? $libelle,
                    'debit' => (float) ($ligne['debit'] ?? 0),
                    'credit' => (float) ($ligne['credit'] ?? 0),
                ]);
            }

            return $ecriture->load(['journal', 'lignes.compte']);
        });
    }

    /**
     * Enregistrer l'écriture automatique pour une vente
     */
    public function enregistrerVente(Vente $vente): void
    {
        try {
            // Éviter les doublons
            $exists = EcritureComptable::where('source_type', 'Vente')
                ->where('source_id', $vente->id)
                ->exists();
            if ($exists) return;

            $montant = (float) $vente->montant_total;
            if ($montant <= 0) return;

            $date = $vente->date_vente ? Carbon::parse($vente->date_vente)->format('Y-m-d') : date('Y-m-d');
            $libelle = "Vente #" . $vente->id . ($vente->client ? " - " . $vente->client->nom : "");

            if ($vente->type_paiement === 'credit') {
                // Vente à crédit : Débit 411 (Client), Crédit 701 (Vente)
                $lignes = [
                    [
                        'numero' => '411', // Clients
                        'debit' => $montant,
                        'credit' => 0,
                        'libelle' => $libelle . " (Créance)",
                    ],
                    [
                        'numero' => '701', // Ventes de marchandises
                        'debit' => 0,
                        'credit' => $montant,
                        'libelle' => $libelle,
                    ],
                ];

                $this->createEcriture(
                    $vente->boutique_id,
                    'VT',
                    $date,
                    $libelle . " [Crédit]",
                    $lignes,
                    'Vente',
                    $vente->id,
                    $vente->user_id
                );

                // Si un acompte / avance a été versé immédiatement
                $avance = (float) ($vente->montant_avance ?? 0);
                if ($avance > 0) {
                    $lignesAvance = [
                        [
                            'numero' => '571', // Caisse
                            'debit' => $avance,
                            'credit' => 0,
                            'libelle' => "Avance/Acompte Vente #" . $vente->id,
                        ],
                        [
                            'numero' => '411', // Clients
                            'debit' => 0,
                            'credit' => $avance,
                            'libelle' => "Règlement acompte Vente #" . $vente->id,
                        ],
                    ];

                    $this->createEcriture(
                        $vente->boutique_id,
                        'CA',
                        $date,
                        "Acompte sur Vente #" . $vente->id,
                        $lignesAvance,
                        'VenteAvance',
                        $vente->id,
                        $vente->user_id
                    );
                }
            } elseif ($vente->type_paiement !== 'proforma') {
                // Vente au comptant : Débit 571 (Caisse), Crédit 701 (Vente)
                $lignes = [
                    [
                        'numero' => '571', // Caisse principale
                        'debit' => $montant,
                        'credit' => 0,
                        'libelle' => $libelle,
                    ],
                    [
                        'numero' => '701', // Ventes de marchandises
                        'debit' => 0,
                        'credit' => $montant,
                        'libelle' => $libelle,
                    ],
                ];

                $this->createEcriture(
                    $vente->boutique_id,
                    'VT',
                    $date,
                    $libelle,
                    $lignes,
                    'Vente',
                    $vente->id,
                    $vente->user_id
                );
            }
        } catch (\Exception $e) {
            Log::error("Erreur ComptaService::enregistrerVente: " . $e->getMessage(), [
                'vente_id' => $vente->id,
            ]);
        }
    }

    /**
     * Enregistrer la contre-passation (annulation) d'une vente
     */
    public function enregistrerAnnulationVente(Vente $vente): void
    {
        try {
            $exists = EcritureComptable::where('source_type', 'AnnulationVente')
                ->where('source_id', $vente->id)
                ->exists();
            if ($exists) return;

            $montant = (float) $vente->montant_total;
            if ($montant <= 0) {
                // Si le montant de la vente a déjà été mis à 0 par le contrôleur, on récupère le montant de l'écriture initiale
                $origEcriture = EcritureComptable::where('source_type', 'Vente')
                    ->where('source_id', $vente->id)
                    ->with('lignes')
                    ->first();
                if ($origEcriture && $origEcriture->lignes->count() > 0) {
                    $montant = (float) $origEcriture->lignes->sum('debit');
                }
            }

            if ($montant <= 0) {
                Log::warning("ComptaService::enregistrerAnnulationVente: Impossible d'annuler la vente #{$vente->id}, montant nul ou écriture introuvable.");
                return;
            }

            $date = date('Y-m-d');
            $libelle = "Annulation Vente #" . $vente->id;

            if ($vente->type_paiement === 'credit') {
                $lignes = [
                    [
                        'numero' => '701', // Ventes de marchandises
                        'debit' => $montant,
                        'credit' => 0,
                        'libelle' => $libelle,
                    ],
                    [
                        'numero' => '411', // Clients
                        'debit' => 0,
                        'credit' => $montant,
                        'libelle' => $libelle . " (Créance annulée)",
                    ],
                ];
                
                $this->createEcriture(
                    $vente->boutique_id,
                    'VT',
                    $date,
                    $libelle,
                    $lignes,
                    'AnnulationVente',
                    $vente->id,
                    $vente->user_id
                );
                
            } else {
                $lignes = [
                    [
                        'numero' => '701', // Ventes de marchandises
                        'debit' => $montant,
                        'credit' => 0,
                        'libelle' => $libelle,
                    ],
                    [
                        'numero' => '571', // Caisse
                        'debit' => 0,
                        'credit' => $montant,
                        'libelle' => $libelle,
                    ],
                ];

                $this->createEcriture(
                    $vente->boutique_id,
                    'VT',
                    $date,
                    $libelle,
                    $lignes,
                    'AnnulationVente',
                    $vente->id,
                    $vente->user_id
                );
            }
        } catch (\Exception $e) {
            Log::error("Erreur ComptaService::enregistrerAnnulationVente: " . $e->getMessage(), [
                'vente_id' => $vente->id,
            ]);
        }
    }

    /**
     * Enregistrer l'écriture automatique pour un règlement de crédit
     */
    public function enregistrerPaiementCredit(PaiementCredit $paiement): void
    {
        try {
            $exists = EcritureComptable::where('source_type', 'PaiementCredit')
                ->where('source_id', $paiement->id)
                ->exists();
            if ($exists) return;

            $montant = (float) $paiement->montant;
            if ($montant <= 0) return;

            $date = $paiement->created_at ? Carbon::parse($paiement->created_at)->format('Y-m-d') : date('Y-m-d');
            $libelle = "Règlement dette Vente #" . $paiement->vente_id;

            $lignes = [
                [
                    'numero' => '571', // Caisse
                    'debit' => $montant,
                    'credit' => 0,
                    'libelle' => $libelle,
                ],
                [
                    'numero' => '411', // Clients
                    'debit' => 0,
                    'credit' => $montant,
                    'libelle' => $libelle,
                ],
            ];

            $this->createEcriture(
                $paiement->boutique_id,
                'CA',
                $date,
                $libelle,
                $lignes,
                'PaiementCredit',
                $paiement->id,
                $paiement->user_id
            );
        } catch (\Exception $e) {
            Log::error("Erreur ComptaService::enregistrerPaiementCredit: " . $e->getMessage(), [
                'paiement_id' => $paiement->id,
            ]);
        }
    }

    /**
     * Enregistrer l'écriture automatique pour une dépense
     */
    public function enregistrerDepense(Expense $expense): void
    {
        try {
            $exists = EcritureComptable::where('source_type', 'Expense')
                ->where('source_id', $expense->id)
                ->exists();
            if ($exists) return;

            $montant = (float) $expense->montant;
            if ($montant <= 0) return;

            $date = $expense->date ? Carbon::parse($expense->date)->format('Y-m-d') : date('Y-m-d');

            // Déterminer le compte de charge SYSCOHADA approprié selon le type
            $typeClean = mb_strtolower($expense->type ?? '');
            $compteCharge = '658'; // Par défaut : Autres charges d'exploitation

            if (str_contains($typeClean, 'loyer')) {
                $compteCharge = '622'; // Locations et charges locatives
            } elseif (str_contains($typeClean, 'eau') || str_contains($typeClean, 'electr') || str_contains($typeClean, 'carburant')) {
                $compteCharge = '605'; // Électricité, eau, carburant
            } elseif (str_contains($typeClean, 'salaire') || str_contains($typeClean, 'personnel')) {
                $compteCharge = '661'; // Salaires du personnel
            } elseif (str_contains($typeClean, 'transport') || str_contains($typeClean, 'livraison')) {
                $compteCharge = '612'; // Transports
            } elseif (str_contains($typeClean, 'entretien') || str_contains($typeClean, 'repar')) {
                $compteCharge = '624'; // Entretien et réparations
            } elseif (str_contains($typeClean, 'internet') || str_contains($typeClean, 'telephon')) {
                $compteCharge = '628'; // Téléphone et Internet
            } elseif (str_contains($typeClean, 'fourniture')) {
                $compteCharge = '6052'; // Fournitures de bureau
            }
            $libelle = "Dépense : " . ($expense->type ?? 'Générale') . ($expense->description ? " - " . $expense->description : "");

            $lignes = [
                [
                    'numero' => $compteCharge,
                    'debit' => $montant,
                    'credit' => 0,
                    'libelle' => $libelle,
                ],
                [
                    'numero' => '571', // Caisse
                    'debit' => 0,
                    'credit' => $montant,
                    'libelle' => $libelle,
                ],
            ];

            $this->createEcriture(
                $expense->boutique_id,
                'CA',
                $date,
                $libelle,
                $lignes,
                'Expense',
                $expense->id,
                $expense->user_id
            );
        } catch (\Exception $e) {
            Log::error("Erreur ComptaService::enregistrerDepense: " . $e->getMessage(), [
                'expense_id' => $expense->id,
            ]);
        }
    }

    /**
     * Enregistrer l'écriture automatique pour l'annulation d'une dépense
     */
    public function enregistrerAnnulationDepense(Expense $expense): void
    {
        try {
            $exists = EcritureComptable::where('source_type', 'AnnulationExpense')
                ->where('source_id', $expense->id)
                ->exists();
            if ($exists) return;

            $montant = (float) $expense->montant;
            if ($montant <= 0) return;

            $date = date('Y-m-d');
            $typeClean = mb_strtolower($expense->type ?? '');
            $compteCharge = '658'; // Par défaut : Autres charges d'exploitation

            if (str_contains($typeClean, 'loyer')) {
                $compteCharge = '622';
            } elseif (str_contains($typeClean, 'eau') || str_contains($typeClean, 'electr') || str_contains($typeClean, 'carburant')) {
                $compteCharge = '605';
            } elseif (str_contains($typeClean, 'salaire') || str_contains($typeClean, 'personnel')) {
                $compteCharge = '661';
            } elseif (str_contains($typeClean, 'transport') || str_contains($typeClean, 'livraison')) {
                $compteCharge = '612';
            } elseif (str_contains($typeClean, 'entretien') || str_contains($typeClean, 'repar')) {
                $compteCharge = '624';
            } elseif (str_contains($typeClean, 'internet') || str_contains($typeClean, 'telephon')) {
                $compteCharge = '628';
            } elseif (str_contains($typeClean, 'fourniture')) {
                $compteCharge = '6052';
            }
            $libelle = "Annulation Dépense : " . ($expense->type ?? 'Générale');

            $lignes = [
                [
                    'numero' => '571', // Caisse (On remet l'argent)
                    'debit' => $montant,
                    'credit' => 0,
                    'libelle' => $libelle,
                ],
                [
                    'numero' => $compteCharge, // On annule la charge
                    'debit' => 0,
                    'credit' => $montant,
                    'libelle' => $libelle,
                ],
            ];

            $this->createEcriture(
                $expense->boutique_id,
                'CA',
                $date,
                $libelle,
                $lignes,
                'AnnulationExpense',
                $expense->id,
                $expense->user_id
            );
        } catch (\Exception $e) {
            Log::error("Erreur ComptaService::enregistrerAnnulationDepense: " . $e->getMessage(), [
                'expense_id' => $expense->id,
            ]);
        }
    }

    /**
     * Enregistrer l'écriture automatique pour un réapprovisionnement (Achats de marchandises)
     */
    public function enregistrerReappro(array $produits, int $boutiqueId, ?int $userId = null): void
    {
        try {
            $totalAchats = 0;
            $itemsNames = [];
            foreach ($produits as $item) {
                $qte = (float)($item['quantite'] ?? 0);
                $prixAchat = (float)($item['prix_achat'] ?? 0);
                $totalAchats += ($qte * $prixAchat);
                if (!empty($item['produit'])) {
                    $itemsNames[] = $item['produit'] . " (x" . $qte . ")";
                }
            }

            if ($totalAchats <= 0) return;

            $date = date('Y-m-d');
            $detailStr = count($itemsNames) > 0 ? ": " . implode(', ', array_slice($itemsNames, 0, 3)) : "";
            $libelle = "Réapprovisionnement stock" . $detailStr;

            // Débit 601 (Achats de marchandises), Crédit 571 (Caisse)
            $lignes = [
                [
                    'numero' => '601', // Achats de marchandises
                    'debit' => $totalAchats,
                    'credit' => 0,
                    'libelle' => $libelle,
                ],
                [
                    'numero' => '571', // Caisse
                    'debit' => 0,
                    'credit' => $totalAchats,
                    'libelle' => $libelle,
                ],
            ];

            $this->createEcriture(
                $boutiqueId,
                'AC',
                $date,
                $libelle,
                $lignes,
                'Reappro',
                null,
                $userId
            );
        } catch (\Exception $e) {
            Log::error("Erreur ComptaService::enregistrerReappro: " . $e->getMessage(), [
                'boutique_id' => $boutiqueId,
                'total' => $totalAchats ?? 0,
            ]);
        }
    }

    /**
     * Enregistrer un ajustement d'inventaire ou écart de stock
     */
    public function enregistrerAjustementStock(int $boutiqueId, string $libelle, float $montant, string $sens = 'perte', ?int $userId = null): void
    {
        try {
            if ($montant <= 0) return;

            $date = date('Y-m-d');

            if ($sens === 'perte' || $sens === 'manquant') {
                // Perte / Manquant sur stock : Débit 603 (Variation stocks) / Crédit 311 (Marchandises)
                $lignes = [
                    [
                        'numero' => '603', // Variation des stocks de marchandises
                        'debit' => $montant,
                        'credit' => 0,
                        'libelle' => $libelle . " (Perte/Manquant)",
                    ],
                    [
                        'numero' => '311', // Marchandises
                        'debit' => 0,
                        'credit' => $montant,
                        'libelle' => $libelle,
                    ],
                ];
            } else {
                // Surplus d'inventaire : Débit 311 (Marchandises) / Crédit 603 (Variation stocks)
                $lignes = [
                    [
                        'numero' => '311', // Marchandises
                        'debit' => $montant,
                        'credit' => 0,
                        'libelle' => $libelle,
                    ],
                    [
                        'numero' => '603', // Variation des stocks de marchandises
                        'debit' => 0,
                        'credit' => $montant,
                        'libelle' => $libelle . " (Surplus)",
                    ],
                ];
            }

            $this->createEcriture(
                $boutiqueId,
                'OD',
                $date,
                $libelle,
                $lignes,
                'InventaireAjustement',
                null,
                $userId
            );
        } catch (\Exception $e) {
            Log::error("Erreur ComptaService::enregistrerAjustementStock: " . $e->getMessage(), [
                'boutique_id' => $boutiqueId,
                'montant' => $montant,
            ]);
        }
    }

    /**
     * Synchroniser tout l'historique non comptabilisé d'une boutique (Ventes, Dépenses, Règlements)
     */
    public function synchroniserHistorique(int $boutiqueId): array
    {
        $ventesSync = 0;
        $depensesSync = 0;
        $paiementsSync = 0;

        // 1. Ventes non encore comptabilisées
        $ventes = Vente::where('boutique_id', $boutiqueId)
            ->whereIn('statut', ['validee', 'payee', 'credit', 'terminee'])
            ->whereNotIn('id', function($q) {
                $q->select('source_id')
                  ->from('ecritures_comptables')
                  ->where('source_type', 'Vente')
                  ->whereNotNull('source_id');
            })
            ->get();

        foreach ($ventes as $v) {
            $this->enregistrerVente($v);
            $ventesSync++;
        }

        // 2. Dépenses non encore comptabilisées
        $depenses = Expense::where('boutique_id', $boutiqueId)
            ->whereNotIn('id', function($q) {
                $q->select('source_id')
                  ->from('ecritures_comptables')
                  ->where('source_type', 'Expense')
                  ->whereNotNull('source_id');
            })
            ->get();

        foreach ($depenses as $d) {
            $this->enregistrerDepense($d);
            $depensesSync++;
        }

        // 3. Paiements de crédits non encore comptabilisés
        $paiements = PaiementCredit::where('boutique_id', $boutiqueId)
            ->whereNotIn('id', function($q) {
                $q->select('source_id')
                  ->from('ecritures_comptables')
                  ->where('source_type', 'PaiementCredit')
                  ->whereNotNull('source_id');
            })
            ->get();

        foreach ($paiements as $p) {
            $this->enregistrerPaiementCredit($p);
            $paiementsSync++;
        }

        return [
            'ventes_synchronisees' => $ventesSync,
            'depenses_synchronisees' => $depensesSync,
            'paiements_credits_synchronises' => $paiementsSync,
            'total_synchronise' => ($ventesSync + $depensesSync + $paiementsSync),
            'message' => 'Toutes les opérations ont été synchronisées avec succès dans les journaux SYSCOHADA.'
        ];
    }

    /**
     * Générer la Balance Générale SYSCOHADA (6 colonnes : Cumul Débit, Cumul Crédit, Solde Débiteur, Solde Créditeur)
     * Isolation multi-tenant stricte : seuls les mouvements de la boutique spécifiée sont agrégés.
     */
    public function getBalanceGenerale(int $boutiqueId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        // 1. Agréger les mouvements strictement pour cette boutique et dans la période
        $mouvementsQuery = DB::table('lignes_ecritures as l')
            ->join('ecritures_comptables as e', 'e.id', '=', 'l.ecriture_id')
            ->where('e.boutique_id', '=', $boutiqueId)
            ->where('e.statut', '=', 'validee');

        if ($dateDebut) {
            $mouvementsQuery->where('e.date_ecriture', '>=', $dateDebut);
        }
        if ($dateFin) {
            $mouvementsQuery->where('e.date_ecriture', '<=', $dateFin);
        }

        $mouvements = $mouvementsQuery
            ->select(
                'l.compte_id',
                DB::raw('COALESCE(SUM(l.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(l.credit), 0) as total_credit')
            )
            ->groupBy('l.compte_id')
            ->get()
            ->keyBy('compte_id');

        // 2. Charger les comptes autorisés (système ou créés par la boutique)
        $comptes = CompteComptable::where(function ($q) use ($boutiqueId) {
                $q->whereNull('boutique_id')
                  ->orWhere('boutique_id', $boutiqueId);
            })
            ->where('is_active', true)
            ->orderBy('numero', 'asc')
            ->get();

        $balance = [];
        $sumDebit = 0;
        $sumCredit = 0;
        $sumSoldeDebiteur = 0;
        $sumSoldeCrediteur = 0;

        foreach ($comptes as $c) {
            $mvt = $mouvements->get($c->id);
            $debit = $mvt ? (float) $mvt->total_debit : 0;
            $credit = $mvt ? (float) $mvt->total_credit : 0;

            if ($debit > 0 || $credit > 0) {
                $diff = $debit - $credit;
                $soldeDebiteur = $diff > 0 ? $diff : 0;
                $soldeCrediteur = $diff < 0 ? abs($diff) : 0;

                $balance[] = [
                    'compte_id' => $c->id,
                    'numero' => $c->numero,
                    'libelle' => $c->libelle,
                    'classe' => $c->classe,
                    'type' => $c->type,
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                    'solde_debiteur' => $soldeDebiteur,
                    'solde_crediteur' => $soldeCrediteur,
                ];

                $sumDebit += $debit;
                $sumCredit += $credit;
                $sumSoldeDebiteur += $soldeDebiteur;
                $sumSoldeCrediteur += $soldeCrediteur;
            }
        }

        return [
            'comptes' => $balance,
            'totaux' => [
                'total_debit' => $sumDebit,
                'total_credit' => $sumCredit,
                'total_solde_debiteur' => $sumSoldeDebiteur,
                'total_solde_crediteur' => $sumSoldeCrediteur,
                'is_equilibree' => abs($sumDebit - $sumCredit) < 0.01 && abs($sumSoldeDebiteur - $sumSoldeCrediteur) < 0.01,
            ],
            'periode' => [
                'debut' => $dateDebut,
                'fin' => $dateFin,
            ]
        ];
    }

    /**
     * Générer le Grand Livre SYSCOHADA (mouvements compte par compte avec solde progressif et report à nouveau)
     */
    public function getGrandLivre(int $boutiqueId, ?string $dateDebut = null, ?string $dateFin = null, ?int $compteId = null): array
    {
        $query = LigneEcriture::with(['ecriture.journal', 'compte'])
            ->whereHas('ecriture', function ($q) use ($boutiqueId, $dateDebut, $dateFin) {
                $q->where('boutique_id', $boutiqueId)
                  ->where('statut', 'validee');
                if ($dateDebut) $q->where('date_ecriture', '>=', $dateDebut);
                if ($dateFin) $q->where('date_ecriture', '<=', $dateFin);
            });

        if ($compteId) {
            $query->where('compte_id', $compteId);
        }

        $lignes = $query->join('ecritures_comptables', 'ecritures_comptables.id', '=', 'lignes_ecritures.ecriture_id')
            ->orderBy('lignes_ecritures.compte_id')
            ->orderBy('ecritures_comptables.date_ecriture', 'asc')
            ->orderBy('ecritures_comptables.id', 'asc')
            ->select('lignes_ecritures.*')
            ->get();

        $grandLivre = [];
        $grouped = $lignes->groupBy('compte_id');

        foreach ($grouped as $cId => $mouvements) {
            $compte = $mouvements->first()->compte;

            // Calcul du Solde Initial / Report à nouveau si un filtre date_debut est appliqué
            $soldeInitial = 0;
            if ($dateDebut) {
                $mvtAvant = DB::table('lignes_ecritures as l')
                    ->join('ecritures_comptables as e', 'e.id', '=', 'l.ecriture_id')
                    ->where('e.boutique_id', '=', $boutiqueId)
                    ->where('e.statut', '=', 'validee')
                    ->where('e.date_ecriture', '<', $dateDebut)
                    ->where('l.compte_id', '=', $cId)
                    ->select(
                        DB::raw('COALESCE(SUM(l.debit), 0) as total_deb'),
                        DB::raw('COALESCE(SUM(l.credit), 0) as total_cred')
                    )
                    ->first();

                if ($mvtAvant) {
                    $soldeInitial = (float) ($mvtAvant->total_deb - $mvtAvant->total_cred);
                }
            }

            $solde = $soldeInitial;
            $mouvementsArray = [];

            if ($soldeInitial != 0) {
                $mouvementsArray[] = [
                    'id' => 0,
                    'date' => $dateDebut,
                    'numero_piece' => 'RAN',
                    'journal' => 'RAN',
                    'libelle' => 'Report à nouveau (Solde initial)',
                    'debit' => $soldeInitial > 0 ? $soldeInitial : 0,
                    'credit' => $soldeInitial < 0 ? abs($soldeInitial) : 0,
                    'solde_progressif' => $solde,
                ];
            }

            foreach ($mouvements as $m) {
                $debit = (float) $m->debit;
                $credit = (float) $m->credit;
                $solde += ($debit - $credit);

                $dateEcr = $m->ecriture->date_ecriture ? Carbon::parse($m->ecriture->date_ecriture)->format('Y-m-d') : date('Y-m-d');

                $mouvementsArray[] = [
                    'id' => $m->id,
                    'date' => $dateEcr,
                    'numero_piece' => $m->ecriture->numero_piece,
                    'journal' => $m->ecriture->journal->code ?? 'OD',
                    'libelle' => $m->libelle ?: $m->ecriture->libelle,
                    'debit' => $debit,
                    'credit' => $credit,
                    'solde_progressif' => $solde,
                ];
            }

            $grandLivre[] = [
                'compte' => [
                    'id' => $compte->id,
                    'numero' => $compte->numero,
                    'libelle' => $compte->libelle,
                    'classe' => $compte->classe,
                ],
                'solde_initial' => $soldeInitial,
                'total_debit' => $mouvements->sum('debit') + ($soldeInitial > 0 ? $soldeInitial : 0),
                'total_credit' => $mouvements->sum('credit') + ($soldeInitial < 0 ? abs($soldeInitial) : 0),
                'solde_final' => $solde,
                'mouvements' => $mouvementsArray,
            ];
        }

        return $grandLivre;
    }

    /**
     * Générer le Compte de Résultat SYSCOHADA (Produits - Charges = Résultat Net)
     */
    public function getCompteResultat(int $boutiqueId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $balance = $this->getBalanceGenerale($boutiqueId, $dateDebut, $dateFin);

        $charges = [];
        $produits = [];
        $totalCharges = 0;
        $totalProduits = 0;

        foreach ($balance['comptes'] as $c) {
            if ($c['classe'] == 6) { // Charges
                $montant = $c['solde_debiteur'] - $c['solde_crediteur'];
                if ($montant > 0) {
                    $charges[] = [
                        'numero' => $c['numero'],
                        'libelle' => $c['libelle'],
                        'montant' => $montant,
                    ];
                    $totalCharges += $montant;
                }
            } elseif ($c['classe'] == 7) { // Produits
                $montant = $c['solde_crediteur'] - $c['solde_debiteur'];
                if ($montant > 0) {
                    $produits[] = [
                        'numero' => $c['numero'],
                        'libelle' => $c['libelle'],
                        'montant' => $montant,
                    ];
                    $totalProduits += $montant;
                }
            }
        }

        $resultatNet = $totalProduits - $totalCharges;

        return [
            'periode' => [
                'debut' => $dateDebut,
                'fin' => $dateFin,
            ],
            'charges' => $charges,
            'produits' => $produits,
            'total_charges' => $totalCharges,
            'total_produits' => $totalProduits,
            'resultat_net' => $resultatNet,
            'statut_resultat' => $resultatNet >= 0 ? 'Bénéfice' : 'Perte',
        ];
    }

    /**
     * Récupérer la liste des écritures pour le journal général avec filtres
     */
    public function getJournalEcritures(int $boutiqueId, ?int $journalId = null, ?string $dateDebut = null, ?string $dateFin = null, ?string $search = null)
    {
        $query = EcritureComptable::with(['journal', 'lignes.compte', 'user'])
            ->where('boutique_id', $boutiqueId);

        if ($journalId) {
            $query->where('journal_id', $journalId);
        }

        if ($dateDebut) {
            $query->where('date_ecriture', '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->where('date_ecriture', '<=', $dateFin);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('numero_piece', 'like', "%{$search}%")
                  ->orWhere('libelle', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('date_ecriture', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Générer le Bilan Comptable SYSCOHADA (Actif / Passif)
     */
    public function getBilan(int $boutiqueId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $balance = $this->getBalanceGenerale($boutiqueId, $dateDebut, $dateFin);
        $compteResultat = $this->getCompteResultat($boutiqueId, $dateDebut, $dateFin);

        $actifImmobilise = [];
        $actifCirculant = [];
        $tresorerieActif = [];
        $capitauxPropres = [];
        $passifCirculant = [];
        $tresoreriePassif = [];

        $totalActif = 0;
        $totalPassif = 0;

        foreach ($balance['comptes'] as $c) {
            $classe = (int) $c['classe'];
            $soldeDeb = $c['solde_debiteur'];
            $soldeCred = $c['solde_crediteur'];
            $soldeNet = $soldeDeb - $soldeCred;

            if ($classe === 2) { // Immobilisations (brutes ou amortissements en déduction)
                if ($soldeNet != 0) {
                    $actifImmobilise[] = ['numero' => $c['numero'], 'libelle' => $c['libelle'], 'net' => $soldeNet];
                    $totalActif += $soldeNet;
                }
            } elseif ($classe === 3) { // Stocks
                if ($soldeNet > 0) {
                    $actifCirculant[] = ['numero' => $c['numero'], 'libelle' => $c['libelle'], 'net' => $soldeNet];
                    $totalActif += $soldeNet;
                }
            } elseif ($classe === 4) { // Tiers
                if ($soldeNet > 0) {
                    $actifCirculant[] = ['numero' => $c['numero'], 'libelle' => $c['libelle'], 'net' => $soldeNet];
                    $totalActif += $soldeNet;
                } elseif ($soldeNet < 0) {
                    $passifCirculant[] = ['numero' => $c['numero'], 'libelle' => $c['libelle'], 'net' => abs($soldeNet)];
                    $totalPassif += abs($soldeNet);
                }
            } elseif ($classe === 5) { // Trésorerie
                if ($soldeNet > 0) {
                    $tresorerieActif[] = ['numero' => $c['numero'], 'libelle' => $c['libelle'], 'net' => $soldeNet];
                    $totalActif += $soldeNet;
                } elseif ($soldeNet < 0) {
                    $tresoreriePassif[] = ['numero' => $c['numero'], 'libelle' => $c['libelle'], 'net' => abs($soldeNet)];
                    $totalPassif += abs($soldeNet);
                }
            } elseif ($classe === 1) { // Capitaux propres
                $soldeCap = $soldeCred - $soldeDeb;
                if ($soldeCap != 0) {
                    $capitauxPropres[] = ['numero' => $c['numero'], 'libelle' => $c['libelle'], 'net' => $soldeCap];
                    $totalPassif += $soldeCap;
                }
            }
        }

        // Ajouter le résultat net dans les capitaux propres
        $resultatNet = $compteResultat['resultat_net'];
        $capitauxPropres[] = [
            'numero' => '13',
            'libelle' => 'Résultat Net de l\'exercice (' . $compteResultat['statut_resultat'] . ')',
            'net' => $resultatNet,
        ];
        $totalPassif += $resultatNet;

        return [
            'periode' => ['debut' => $dateDebut, 'fin' => $dateFin],
            'actif' => [
                'immobilise' => $actifImmobilise,
                'circulant' => $actifCirculant,
                'tresorerie' => $tresorerieActif,
                'total' => $totalActif,
            ],
            'passif' => [
                'capitaux' => $capitauxPropres,
                'circulant' => $passifCirculant,
                'tresorerie' => $tresoreriePassif,
                'total' => $totalPassif,
            ],
            'equilibre' => abs(round($totalActif, 2) - round($totalPassif, 2)) < 0.05,
        ];
    }
}