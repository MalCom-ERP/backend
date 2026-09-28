<?php

namespace App\Strategies;

class VetementStrategy extends BaseNatureStrategy
{
    public function getFrontendConfig(): array
    {
        $config = parent::getFrontendConfig();
        $config['features'][] = 'fabric_measurement'; // Example feature flag for frontend
        return $config;
    }
}
