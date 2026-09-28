<?php

namespace App\Strategies;

class CarburantStrategy extends BaseNatureStrategy
{
    public function getFrontendConfig(): array
    {
        $config = parent::getFrontendConfig();
        $config['features'][] = 'fluid_volume_management';
        return $config;
    }
}
