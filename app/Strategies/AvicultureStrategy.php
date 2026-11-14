<?php

namespace App\Strategies;

class AvicultureStrategy extends BaseNatureStrategy
{
    public function getFrontendConfig(): array
    {
        $config = parent::getFrontendConfig();
        $config['features'][] = 'poultry_flock_tracking';
        $config['features'][] = 'egg_tray_management';
        $config['features'][] = 'live_weight_sales';
        return $config;
    }
}
