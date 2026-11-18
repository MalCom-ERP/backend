<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache serveur versionné par boutique (compatible file, sqlite/database, array, redis).
 */
class BoutiqueCacheService
{
    public static function version(?int $boutiqueId, string $domain): int
    {
        $bId = (int) ($boutiqueId ?: 0);
        $vKey = "mc_v:{$bId}:{$domain}";
        $val = Cache::get($vKey);
        if (!$val) {
            $val = 1;
            Cache::forever($vKey, $val);
        }
        return (int) $val;
    }

    public static function remember(?int $boutiqueId, string $domain, string $key, int $ttlSeconds, callable $callback)
    {
        $bId = (int) ($boutiqueId ?: 0);
        $version = self::version($bId, $domain);
        $fullKey = "mc_data:{$bId}:{$domain}:v{$version}:{$key}";

        return Cache::remember($fullKey, $ttlSeconds, $callback);
    }

    public static function bump(?int $boutiqueId, string|array $domains): void
    {
        $domainList = is_array($domains) ? $domains : [$domains];
        $targets = array_unique([(int) ($boutiqueId ?: 0), 0]);

        foreach ($targets as $bId) {
            foreach ($domainList as $domain) {
                $vKey = "mc_v:{$bId}:{$domain}";
                $next = ((int) Cache::get($vKey, 1)) + 1;
                Cache::forever($vKey, $next);
            }
        }
    }
}
