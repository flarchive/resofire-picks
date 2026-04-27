<?php

namespace Resofire\Picks\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Resofire\Picks\Team;

class TeamSyncService
{
    public function __construct(
        protected CfbdService $cfbd,
        protected LogoService $logoService,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * Sync all FBS teams from CFBD into the picks_teams table.
     *
     * - Creates new teams that don't exist yet.
     * - Updates existing teams (name, abbreviation, conference, ESPN ID, CFBD ID).
     * - Downloads logos for teams that don't have a custom logo set.
     *
     * Returns a summary array with counts.
     */
    public function sync(): array
    {
        $apiTeams = $this->cfbd->fetchTeams();

        $created  = 0;
        $updated  = 0;
        $logos    = 0;
        $errors   = [];

        foreach ($apiTeams as $apiTeam) {
            $cfbdId    = Arr::get($apiTeam, 'id');
            $espnId    = Arr::get($apiTeam, 'espn_id');
            $name      = Arr::get($apiTeam, 'school');
            $abbrev    = Arr::get($apiTeam, 'abbreviation');
            $conference = Arr::get($apiTeam, 'conference');

            if (! $cfbdId || ! $name) {
                continue;
            }

            $slug = Str::slug($name);

            // Find existing team by CFBD ID first, then fall back to slug.
            $team = Team::where('cfbd_id', $cfbdId)->first()
                ?? Team::where('slug', $slug)->first();

            $isNew = $team === null;

            if ($isNew) {
                $team = new Team();
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

            // Download logos only if:
            // - The team has an ESPN ID
            // - The team does not have a custom logo
            // - Either it's a new team, or it has no logo yet
            if (
                $espnId
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
            now()->toIso8601String()
        );

        return compact('created', 'updated', 'logos', 'errors');
    }

    /**
     * Re-download logos for a single team by its local ID.
     * Respects the logo_custom flag.
     *
     * Returns true if at least the standard logo was saved.
     */
    public function refreshLogos(Team $team): bool
    {
        if ($team->logo_custom) {
            return false;
        }

        if (! $team->espn_id) {
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
