<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Nature;
use App\Contracts\ShopNatureStrategy;
use App\Strategies\DefaultStrategy;
use App\Strategies\VetementStrategy;
use App\Strategies\CarburantStrategy;
use App\Strategies\DepotBoissonStrategy;
use App\Strategies\PiecesAutoStrategy;
use App\Strategies\BoulangerieStrategy;
use App\Strategies\AvicultureStrategy;
use Exception;

class NatureStrategyFactory
{
    /**
     * Resolve the appropriate strategy for a given boutique.
     */
    public static function make(Boutique $boutique): ShopNatureStrategy
    {
        $nature = $boutique->nature;

        if (!$nature) {
            // Fallback to default nature if not set
            $nature = Nature::where('slug', 'default')->first();
        }

        switch ($nature->slug) {
            case 'vetement':
                return new VetementStrategy($nature);
            case 'carburant':
                return new CarburantStrategy($nature);
            case 'depot_boisson':
                return new DepotBoissonStrategy($nature);
            case 'pieces_auto':
                return new PiecesAutoStrategy($nature);
            case 'boulangerie':
                return new BoulangerieStrategy($nature);
            case 'aviculture':
                return new AvicultureStrategy($nature);
            default:
                return new DefaultStrategy($nature);
        }
    }

    /**
     * Resolve by nature slug directly if needed.
     */
    public static function makeBySlug(string $slug): ShopNatureStrategy
    {
        $nature = Nature::where('slug', $slug)->first();
        if (!$nature) {
            throw new Exception("Nature with slug '{$slug}' not found.");
        }

        switch ($slug) {
            case 'vetement':
                return new VetementStrategy($nature);
            case 'carburant':
                return new CarburantStrategy($nature);
            case 'depot_boisson':
                return new DepotBoissonStrategy($nature);
            case 'pieces_auto':
                return new PiecesAutoStrategy($nature);
            case 'boulangerie':
                return new BoulangerieStrategy($nature);
            case 'aviculture':
                return new AvicultureStrategy($nature);
            default:
                return new DefaultStrategy($nature);
        }
    }
}
