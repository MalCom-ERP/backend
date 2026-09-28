<?php

namespace App\Strategies;

use App\Contracts\ShopNatureStrategy;
use App\Models\Nature;

abstract class BaseNatureStrategy implements ShopNatureStrategy
{
    protected $nature;

    public function __construct(Nature $nature)
    {
        $this->nature = $nature;
    }

    public function getAvailableUnits(): array
    {
        return $this->nature->config['units'] ?? ['unité'];
    }

    public function getNatureSlug(): string
    {
        return $this->nature->slug;
    }

    public function validateProductData(array $data): array
    {
        // Default validation logic (empty array means no errors)
        return [];
    }

    public function getFrontendConfig(): array
    {
        return [
            'slug' => $this->nature->slug,
            'name' => $this->nature->name,
            'units' => $this->getAvailableUnits(),
            'features' => [] // Custom features can be added here
        ];
    }
}
