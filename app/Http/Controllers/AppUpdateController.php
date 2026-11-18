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
            env('MALCOM_DOWNLOADS_DIR'),
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
        $edition = in_array($rawEdition, ['pro', 'standalone'], true) ? 'pro' : 'cloud';

        $platform = strtolower(trim((string) $request->query('platform', 'windows')));
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
            ], 200);
        }

        $matches = $this->scanReleasesFor($dir, $edition, $platform);

        if (empty($matches)) {
            return response()->json([
                'update_available' => false,
                'is_different' => false,
                'current_version' => $currentVersion,
                'latest_version' => $currentVersion,
                'edition' => $edition,
                'platform' => $platform,
                'downloads_dir_found' => true,
            ], 200);
        }

        usort($matches, function ($a, $b) {
            $cmp = version_compare($b['version'], $a['version']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['priority'] <=> $b['priority'];
        });

        $best = $matches[0];
        $latestVersion = $best['version'];
        $updateAvailable = version_compare($latestVersion, $currentVersion, '>');
        $isDifferent = $latestVersion !== $currentVersion;

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
            'download_url' => url('/api/app-updates/download/' . rawurlencode($best['filename'])),
            'direct_url' => 'https://malcom-lgc.com/downloads/' . rawurlencode($best['filename']),
            'downloads_dir_found' => true,
        ], 200);
    }

    /**
     * Sert l'exécutable demandé en streaming binaire depuis le dossier serveur.
     */
    public function download(string $filename)
    {
        $safeName = basename($filename);
        if (!preg_match('/^[A-Za-z0-9._-]+\.(exe|msi|dmg|zip|AppImage|deb)$/i', $safeName)) {
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

    private function scanReleasesFor(string $dir, string $edition, string $platform): array
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

            if ($parsed['edition'] !== $edition) {
                continue;
            }

            if (!$this->matchesPlatform($parsed['platform'], $platform)) {
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

    private function parseInstallerFilename(string $filename): ?array
    {
        // Format standard : MalCom-Pro-4.4.0-Windows-Setup.exe / MalCom-Cloud-4.5.0-macOS-AppleSilicon.dmg
        if (preg_match('/^MalCom-(Pro|Cloud)-v?(\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?)-(Windows-Setup\.exe|Windows-x64\.msi|macOS-AppleSilicon\.dmg|macOS-Intel\.dmg)$/i', $filename, $m)) {
            $edition = strtolower($m[1]);
            $version = $m[2];
            $suffix = strtolower($m[3]);
            $platform = match (true) {
                str_contains($suffix, 'windows-setup.exe') => 'windows-exe',
                str_contains($suffix, 'windows-x64.msi') => 'windows-msi',
                str_contains($suffix, 'applesilicon') => 'macos-arm64',
                str_contains($suffix, 'intel') => 'macos-intel',
                default => 'unknown',
            };
            $priority = $platform === 'windows-exe' ? 1 : 2;
            return compact('edition', 'version', 'platform', 'priority');
        }

        // Format générique tolérant (ex: MalCom_Cloud_4.5.0_x64.exe ou MalCom-Pro-v4.5.0-arm64.dmg)
        if (preg_match('/malcom[-_\s]*(pro|standalone|cloud).*?v?(\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?).*\.(exe|msi|dmg)$/i', $filename, $m)) {
            $rawEd = strtolower($m[1]);
            $edition = in_array($rawEd, ['pro', 'standalone'], true) ? 'pro' : 'cloud';
            $version = $m[2];
            $ext = strtolower($m[3]);
            $lower = strtolower($filename);

            if ($ext === 'exe') {
                $platform = 'windows-exe';
                $priority = 1;
            } elseif ($ext === 'msi') {
                $platform = 'windows-msi';
                $priority = 2;
            } elseif (str_contains($lower, 'arm64') || str_contains($lower, 'applesilicon') || str_contains($lower, 'aarch64')) {
                $platform = 'macos-arm64';
                $priority = 1;
            } elseif (str_contains($lower, 'intel') || str_contains($lower, 'x86_64') || str_contains($lower, 'x64')) {
                $platform = 'macos-intel';
                $priority = 1;
            } else {
                $platform = 'macos';
                $priority = 2;
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
        return false;
    }
}
