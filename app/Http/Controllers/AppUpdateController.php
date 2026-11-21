<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    /**
     * Localise le dossier serveur contenant les installateurs MalCom Desktop.
     */
    public function resolveDownloadsDirectory(): ?string
    {
        $candidates = array_filter([
            config('app.downloads_dir'),
            '/var/www/malcom/vitrine/downloads',
            public_path('downloads'),
            storage_path('app/downloads'),
        ]);

        foreach ($candidates as $dir) {
            if (is_string($dir) && is_dir($dir) && is_readable($dir)) {
                return rtrim($dir, DIRECTORY_SEPARATOR);
            }
        }

        return null;
    }

    /**
     * Vérifie si une version plus récente (ou différente) est disponible dans le dossier serveur.
     * GET /api/app-updates/check?current_version=4.4.0&edition=cloud&platform=windows
     */
    public function check(Request $request)
    {
        $currentVersion = ltrim(trim((string) $request->query('current_version', '0.0.0')), 'vV');
        if ($currentVersion === '') {
            $currentVersion = '0.0.0';
        }

        $rawEdition = strtolower(trim((string) $request->query('edition', 'cloud')));
        $edition = $this->normalizeEdition($rawEdition) ?? 'cloud';

        $rawPlatform = strtolower(trim((string) $request->query('platform', 'windows')));
        $platform = $this->normalizePlatform($rawPlatform) ?? $rawPlatform;
        $dir = $this->resolveDownloadsDirectory();

        if (!$dir) {
            return response()->json([
                'update_available' => false,
                'is_different' => false,
                'current_version' => $currentVersion,
                'latest_version' => $currentVersion,
                'edition' => $edition,
                'platform' => $platform,
                'downloads_dir_found' => false,
            ], 200)->withHeaders($this->noCacheHeaders());
        }

        $best = $this->findBestRelease($this->scanAllReleases($dir), $edition, $platform);

        if (!$best) {
            return response()->json([
                'update_available' => false,
                'is_different' => false,
                'current_version' => $currentVersion,
                'latest_version' => $currentVersion,
                'edition' => $edition,
                'platform' => $platform,
                'downloads_dir_found' => true,
            ], 200)->withHeaders($this->noCacheHeaders());
        }

        $latestVersion = $best['version'];
        $updateAvailable = version_compare($latestVersion, $currentVersion, '>');
        $isDifferent = $latestVersion !== $currentVersion;
        $encoded = rawurlencode($best['filename']);
        $publicBase = $this->publicDownloadsBaseUrl() ?? 'https://malcom-lgc.com/downloads';

        return response()->json([
            'update_available' => $updateAvailable,
            'is_different' => $isDifferent,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'edition' => $edition,
            'platform' => $platform,
            'filename' => $best['filename'],
            'file_size' => $best['size'],
            'updated_at' => date('c', $best['mtime']),
            'download_url' => url('/api/app-updates/download/' . $encoded),
            'direct_url' => $publicBase . '/' . $encoded,
            'downloads_dir_found' => true,
        ], 200)->withHeaders($this->noCacheHeaders());
    }

    /**
     * Retourne le catalogue temps réel des dernières versions disponibles sur le serveur
     * pour chaque édition (pro, cloud) et plateforme (windows, macos-arm64, macos-intel).
     * GET /api/app-updates/latest
     */
    public function latest(Request $request)
    {
        $dir = $this->resolveDownloadsDirectory();
        $allReleases = $dir ? $this->scanAllReleases($dir) : [];

        $editions = [];
        $globalLatestVersion = null;

        foreach (['pro', 'cloud'] as $edition) {
            $platformsPayload = [];
            $editionLatestVersion = null;

            foreach (['windows', 'macos-arm64', 'macos-intel', 'android'] as $platform) {
                $best = $dir ? $this->findBestRelease($allReleases, $edition, $platform) : null;
                $payload = $this->formatReleasePayload($best, $edition, $platform);
                $platformsPayload[$platform] = $payload;

                if ($payload['available'] && $payload['version'] !== null) {
                    if ($editionLatestVersion === null || version_compare($payload['version'], $editionLatestVersion, '>')) {
                        $editionLatestVersion = $payload['version'];
                    }
                    if ($globalLatestVersion === null || version_compare($payload['version'], $globalLatestVersion, '>')) {
                        $globalLatestVersion = $payload['version'];
                    }
                }
            }

            $editions[$edition] = [
                'latest_version' => $editionLatestVersion,
                'platforms' => $platformsPayload,
            ];
        }

        return response()->json([
            'downloads_dir_found' => $dir !== null,
            'latest_version' => $globalLatestVersion,
            'checked_at' => date('c'),
            'editions' => $editions,
        ], 200)->withHeaders($this->noCacheHeaders());
    }

    /**
     * Redirige (302 sans cache) ou sert directement la dernière version disponible sur le serveur
     * pour une édition et une plateforme données.
     * GET /api/app-updates/latest-download/{edition}/{platform}
     */
    public function downloadLatest(Request $request, string $edition, string $platform)
    {
        $normalizedEdition = $this->normalizeEdition(strtolower(trim($edition)));
        $normalizedPlatform = $this->normalizePlatform(strtolower(trim($platform)));

        if ($normalizedEdition === null || $normalizedPlatform === null) {
            return response()->json([
                'message' => 'Édition ou plateforme invalide',
            ], 400)->withHeaders($this->noCacheHeaders());
        }

        $dir = $this->resolveDownloadsDirectory();
        if (!$dir) {
            return response()->json([
                'message' => 'Dossier des mises à jour introuvable sur le serveur',
            ], 404)->withHeaders($this->noCacheHeaders());
        }

        $best = $this->findBestRelease($this->scanAllReleases($dir), $normalizedEdition, $normalizedPlatform);
        if (!$best) {
            return response()->json([
                'message' => 'Aucun installateur disponible pour cette édition et plateforme',
            ], 404)->withHeaders($this->noCacheHeaders());
        }

        if ($request->query('stream') !== '1') {
            $publicBase = $this->publicDownloadsBaseUrl();
            if ($publicBase !== null) {
                return redirect()
                    ->away($publicBase . '/' . rawurlencode($best['filename']), 302)
                    ->withHeaders($this->noCacheHeaders());
            }
        }

        return $this->download($best['filename']);
    }

    /**
     * Sert l'exécutable demandé en streaming binaire depuis le dossier serveur.
     */
    public function download(string $filename)
    {
        $safeName = basename($filename);
        if (!preg_match('/^[A-Za-z0-9._-]+\.(exe|msi|dmg|zip|AppImage|deb|apk)$/i', $safeName)) {
            return response()->json(['message' => 'Nom de fichier invalide'], 400);
        }

        $dir = $this->resolveDownloadsDirectory();
        if (!$dir) {
            return response()->json(['message' => 'Dossier des mises à jour introuvable sur le serveur'], 404);
        }

        $fullPath = $dir . DIRECTORY_SEPARATOR . $safeName;
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            return response()->json(['message' => 'Exécutable introuvable'], 404);
        }

        return response()->download($fullPath, $safeName, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    private function noCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    private function publicDownloadsBaseUrl(): ?string
    {
        $raw = config('app.downloads_public_url', 'https://malcom-lgc.com/downloads');
        if (!is_string($raw)) {
            return null;
        }
        $trimmed = rtrim(trim($raw), '/');
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeEdition(string $raw): ?string
    {
        return match ($raw) {
            'pro', 'standalone' => 'pro',
            'cloud' => 'cloud',
            default => null,
        };
    }

    private function normalizePlatform(string $raw): ?string
    {
        return match ($raw) {
            'windows', 'win', 'windows-x64', 'windows-exe', 'windows-msi', 'win32', 'win64' => 'windows',
            'macos-arm64', 'arm64', 'apple-silicon', 'applesilicon', 'darwin-arm64', 'mac-arm64' => 'macos-arm64',
            'macos-intel', 'intel', 'x64', 'x86_64', 'darwin-x64', 'mac-intel' => 'macos-intel',
            'macos', 'darwin', 'mac' => 'macos',
            'android', 'apk', 'tablet', 'tablette' => 'android',
            default => null,
        };
    }

    private function scanAllReleases(string $dir): array
    {
        $entries = @scandir($dir);
        if (!is_array($entries)) {
            return [];
        }

        $results = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($fullPath)) {
                continue;
            }

            $parsed = $this->parseInstallerFilename($entry);
            if (!$parsed) {
                continue;
            }

            $results[] = [
                'filename' => $entry,
                'version' => $parsed['version'],
                'edition' => $parsed['edition'],
                'platform' => $parsed['platform'],
                'priority' => $parsed['priority'],
                'size' => @filesize($fullPath) ?: 0,
                'mtime' => @filemtime($fullPath) ?: time(),
            ];
        }

        return $results;
    }

    private function findBestRelease(array $allReleases, string $edition, string $platform): ?array
    {
        $matches = array_values(array_filter(
            $allReleases,
            fn (array $release): bool => $release['edition'] === $edition
                && $this->matchesPlatform($release['platform'], $platform)
        ));

        if (empty($matches)) {
            return null;
        }

        usort($matches, function (array $a, array $b): int {
            $cmp = version_compare($b['version'], $a['version']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $prioCmp = $a['priority'] <=> $b['priority'];
            if ($prioCmp !== 0) {
                return $prioCmp;
            }
            return $b['mtime'] <=> $a['mtime'];
        });

        return $matches[0];
    }

    private function formatReleasePayload(?array $best, string $edition, string $platform): array
    {
        $latestRedirectUrl = url('/api/app-updates/latest-download/' . $edition . '/' . $platform);

        if ($best === null) {
            return [
                'available' => false,
                'version' => null,
                'edition' => $edition,
                'platform' => $platform,
                'filename' => null,
                'extension' => null,
                'file_size' => 0,
                'updated_at' => null,
                'relative_url' => null,
                'direct_url' => null,
                'download_url' => null,
                'latest_redirect_url' => $latestRedirectUrl,
            ];
        }

        $encoded = rawurlencode($best['filename']);
        $publicBase = $this->publicDownloadsBaseUrl() ?? 'https://malcom-lgc.com/downloads';

        return [
            'available' => true,
            'version' => $best['version'],
            'edition' => $edition,
            'platform' => $platform,
            'filename' => $best['filename'],
            'extension' => strtolower((string) pathinfo($best['filename'], PATHINFO_EXTENSION)),
            'file_size' => $best['size'],
            'updated_at' => date('c', $best['mtime']),
            'relative_url' => '/downloads/' . $encoded,
            'direct_url' => $publicBase . '/' . $encoded,
            'download_url' => url('/api/app-updates/download/' . $encoded),
            'latest_redirect_url' => $latestRedirectUrl,
        ];
    }

    private function parseInstallerFilename(string $filename): ?array
    {
        // Format standard : MalCom-Pro-4.4.0-Windows-Setup.exe / MalCom-Cloud-4.5.0-macOS-AppleSilicon.dmg / MalCom-Cloud-4.5.0-Android.apk
        if (preg_match('/^MalCom-(Pro|Cloud)-v?(\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?)-(Windows-Setup\.exe|Windows-x64\.msi|macOS-AppleSilicon\.dmg|macOS-Intel\.dmg|Android\.apk)$/i', $filename, $m)) {
            $edition = strtolower($m[1]);
            $version = $m[2];
            $suffix = strtolower($m[3]);
            $platform = match (true) {
                str_contains($suffix, 'windows-setup.exe') => 'windows-exe',
                str_contains($suffix, 'windows-x64.msi') => 'windows-msi',
                str_contains($suffix, 'applesilicon') => 'macos-arm64',
                str_contains($suffix, 'intel') => 'macos-intel',
                str_contains($suffix, 'android.apk') => 'android',
                default => 'unknown',
            };
            $priority = in_array($platform, ['windows-exe', 'macos-arm64', 'macos-intel', 'android'], true) ? 1 : 2;
            return compact('edition', 'version', 'platform', 'priority');
        }

        // Format générique tolérant (ex: MalCom_Cloud_4.5.0_x64.exe ou MalCom-Pro-v4.5.0-arm64.dmg ou MalCom-Cloud-4.5.0.apk)
        if (preg_match('/malcom[-_\s]*(pro|standalone|cloud).*?v?(\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?).*\.(exe|msi|dmg|apk)$/i', $filename, $m)) {
            $rawEd = strtolower($m[1]);
            $edition = in_array($rawEd, ['pro', 'standalone'], true) ? 'pro' : 'cloud';
            $version = $m[2];
            $ext = strtolower($m[3]);
            $lower = strtolower($filename);

            if ($ext === 'apk') {
                $platform = 'android';
                $priority = 2;
            } elseif ($ext === 'exe') {
                $platform = 'windows-exe';
                $priority = 2;
            } elseif ($ext === 'msi') {
                $platform = 'windows-msi';
                $priority = 3;
            } elseif (str_contains($lower, 'arm64') || str_contains($lower, 'applesilicon') || str_contains($lower, 'aarch64')) {
                $platform = 'macos-arm64';
                $priority = 2;
            } elseif (str_contains($lower, 'intel') || str_contains($lower, 'x86_64') || str_contains($lower, 'x64')) {
                $platform = 'macos-intel';
                $priority = 2;
            } else {
                $platform = 'macos';
                $priority = 3;
            }

            return compact('edition', 'version', 'platform', 'priority');
        }

        return null;
    }

    private function matchesPlatform(string $filePlatform, string $requestedPlatform): bool
    {
        if (str_starts_with($requestedPlatform, 'windows')) {
            return in_array($filePlatform, ['windows-exe', 'windows-msi'], true);
        }
        if ($requestedPlatform === 'macos-arm64') {
            return in_array($filePlatform, ['macos-arm64', 'macos'], true);
        }
        if ($requestedPlatform === 'macos-intel') {
            return in_array($filePlatform, ['macos-intel', 'macos'], true);
        }
        if ($requestedPlatform === 'macos') {
            return str_starts_with($filePlatform, 'macos');
        }
        if ($requestedPlatform === 'android') {
            return $filePlatform === 'android';
        }
        return false;
    }
}
