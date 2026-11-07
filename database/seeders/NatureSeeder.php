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
                'config' => ['units' => ['unité', 'paquet', 'kg', 'mètre']]
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
                'config' => ['units' => ['unité', 'r rame', 'paquet']]
            ],
            [
                'name' => 'Accessoires',
                'slug' => 'accessoire',
                'description' => 'Bijoux, sacs et divers accessoires',
                'config' => ['units' => ['unité', 'paire']]
            ],
            [
                'name' => 'Carburant & Énergie',
                'slug' => 'carburant',
                'description' => 'Stations-service, vente de carburant et gaz',
                'config' => ['units' => ['litre', 'barrique', 'm3']]
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
