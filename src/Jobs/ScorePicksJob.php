<?php

namespace Resofire\Picks\Jobs;

use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use Illuminate\Support\Collection;
use Resofire\Picks\Pick;
use Resofire\Picks\PickEvent;
use Resofire\Picks\UserScore;

class ScorePicksJob extends AbstractJob
{
    public function __construct(
        protected int $eventId
    ) {
    }

    public function handle(): void
    {
        $event = PickEvent::find($this->eventId);

        if (! $event || ! $event->isFinished() || ! $event->result) {
            return;
        }

        $picks = Pick::where('event_id', $this->eventId)
            ->with('user')
            ->get();

        if ($picks->isEmpty()) {
            return;
        }

        // Score each pick
        foreach ($picks as $pick) {
            $pick->is_correct = ($pick->selected_outcome === $event->result);
            $pick->save();
        }

        // Get unique user IDs affected
        $userIds = $picks->pluck('user_id')->unique()->values();

        // Recalculate scores for each user
        foreach ($userIds as $userId) {
            $this->recalculateUserScore($userId, $event->week_id, $this->getSeasonId($event->week_id));
        }
    }

    /**
     * Recalculate and upsert a user's score for a specific week and season.
     */
    private function recalculateUserScore(int $userId, ?int $weekId, ?int $seasonId): void
    {
        // Week score
        if ($weekId) {
            $this->upsertScore($userId, $weekId, $seasonId, weekScope: true);
        }

        // Season score
        if ($seasonId) {
            $this->upsertScore($userId, null, $seasonId, weekScope: false);
        }

        // All-time score (no week, no season)
        $this->upsertScore($userId, null, null, weekScope: false);
    }

    /**
     * Upsert a single user score row, handling NULL columns correctly.
     * MySQL's unique index treats NULL != NULL, so updateOrCreate with NULLs
     * creates duplicates. We use explicit firstOrNew + save instead.
     */
    private function upsertScore(int $userId, ?int $weekId, ?int $seasonId, bool $weekScope): void
    {
        $query = Pick::where('user_id', $userId)
            ->whereNotNull('is_correct');

        if ($weekScope && $weekId) {
            $query->whereHas('event', fn ($q) => $q->where('week_id', $weekId));
        } elseif ($seasonId) {
            $query->whereHas('event', fn ($q) => $q->whereHas(
                'week', fn ($w) => $w->where('season_id', $seasonId)
            ));
        }

        $totalPicks   = (clone $query)->count();
        $correctPicks = (clone $query)->where('is_correct', true)->count();
        $accuracy     = $totalPicks > 0
            ? round($correctPicks / $totalPicks * 100, 2)
            : 0.0;

        $scoreQuery = UserScore::where('user_id', $userId);

        if ($weekScope && $weekId) {
            $scoreQuery->where('week_id', $weekId)->where('season_id', $seasonId);
        } elseif ($seasonId) {
            $scoreQuery->whereNull('week_id')->where('season_id', $seasonId);
        } else {
            $scoreQuery->whereNull('week_id')->whereNull('season_id');
        }

        $score = $scoreQuery->first() ?? new UserScore();

        $score->user_id       = $userId;
        $score->week_id       = ($weekScope && $weekId) ? $weekId : null;
        $score->season_id     = $seasonId;
        $score->total_picks   = $totalPicks;
        $score->correct_picks = $correctPicks;
        $score->total_points  = $correctPicks;
        $score->accuracy      = $accuracy;
        $score->save();
    }

    private function getSeasonId(?int $weekId): ?int
    {
        if (! $weekId) {
            return null;
        }

        return \Resofire\Picks\Week::find($weekId)?->season_id;
    }
}
