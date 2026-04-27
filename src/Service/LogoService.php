<?php

namespace Resofire\Picks\Service;

use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class LogoService
{
    protected const ESPN_LOGO_URL    = 'https://a.espncdn.com/i/teamlogos/ncaa/500/{espn_id}.png';
    protected const ESPN_DARK_URL    = 'https://a.espncdn.com/i/teamlogos/ncaa/500-dark/{espn_id}.png';
    protected const LOGO_DIRECTORY   = 'picks/logos';
    protected const WEBP_QUALITY     = 85;

    private Client $client;

    public function __construct(
        protected ImageManager $imageManager,
        protected Paths $paths,
        protected SettingsRepositoryInterface $settings
    ) {
        $this->client = new Client(['timeout' => 15]);
    }

    /**
     * Download both standard and dark logos for a team from the ESPN CDN,
     * convert each to WebP, and save them under public/assets/picks/logos/.
     *
     * Returns an array with keys 'logo_path' and 'logo_dark_path'.
     * Either value may be null if the download or conversion failed.
     */
    public function downloadAndStore(int $espnId, string $slug): array
    {
        $this->ensureDirectoryExists();

        return [
            'logo_path'      => $this->processLogo(
                $this->buildUrl(self::ESPN_LOGO_URL, $espnId),
                $slug,
                ''
            ),
            'logo_dark_path' => $this->processLogo(
                $this->buildUrl(self::ESPN_DARK_URL, $espnId),
                $slug,
                '-dark'
            ),
        ];
    }

    /**
     * Process a single logo: download, convert to WebP, save.
     * Returns the public-relative path on success, null on failure.
     */
    private function processLogo(string $url, string $slug, string $suffix): ?string
    {
        $imageData = $this->download($url);

        if ($imageData === null) {
            return null;
        }

        return $this->convertAndSave($imageData, $slug, $suffix);
    }

    /**
     * Download raw image bytes from a URL.
     * Returns null on any failure (404, network error, etc).
     */
    private function download(string $url): ?string
    {
        try {
            $response = $this->client->get($url, [
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $body = (string) $response->getBody();

            if (empty($body)) {
                return null;
            }

            return $body;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Convert raw image data to WebP and save to disk.
     * Returns the public-relative path on success, null on failure.
     */
    private function convertAndSave(string $imageData, string $slug, string $suffix): ?string
    {
        try {
            $encoded = $this->imageManager
                ->read($imageData)
                ->toWebp(self::WEBP_QUALITY);

            $filename  = $slug . $suffix . '.webp';
            $directory = $this->paths->public . '/assets/' . self::LOGO_DIRECTORY;
            $fullPath  = $directory . '/' . $filename;

            file_put_contents($fullPath, $encoded);

            return 'assets/' . self::LOGO_DIRECTORY . '/' . $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ensure the logo storage directory exists and is writable.
     */
    private function ensureDirectoryExists(): void
    {
        $directory = $this->paths->public . '/assets/' . self::LOGO_DIRECTORY;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function buildUrl(string $template, int $espnId): string
    {
        return str_replace('{espn_id}', (string) $espnId, $template);
    }
}
