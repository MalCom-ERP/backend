<?php

namespace App\Strategies;

class PiecesAutoStrategy extends BaseNatureStrategy
{
    public function getFrontendConfig(): array
    {
        $config = parent::getFrontendConfig();
        $config['features'][] = 'oem_catalog';
        $config['features'][] = 'vehicle_compatibility';
        $config['features'][] = 'garage_labor';
        return $config;
    }
}
