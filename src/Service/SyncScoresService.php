<?php

namespace Resofire\Picks\Service;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Arr;
use Resofire\Picks\Jobs\ScorePicksJob;
use Resofire\Picks\Pick;
use Resofire\Picks\PickEvent;

class SyncScoresService
{
    public function __construct(
        protected CfbdService $cfbd,
        protected SettingsRepositoryInterface $settings,
        protected Queue $queue
    ) {
    }

    /**
     * Fetch completed game scores from CFBD and update picks_events.
     *
     * For each game that CFBD marks as completed with scores:
     * - Updates home_score, away_score, status, result on the event
     * - Dispatches ScorePicksJob for events that have picks and just became finished
     *
     * Returns a summary of what changed.
     */
    public function sync(): array
    {
        $year           = (int) $this->settings->get('resofire-picks.season_year', (int) date('Y'));
        $syncRegular    = (bool) $this->settings->get('resofire-picks.sync_regular_season', true);
        $syncPostseason = (bool) $this->settings->get('resofire-picks.sync_postseason', true);

        $updated = 0;
        $scored  = 0;
        $skipped = 0;

        // Fetch all weeks for this year so we know which week numbers exist
        $seasonTypes = [];
        if ($syncRegular)    $seasonTypes[] = 'regular';
        if ($syncPostseason) $seasonTypes[] = 'postseason';

        foreach ($seasonTypes as $seasonType) {
            $weekNumbers = $this->getWeekNumbers($seasonType);

            foreach ($weekNumbers as $weekNumber) {
                $apiGames = $this->cfbd->fetchGames($year, $seasonType, $weekNumber);

                foreach ($apiGames as $apiGame) {
                    $cfbdId    = Arr::get($apiGame, 'id');
                    $completed = (bool) Arr::get($apiGame, 'completed', false);
                    $homePts   = Arr::get($apiGame, 'homePoints');
                    $awayPts   = Arr::get($apiGame, 'awayPoints');

                    // Only process completed games with actual scores
                    if (! $completed || $homePts === null || $awayPts === null) {
                        $skipped++;
                        continue;
                    }

                    $event = PickEvent::where('cfbd_id', $cfbdId)->first();

                    if (! $event) {
                        $skipped++;
                        continue;
                    }

                    // Skip if already finished with the same scores
                    if (
                        $event->status === PickEvent::STATUS_FINISHED
                        && $event->home_score === (int) $homePts
                        && $event->away_score === (int) $awayPts
                    ) {
                        $skipped++;
                        continue;
                    }

                    $wasAlreadyFinished = $event->status === PickEvent::STATUS_FINISHED;

                    $event->home_score = (int) $homePts;
                    $event->away_score = (int) $awayPts;
                    $event->status     = PickEvent::STATUS_FINISHED;
                    $event->result     = $event->calculateResult();
                    $event->save();

                    $updated++;

                    // Dispatch scoring job if this event has picks and
                    // either just became finished or scores changed
                    $hasPicks = Pick::where('event_id', $event->id)->exists();

                    if ($hasPicks) {
                        $this->queue->push(new ScorePicksJob($event->id));
                        $scored++;
                    }
                }
            }
        }

        $this->settings->set(
            'resofire-picks.last_scores_sync',
            Carbon::now()->toIso8601String()
        );

        return compact('updated', 'scored', 'skipped');
    }

    /**
     * Get the week numbers to sync for a given season type.
     * For regular season, reads weeks from picks_weeks in DB.
     * For postseason, always uses week 1 (all bowl games are week 1 in CFBD).
     */
    private function getWeekNumbers(string $seasonType): array
    {
        if ($seasonType === 'postseason') {
            return [1];
        }

        return \Resofire\Picks\Week::where('season_type', 'regular')
            ->whereHas('season', function ($q) {
                $year = (int) $this->settings->get('resofire-picks.season_year', (int) date('Y'));
                $q->where('year', $year);
            })
            ->pluck('week_number')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
