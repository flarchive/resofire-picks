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
     * Sync live and completed scores from the ESPN scoreboard API.
     * No auth required — ESPN's scoreboard is public.
     *
     * Handles three states based on status.type.state:
     * - "pre"  → skip (not started)
     * - "in"   → update scores on event, mark as in_progress, no scoring job
     * - "post" → update scores, mark as finished, dispatch ScorePicksJob if picks exist
     *
     * Returns summary counts.
     */
    public function syncFromEspn(): array
    {
        $url      = 'https://site.api.espn.com/apis/site/v2/sports/football/college-football/scoreboard';
        $response = $this->fetchUrl($url);

        if ($response === null) {
            throw new \RuntimeException('Failed to fetch ESPN scoreboard.');
        }

        $events    = $response['events'] ?? [];
        $updated   = 0;
        $finished  = 0;
        $skipped   = 0;

        foreach ($events as $espnEvent) {
            $espnId      = $espnEvent['id'] ?? null;
            $competition = $espnEvent['competitions'][0] ?? null;

            if (! $espnId || ! $competition) {
                $skipped++;
                continue;
            }

            $statusType = $competition['status']['type'] ?? [];
            $state      = $statusType['state'] ?? 'pre';
            $completed  = (bool) ($statusType['completed'] ?? false);

            // Skip games that haven't started
            if ($state === 'pre') {
                $skipped++;
                continue;
            }

            // Match to our event by cfbd_id (ESPN event id = CFBD game id)
            $event = PickEvent::where('cfbd_id', (int) $espnId)->first();

            if (! $event) {
                $skipped++;
                continue;
            }

            // Parse scores from competitors
            $homeScore = null;
            $awayScore = null;

            foreach ($competition['competitors'] ?? [] as $competitor) {
                $side  = $competitor['homeAway'] ?? null;
                $score = isset($competitor['score']) ? (int) $competitor['score'] : null;

                if ($side === 'home') $homeScore = $score;
                if ($side === 'away') $awayScore = $score;
            }

            if ($homeScore === null || $awayScore === null) {
                $skipped++;
                continue;
            }

            $wasFinished = $event->status === PickEvent::STATUS_FINISHED;

            $event->home_score = $homeScore;
            $event->away_score = $awayScore;

            if ($completed) {
                $event->status = PickEvent::STATUS_FINISHED;
                $event->result = $event->calculateResult();
            } else {
                // In progress — update score but don't finalize
                $event->status = 'in_progress';
            }

            $event->save();
            $updated++;

            // Only dispatch scoring job when a game just became finished
            if ($completed && ! $wasFinished) {
                $hasPicks = Pick::where('event_id', $event->id)->exists();
                if ($hasPicks) {
                    $this->queue->push(new ScorePicksJob($event->id));
                    $finished++;
                }
            }
        }

        $this->settings->set(
            'resofire-picks.last_scores_sync',
            Carbon::now()->toIso8601String()
        );

        return compact('updated', 'finished', 'skipped');
    }

    /**
     * Fetch a URL via curl and return decoded JSON, or null on failure.
     */
    private function fetchUrl(string $url): ?array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'resofire/picks',
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            return null;
        }

        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
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
