<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $natures = [
            [
                'name' => 'Défaut',
                'slug' => 'default',
                'description' => 'Boutique standard avec vente à l\'unité',
                'config' => ['units' => ['unité', 'lot']]
            ],
            [
                'name' => 'Vêtement & Textile',
                'slug' => 'vetement',
                'description' => 'Boutique de vêtements, tissus et accessoires de mode',
                'config' => ['units' => ['pièce', 'mètre', 'yard']]
            ],
            [
                'name' => 'Alimentation Générale',
                'slug' => 'alimentation',
                'description' => 'Supermarchés, supérettes et boutiques d\'alimentation',
                'config' => ['units' => ['unité', 'kg', 'carton', 'sac', 'litre']]
            ],
            [
                'name' => 'Quincaillerie',
                'slug' => 'quincaillerie',
                'description' => 'Matériaux de construction et outils',
                'config' => ['units' => ['unité', 'paquet', 'kg', 'mètre', 'tonne', 'barre', 'sac', 'rouleau']]
            ],
            [
                'name' => 'Téléphonie & Électronique',
                'slug' => 'telephonie',
                'description' => 'Vente de téléphones et gadgets électroniques',
                'config' => ['units' => ['unité', 'pièce']]
            ],
            [
                'name' => 'Librairie & Papeterie',
                'slug' => 'librairie',
                'description' => 'Livres, fournitures scolaires et bureau',
                'config' => ['units' => ['unité', 'rame', 'paquet', 'carton']]
            ],
            [
                'name' => 'Accessoires',
                'slug' => 'accessoire',
                'description' => 'Bijoux, sacs et divers accessoires',
                'config' => ['units' => ['unité', 'paire', 'coffret', 'lot', 'douzaine']]
            ],
            [
                'name' => 'Carburant & Énergie',
                'slug' => 'carburant',
                'description' => 'Stations-service, vente de carburant et gaz',
                'config' => ['units' => ['litre', 'barrique', 'm3']]
            ],
            [
                'name' => 'Dépôt de Boissons & Gaz',
                'slug' => 'depot_boisson',
                'description' => 'Distribution de boissons, casiers consignés, bouteilles de gaz et vente demi-gros/gros',
                'config' => [
                    'units' => ['casier', 'bouteille', 'pack', 'bouteille gaz', 'palette', 'carton'],
                    'unit_label' => 'Casiers / Bouteilles'
                ]
            ],
            [
                'name' => 'Pièces Auto/Moto & Garage',
                'slug' => 'pieces_auto',
                'description' => 'Pièces détachées auto, moto, poids lourds, lubrifiants et atelier mécanique',
                'config' => [
                    'units' => ['pièce', 'kit', 'jeu', 'paire', 'bidon', 'litre'],
                    'unit_label' => 'Pièces / Kits'
                ]
            ],
            [
                'name' => 'Boulangerie & Pâtisserie',
                'slug' => 'boulangerie',
                'description' => 'Fournil, boulangerie, viennoiserie, pâtisserie et distribution livreurs',
                'config' => [
                    'units' => ['pièce', 'baguette', 'plateau', 'boîte', 'part', 'kg', 'sac'],
                    'unit_label' => 'Pièces / Fournées'
                ]
            ],
            [
                'name' => 'Ferme Avicole (Chair & Pondeuses)',
                'slug' => 'aviculture',
                'description' => 'Élevage et vente de poulets de chair, pondeuses, œufs en alvéoles, poussins et aliments volaille',
                'config' => [
                    'units' => ['sujet', 'alvéole (30)', 'carton (12 alv.)', 'kg', 'sac 50kg', 'plateau'],
                    'unit_label' => 'Sujets / Alvéoles'
                ]
            ],
        ];

        foreach ($natures as $nature) {
            \App\Models\Nature::updateOrCreate(
                ['slug' => $nature['slug']],
                $nature
            );
        }
    }
}
