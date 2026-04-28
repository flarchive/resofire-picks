<?php

namespace Resofire\Picks\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Resofire\Picks\Jobs\ScorePicksJob;
use Resofire\Picks\PickEvent;
use Resofire\Picks\Week;

class EnterResultController implements RequestHandlerInterface
{
    public function __construct(
        protected Queue $queue,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertCan('picks.manage');

        $id    = Arr::get($request->getQueryParams(), 'id');
        $event = PickEvent::findOrFail($id);

        $body      = $request->getParsedBody() ?? [];
        $homeScore = Arr::get($body, 'homeScore');
        $awayScore = Arr::get($body, 'awayScore');

        if ($homeScore === null || $awayScore === null) {
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'homeScore and awayScore are required.',
            ], 422);
        }

        $event->home_score = (int) $homeScore;
        $event->away_score = (int) $awayScore;
        $event->status     = PickEvent::STATUS_FINISHED;
        $event->result     = $event->calculateResult();
        $event->save();

        // Dispatch scoring job to the queue
        $this->queue->push(new ScorePicksJob($event->id));

        // Auto-unlock next week if enabled and this week is now complete
        $nextWeekUnlocked = false;
        if ($this->settings->get('resofire-picks.auto_unlock_weeks', false) && $event->week_id) {
            $nextWeekUnlocked = $this->maybeUnlockNextWeek($event->week_id);
        }

        return new JsonResponse([
            'status'           => 'success',
            'id'               => $event->id,
            'homeScore'        => $event->home_score,
            'awayScore'        => $event->away_score,
            'result'           => $event->result,
            'gameStatus'       => $event->status,
            'nextWeekUnlocked' => $nextWeekUnlocked,
        ]);
    }

    /**
     * Check if all games in the given week are finished.
     * If so, find the next sequential week and open it.
     * Returns true if a week was unlocked.
     */
    private function maybeUnlockNextWeek(int $weekId): bool
    {
        // Check if any games in this week are still unfinished
        $unfinished = PickEvent::where('week_id', $weekId)
            ->where('status', '!=', PickEvent::STATUS_FINISHED)
            ->exists();

        if ($unfinished) {
            return false;
        }

        // All games finished — find the current week
        $currentWeek = Week::find($weekId);
        if (! $currentWeek) {
            return false;
        }

        // Find the next week in the same season by week_number
        $nextWeek = Week::where('season_id', $currentWeek->season_id)
            ->where('week_number', '>', $currentWeek->week_number)
            ->where('season_type', $currentWeek->season_type)
            ->orderBy('week_number')
            ->first();

        // If no next regular week, check for postseason
        if (! $nextWeek && $currentWeek->season_type === 'regular') {
            $nextWeek = Week::where('season_id', $currentWeek->season_id)
                ->where('season_type', 'postseason')
                ->orderBy('week_number')
                ->first();
        }

        if (! $nextWeek || $nextWeek->is_open) {
            return false;
        }

        $nextWeek->is_open = true;
        $nextWeek->save();

        return true;
    }
}
