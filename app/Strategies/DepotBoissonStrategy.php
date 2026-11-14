<?php

namespace App\Strategies;

class DepotBoissonStrategy extends BaseNatureStrategy
{
    public function getFrontendConfig(): array
    {
        $config = parent::getFrontendConfig();
        $config['features'][] = 'consigne_management';
        $config['features'][] = 'multi_tier_pricing';
        return $config;
    }
}
