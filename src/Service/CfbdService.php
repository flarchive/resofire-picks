<?php

namespace Resofire\Picks\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use RuntimeException;

class CfbdService
{
    protected const BASE_URL = 'https://api.collegefootballdata.com';

    private Client $client;

    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout'  => 30,
        ]);
    }

    /**
     * Fetch all FBS teams, optionally filtered by conference.
     *
     * Returns an array of team data arrays from the CFBD API.
     *
     * @throws RuntimeException
     */
    public function fetchTeams(): array
    {
        $apiKey = $this->settings->get('resofire-picks.cfbd_api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('CFBD API key is not configured.');
        }

        $params = ['classification' => 'fbs'];

        $conferenceFilter = trim((string) $this->settings->get('resofire-picks.conference_filter', ''));
        if ($conferenceFilter !== '') {
            $params['conference'] = $conferenceFilter;
        }

        try {
            $response = $this->client->get('/teams', [
                'headers' => $this->buildHeaders($apiKey),
                'query'   => $params,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('CFBD API request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = json_decode((string) $response->getBody(), true);

        if (! is_array($body)) {
            throw new RuntimeException('CFBD API returned unexpected response for /teams.');
        }

        return $body;
    }

    /**
     * Fetch the game schedule for a given year, season type, and optional week.
     *
     * @param int         $year
     * @param string      $seasonType  'regular' or 'postseason'
     * @param int|null    $week
     * @param string|null $conference
     *
     * @throws RuntimeException
     */
    public function fetchGames(int $year, string $seasonType, ?int $week = null, ?string $conference = null): array
    {
        $apiKey = $this->settings->get('resofire-picks.cfbd_api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('CFBD API key is not configured.');
        }

        $params = [
            'year'            => $year,
            'seasonType'      => $seasonType,
            'classification'  => 'fbs',
        ];

        if ($week !== null) {
            $params['week'] = $week;
        }

        if ($conference !== null) {
            $params['conference'] = $conference;
        }

        try {
            $response = $this->client->get('/games', [
                'headers' => $this->buildHeaders($apiKey),
                'query'   => $params,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('CFBD API request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = json_decode((string) $response->getBody(), true);

        if (! is_array($body)) {
            throw new RuntimeException('CFBD API returned unexpected response for /games.');
        }

        return $body;
    }

    /**
     * Fetch the weeks available for a given year and season type.
     *
     * @throws RuntimeException
     */
    public function fetchCalendar(int $year): array
    {
        $apiKey = $this->settings->get('resofire-picks.cfbd_api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('CFBD API key is not configured.');
        }

        try {
            $response = $this->client->get('/calendar', [
                'headers' => $this->buildHeaders($apiKey),
                'query'   => ['year' => $year],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('CFBD API request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = json_decode((string) $response->getBody(), true);

        if (! is_array($body)) {
            throw new RuntimeException('CFBD API returned unexpected response for /calendar.');
        }

        return $body;
    }

    private function buildHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept'        => 'application/json',
        ];
    }
}
