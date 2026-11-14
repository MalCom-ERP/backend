<?php

namespace App\Strategies;

class BoulangerieStrategy extends BaseNatureStrategy
{
    public function getFrontendConfig(): array
    {
        $config = parent::getFrontendConfig();
        $config['features'][] = 'bakery_batches';
        $config['features'][] = 'fast_pos_presets';
        return $config;
    }
}
