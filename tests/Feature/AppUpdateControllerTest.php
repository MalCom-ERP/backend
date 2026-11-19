<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppUpdateControllerTest extends TestCase
{
    private string $downloadsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->downloadsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'malcom-updates-' . uniqid('', true);
        mkdir($this->downloadsDir, 0777, true);

        config()->set('app.downloads_dir', $this->downloadsDir);
        config()->set('app.downloads_public_url', 'https://malcom-lgc.com/downloads');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->downloadsDir)) {
            $entries = @scandir($this->downloadsDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($this->downloadsDir . DIRECTORY_SEPARATOR . $entry);
            }
            @rmdir($this->downloadsDir);
        }

        parent::tearDown();
    }

    public function test_latest_selects_highest_semver_and_marks_missing_platforms_unavailable(): void
    {
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.4.0-Windows-Setup.exe', '4.4.0');
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.5.0-Windows-Setup.exe', '4.5.0');
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.10.0-Windows-Setup.exe', '4.10.0');
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.10.0-macOS-AppleSilicon.dmg', 'arm64');
        file_put_contents($this->downloadsDir . '/MalCom-Cloud-4.5.0-Windows-Setup.exe', 'cloud');

        $response = $this->getJson('/api/app-updates/latest');

        $response->assertStatus(200);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $response->assertJson([
            'downloads_dir_found' => true,
            'latest_version' => '4.10.0',
            'editions' => [
                'pro' => [
                    'latest_version' => '4.10.0',
                    'platforms' => [
                        'windows' => [
                            'available' => true,
                            'version' => '4.10.0',
                            'filename' => 'MalCom-Pro-4.10.0-Windows-Setup.exe',
                            'extension' => 'exe',
                            'relative_url' => '/downloads/MalCom-Pro-4.10.0-Windows-Setup.exe',
                            'direct_url' => 'https://malcom-lgc.com/downloads/MalCom-Pro-4.10.0-Windows-Setup.exe',
                        ],
                        'macos-arm64' => [
                            'available' => true,
                            'version' => '4.10.0',
                            'filename' => 'MalCom-Pro-4.10.0-macOS-AppleSilicon.dmg',
                            'extension' => 'dmg',
                        ],
                        'macos-intel' => [
                            'available' => false,
                            'version' => null,
                            'filename' => null,
                        ],
                    ],
                ],
                'cloud' => [
                    'latest_version' => '4.5.0',
                    'platforms' => [
                        'windows' => [
                            'available' => true,
                            'version' => '4.5.0',
                            'filename' => 'MalCom-Cloud-4.5.0-Windows-Setup.exe',
                        ],
                        'macos-arm64' => [
                            'available' => false,
                        ],
                    ],
                ],
            ],
        ]);

        // Si l'ancienne version 4.4.0 est supprimée du disque, 4.10.0 reste résolue ;
        // si 4.10.0 est remplacée/supprimée, le serveur reflète immédiatement 4.5.0.
        @unlink($this->downloadsDir . '/MalCom-Pro-4.4.0-Windows-Setup.exe');
        @unlink($this->downloadsDir . '/MalCom-Pro-4.10.0-Windows-Setup.exe');

        $afterDelete = $this->getJson('/api/app-updates/latest');
        $afterDelete->assertStatus(200);
        $afterDelete->assertJsonPath('editions.pro.platforms.windows.version', '4.5.0');
        $afterDelete->assertJsonPath('editions.pro.platforms.windows.filename', 'MalCom-Pro-4.5.0-Windows-Setup.exe');
    }

    public function test_latest_download_redirects_302_without_cache_and_supports_standalone_alias(): void
    {
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.5.0-Windows-Setup.exe', 'old');
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.10.0-Windows-Setup.exe', 'new');

        $proResponse = $this->get('/api/app-updates/latest-download/pro/windows');
        $proResponse->assertStatus(302);
        $proResponse->assertRedirect('https://malcom-lgc.com/downloads/MalCom-Pro-4.10.0-Windows-Setup.exe');
        $this->assertStringContainsString('no-store', (string) $proResponse->headers->get('Cache-Control'));

        $standaloneResponse = $this->get('/api/app-updates/latest-download/standalone/windows');
        $standaloneResponse->assertStatus(302);
        $standaloneResponse->assertRedirect('https://malcom-lgc.com/downloads/MalCom-Pro-4.10.0-Windows-Setup.exe');
    }

    public function test_latest_download_streams_binary_when_stream_query_is_set(): void
    {
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.10.0-Windows-Setup.exe', 'binary-payload');

        $response = $this->get('/api/app-updates/latest-download/pro/windows?stream=1');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'MalCom-Pro-4.10.0-Windows-Setup.exe',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_latest_download_returns_404_for_missing_platform_and_400_for_invalid_params(): void
    {
        file_put_contents($this->downloadsDir . '/MalCom-Pro-4.5.0-Windows-Setup.exe', 'win');

        $missing = $this->getJson('/api/app-updates/latest-download/pro/macos-intel');
        $missing->assertStatus(404);
        $this->assertStringContainsString('no-store', (string) $missing->headers->get('Cache-Control'));

        $invalidEdition = $this->getJson('/api/app-updates/latest-download/invalid/windows');
        $invalidEdition->assertStatus(400);

        $invalidPlatform = $this->getJson('/api/app-updates/latest-download/pro/linux');
        $invalidPlatform->assertStatus(400);
    }
}
