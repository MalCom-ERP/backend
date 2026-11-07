<?php

namespace App\Contracts;

use App\Models\Boutique;

interface ShopNatureStrategy
{
    /**
     * Get the available units of measure for this nature.
     */
    public function getAvailableUnits(): array;

    /**
     * Get the slug of the nature.
     */
    public function getNatureSlug(): string;

    /**
     * Custom logic for product validation if needed.
     */
    public function validateProductData(array $data): array;
    
    /**
     * Get custom layout or configuration for the frontend if needed via API.
     */
    public function getFrontendConfig(): array;
}
