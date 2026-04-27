<?php

namespace Resofire\Picks\Service;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Resofire\Picks\Team;

class TeamSyncService
{
    /**
     * Valid FBS conference abbreviations used to filter teams returned by CFBD.
     * The /teams endpoint does not support server-side classification filtering,
     * so we filter client-side by checking the classification field on each team.
     */
    protected const FBS_CLASSIFICATION = 'fbs';

    public function __construct(
        protected CfbdService $cfbd,
        protected LogoService $logoService,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * Sync FBS teams from CFBD into the picks_teams table.
     *
     * Logo downloads are done in a separate pass to keep the sync fast and
     * avoid PHP execution timeout on large datasets. Pass $downloadLogos = true
     * to also download logos in the same request (only safe for small batches).
     */
    public function sync(bool $downloadLogos = false): array
    {
        $apiTeams = $this->cfbd->fetchTeams();

        // Filter to FBS only — CFBD /teams returns all classifications
        $apiTeams = array_filter($apiTeams, function (array $team) {
            $classification = strtolower((string) Arr::get($team, 'classification', ''));
            return $classification === self::FBS_CLASSIFICATION;
        });

        $created = 0;
        $updated = 0;
        $logos   = 0;
        $errors  = [];

        foreach ($apiTeams as $apiTeam) {
            $cfbdId     = Arr::get($apiTeam, 'id');
            $espnId     = Arr::get($apiTeam, 'espn_id');
            $name       = Arr::get($apiTeam, 'school');
            $abbrev     = Arr::get($apiTeam, 'abbreviation');
            $conference = Arr::get($apiTeam, 'conference');

            if (! $cfbdId || ! $name) {
                continue;
            }

            $slug = Str::slug($name);

            $team = Team::where('cfbd_id', $cfbdId)->first()
                ?? Team::where('slug', $slug)->first();

            $isNew = $team === null;

            if ($isNew) {
                $team       = new Team();
                $team->slug = $slug;
            }

            $team->name         = $name;
            $team->abbreviation = $abbrev;
            $team->conference   = $conference;
            $team->cfbd_id      = $cfbdId;

            if ($espnId) {
                $team->espn_id = (int) $espnId;
            }

            $team->save();

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }

            // Only download logos when explicitly requested and safe to do so
            if (
                $downloadLogos
                && $espnId
                && ! $team->logo_custom
                && ($isNew || ! $team->logo_path)
            ) {
                try {
                    $paths = $this->logoService->downloadAndStore((int) $espnId, $team->slug);

                    $dirty = false;

                    if ($paths['logo_path'] !== null) {
                        $team->logo_path = $paths['logo_path'];
                        $dirty = true;
                    }

                    if ($paths['logo_dark_path'] !== null) {
                        $team->logo_dark_path = $paths['logo_dark_path'];
                        $dirty = true;
                    }

                    if ($dirty) {
                        $team->save();
                        $logos++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Logo download failed for {$name}: " . $e->getMessage();
                }
            }
        }

        $this->settings->set(
            'resofire-picks.last_teams_sync',
            Carbon::now()->toIso8601String()
        );

        return compact('created', 'updated', 'logos', 'errors');
    }

    /**
     * Download logos for all FBS teams that are missing them.
     * Runs in batches to avoid timeouts. Returns counts of logos saved.
     */
    public function syncLogos(int $batchSize = 20): array
    {
        $teams = Team::whereNull('logo_path')
            ->whereNotNull('espn_id')
            ->where('logo_custom', false)
            ->limit($batchSize)
            ->get();

        $saved  = 0;
        $failed = 0;

        foreach ($teams as $team) {
            try {
                $paths = $this->logoService->downloadAndStore($team->espn_id, $team->slug);

                $dirty = false;

                if ($paths['logo_path'] !== null) {
                    $team->logo_path = $paths['logo_path'];
                    $dirty = true;
                }

                if ($paths['logo_dark_path'] !== null) {
                    $team->logo_dark_path = $paths['logo_dark_path'];
                    $dirty = true;
                }

                if ($dirty) {
                    $team->save();
                    $saved++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $remaining = Team::whereNull('logo_path')
            ->whereNotNull('espn_id')
            ->where('logo_custom', false)
            ->count();

        return compact('saved', 'failed', 'remaining');
    }

    /**
     * Re-download logos for a single team. Respects logo_custom flag.
     */
    public function refreshLogos(Team $team): bool
    {
        if ($team->logo_custom || ! $team->espn_id) {
            return false;
        }

        $paths = $this->logoService->downloadAndStore($team->espn_id, $team->slug);

        $saved = false;

        if ($paths['logo_path'] !== null) {
            $team->logo_path = $paths['logo_path'];
            $saved = true;
        }

        if ($paths['logo_dark_path'] !== null) {
            $team->logo_dark_path = $paths['logo_dark_path'];
        }

        if ($saved) {
            $team->save();
        }

        return $saved;
    }
}
