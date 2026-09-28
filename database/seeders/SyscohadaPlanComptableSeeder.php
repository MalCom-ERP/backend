<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JournalComptable;
use App\Models\CompteComptable;
use Illuminate\Support\Facades\DB;

class SyscohadaPlanComptableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Journaux Comptables Standard
        $journaux = [
            ['code' => 'VT', 'libelle' => 'Journal des Ventes'],
            ['code' => 'AC', 'libelle' => 'Journal des Achats'],
            ['code' => 'CA', 'libelle' => 'Journal de Caisse'],
            ['code' => 'BQ', 'libelle' => 'Journal de Banque'],
            ['code' => 'OD', 'libelle' => 'Opérations Diverses'],
        ];

        foreach ($journaux as $j) {
            JournalComptable::firstOrCreate(
                ['code' => $j['code'], 'boutique_id' => null],
                ['libelle' => $j['libelle']]
            );
        }

        // 2. Plan Comptable Général SYSCOHADA Révisé (Norme UEMOA / OHADA)
        $comptes = [
            // Classe 1 : Ressources Durables
            ['numero' => '101', 'libelle' => 'Capital social', 'classe' => 1, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '102', 'libelle' => 'Capital individuel / Apport personnel', 'classe' => 1, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '111', 'libelle' => 'Réserve légale', 'classe' => 1, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '121', 'libelle' => 'Report à nouveau créditeur', 'classe' => 1, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '131', 'libelle' => 'Résultat net : Bénéfice', 'classe' => 1, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '139', 'libelle' => 'Résultat net : Perte', 'classe' => 1, 'type' => 'passif', 'sens_normal' => 'debit'],
            ['numero' => '162', 'libelle' => 'Emprunts bancaires et financiers', 'classe' => 1, 'type' => 'passif', 'sens_normal' => 'credit'],

            // Classe 2 : Actif Immobilisé
            ['numero' => '211', 'libelle' => 'Terrains', 'classe' => 2, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '213', 'libelle' => 'Bâtiments et locaux commerciaux', 'classe' => 2, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '241', 'libelle' => 'Matériel et outillage d\'exploitation', 'classe' => 2, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '244', 'libelle' => 'Matériel de transport', 'classe' => 2, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '245', 'libelle' => 'Matériel de bureau et informatique', 'classe' => 2, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '284', 'libelle' => 'Amortissements du matériel', 'classe' => 2, 'type' => 'actif', 'sens_normal' => 'credit'],

            // Classe 3 : Stocks
            ['numero' => '311', 'libelle' => 'Marchandises (Stock général)', 'classe' => 3, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '391', 'libelle' => 'Dépréciations des stocks de marchandises', 'classe' => 3, 'type' => 'actif', 'sens_normal' => 'credit'],

            // Classe 4 : Comptes de Tiers
            ['numero' => '401', 'libelle' => 'Fournisseurs (Dettes en compte)', 'classe' => 4, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '4011', 'libelle' => 'Fournisseurs de marchandises', 'classe' => 4, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '4012', 'libelle' => 'Fournisseurs de prestations & services', 'classe' => 4, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '411', 'libelle' => 'Clients (Créances ventes à crédit)', 'classe' => 4, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '4112', 'libelle' => 'Clients douteux ou litigieux', 'classe' => 4, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '422', 'libelle' => 'Personnel, rémunérations et salaires dus', 'classe' => 4, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '431', 'libelle' => 'Sécurité sociale & cotisations (INPS / CNSS)', 'classe' => 4, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '443', 'libelle' => 'État, TVA facturée sur ventes', 'classe' => 4, 'type' => 'passif', 'sens_normal' => 'credit'],
            ['numero' => '444', 'libelle' => 'État, TVA déductible sur achats', 'classe' => 4, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '445', 'libelle' => 'État, impôts, taxes et droits fiscaux', 'classe' => 4, 'type' => 'passif', 'sens_normal' => 'credit'],

            // Classe 5 : Trésorerie
            ['numero' => '521', 'libelle' => 'Banque (Compte courant principal)', 'classe' => 5, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '531', 'libelle' => 'Chèques et effets à encaisser', 'classe' => 5, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '571', 'libelle' => 'Caisse principale boutique', 'classe' => 5, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '572', 'libelle' => 'Caisse secondaire / Menue caisse', 'classe' => 5, 'type' => 'actif', 'sens_normal' => 'debit'],
            ['numero' => '581', 'libelle' => 'Virements internes de fonds', 'classe' => 5, 'type' => 'actif', 'sens_normal' => 'debit'],

            // Classe 6 : Charges des activités ordinaires
            ['numero' => '601', 'libelle' => 'Achats de marchandises', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '603', 'libelle' => 'Variation des stocks de marchandises', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '605', 'libelle' => 'Électricité, eau, carburant d\'exploitation', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '6052', 'libelle' => 'Fournitures de bureau et consommables', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '611', 'libelle' => 'Transports sur achats', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '612', 'libelle' => 'Transports sur ventes / Livraisons', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '613', 'libelle' => 'Transports et déplacements du personnel', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '622', 'libelle' => 'Locations et loyers de boutique', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '624', 'libelle' => 'Entretien, réparations et maintenance', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '625', 'libelle' => 'Primes d\'assurance', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '627', 'libelle' => 'Publicité, marketing et promotions', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '628', 'libelle' => 'Téléphone, Internet et communications', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '631', 'libelle' => 'Frais bancaires et commissions', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '641', 'libelle' => 'Impôts, taxes et patentes', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '651', 'libelle' => 'Pertes sur créances irrécouvrables', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '658', 'libelle' => 'Autres charges diverses d\'exploitation', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '661', 'libelle' => 'Salaires et rémunérations du personnel', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '664', 'libelle' => 'Charges sociales et patronales', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '681', 'libelle' => 'Dotations aux amortissements d\'exploitation', 'classe' => 6, 'type' => 'charge', 'sens_normal' => 'debit'],

            // Classe 7 : Produits des activités ordinaires
            ['numero' => '701', 'libelle' => 'Ventes de marchandises', 'classe' => 7, 'type' => 'produit', 'sens_normal' => 'credit'],
            ['numero' => '706', 'libelle' => 'Prestations de services vendues', 'classe' => 7, 'type' => 'produit', 'sens_normal' => 'credit'],
            ['numero' => '707', 'libelle' => 'Produits accessoires et emballages', 'classe' => 7, 'type' => 'produit', 'sens_normal' => 'credit'],
            ['numero' => '711', 'libelle' => 'Subventions et aides d\'exploitation', 'classe' => 7, 'type' => 'produit', 'sens_normal' => 'credit'],
            ['numero' => '758', 'libelle' => 'Autres produits d\'exploitation courante', 'classe' => 7, 'type' => 'produit', 'sens_normal' => 'credit'],
            ['numero' => '771', 'libelle' => 'Intérêts et gains financiers', 'classe' => 7, 'type' => 'produit', 'sens_normal' => 'credit'],

            // Classe 8 : Hors Activités Ordinaires (HAO)
            ['numero' => '811', 'libelle' => 'Charges exceptionnelles et pertes HAO', 'classe' => 8, 'type' => 'charge', 'sens_normal' => 'debit'],
            ['numero' => '821', 'libelle' => 'Produits exceptionnels et gains HAO', 'classe' => 8, 'type' => 'produit', 'sens_normal' => 'credit'],
        ];

        foreach ($comptes as $compte) {
            CompteComptable::updateOrCreate(
                ['numero' => $compte['numero'], 'boutique_id' => null],
                [
                    'libelle' => $compte['libelle'],
                    'classe' => $compte['classe'],
                    'type' => $compte['type'],
                    'sens_normal' => $compte['sens_normal'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
