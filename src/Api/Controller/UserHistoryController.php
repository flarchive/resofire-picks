<?php

namespace Resofire\Picks\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /picks/user-history?user_id=X
 *
 * Returns the full season-by-season pick history for a user, including
 * per-week breakdowns within each season. Used by the profile history stack.
 *
 * Permission:
 *   - Viewing own history: picks.view
 *   - Viewing another user's history: picks.viewHistory
 *   - Admins: always allowed (handled by PicksPolicy)
 */
class UserHistoryController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor  = RequestUtil::getActor($request);
        $params = $request->getQueryParams();
        $userId = (int) Arr::get($params, 'user_id', 0);

        if (!$userId) {
            return new JsonResponse(['error' => 'user_id required'], 422);
        }

        // Own history requires picks.view; another user's history requires picks.viewHistory
        if ($actor->id === $userId) {
            $actor->assertCan('picks.view');
        } else {
            $actor->assertCan('picks.viewHistory');
        }

        try {
            // ── All seasons, newest first ─────────────────────────────────────
            $seasons = DB::table('picks_seasons')
                ->orderByDesc('year')
                ->get();

            // ── Current open week (to flag in-progress) ───────────────────────
            $openWeek = DB::table('picks_weeks')
                ->where('is_open', true)
                ->orderByDesc('week_number')
                ->first();

            $currentSeasonId = $openWeek?->season_id ?? null;

            // ── All-time stats ────────────────────────────────────────────────
            $alltimeRow = DB::table('picks_user_scores')
                ->where('user_id', $userId)
                ->whereNull('week_id')
                ->whereNull('season_id')
                ->first();

            $alltimeRank       = null;
            $alltimeTotalPlayers = 0;

            if ($alltimeRow && $alltimeRow->total_picks > 0) {
                $above = DB::table('picks_user_scores')
                    ->whereNull('week_id')
                    ->whereNull('season_id')
                    ->where('total_picks', '>', 0)
                    ->where('total_points', '>', $alltimeRow->total_points)
                    ->count();
                $alltimeRank = $above + 1;

                $alltimeTotalPlayers = DB::table('picks_user_scores')
                    ->whereNull('week_id')
                    ->whereNull('season_id')
                    ->where('total_picks', '>', 0)
                    ->count();
            }

            // ── Longest correct streak across all picks ───────────────────────
            $longestStreak = $this->calculateLongestStreak($userId);

            // ── Best single week ──────────────────────────────────────────────
            $bestWeekRow = DB::table('picks_user_scores')
                ->join('picks_weeks', 'picks_user_scores.week_id', '=', 'picks_weeks.id')
                ->join('picks_seasons', 'picks_weeks.season_id', '=', 'picks_seasons.id')
                ->where('picks_user_scores.user_id', $userId)
                ->whereNotNull('picks_user_scores.week_id')
                ->where('picks_user_scores.total_picks', '>', 0)
                ->orderByDesc('picks_user_scores.accuracy')
                ->orderByDesc('picks_user_scores.total_points')
                ->select([
                    'picks_user_scores.id',
                    'picks_user_scores.user_id',
                    'picks_user_scores.season_id',
                    'picks_user_scores.week_id',
                    'picks_user_scores.total_picks',
                    'picks_user_scores.correct_picks',
                    'picks_user_scores.total_points',
                    'picks_user_scores.accuracy',
                    'picks_weeks.name as week_name',
                    'picks_seasons.year as season_year',
                ])
                ->first();

            $bestWeek = null;
            if ($bestWeekRow) {
                $bestWeek = [
                    'week_name'     => $bestWeekRow->week_name,
                    'season_year'   => (int) $bestWeekRow->season_year,
                    'accuracy'      => (float) $bestWeekRow->accuracy,
                    'correct_picks' => (int) $bestWeekRow->correct_picks,
                    'total_picks'   => (int) $bestWeekRow->total_picks,
                    'total_points'  => (int) $bestWeekRow->total_points,
                ];
            }

            // ── Season details ────────────────────────────────────────────────
            $seasonsData = [];

            foreach ($seasons as $season) {
                // Season-level score for this user
                $seasonScore = DB::table('picks_user_scores')
                    ->where('user_id', $userId)
                    ->where('season_id', $season->id)
                    ->whereNull('week_id')
                    ->first();

                // Season rank
                $seasonRank        = null;
                $seasonTotalPlayers = 0;

                if ($seasonScore && $seasonScore->total_picks > 0) {
                    $above = DB::table('picks_user_scores')
                        ->where('season_id', $season->id)
                        ->whereNull('week_id')
                        ->where('total_picks', '>', 0)
                        ->where('total_points', '>', $seasonScore->total_points)
                        ->count();
                    $seasonRank = $above + 1;

                    $seasonTotalPlayers = DB::table('picks_user_scores')
                        ->where('season_id', $season->id)
                        ->whereNull('week_id')
                        ->where('total_picks', '>', 0)
                        ->count();
                }

                // All weeks in this season that the user has scores for
                $weekScores = DB::table('picks_user_scores')
                    ->join('picks_weeks', 'picks_user_scores.week_id', '=', 'picks_weeks.id')
                    ->where('picks_user_scores.user_id', $userId)
                    ->where('picks_weeks.season_id', $season->id)
                    ->whereNotNull('picks_user_scores.week_id')
                    ->orderByDesc('picks_weeks.week_number')
                    ->select([
                        'picks_user_scores.id',
                        'picks_user_scores.user_id',
                        'picks_user_scores.season_id',
                        'picks_user_scores.week_id',
                        'picks_user_scores.total_picks',
                        'picks_user_scores.correct_picks',
                        'picks_user_scores.total_points',
                        'picks_user_scores.accuracy',
                        'picks_weeks.name as week_name',
                        'picks_weeks.week_number',
                        'picks_weeks.is_open',
                    ])
                    ->get();

                $weeksData = [];
                foreach ($weekScores as $ws) {
                    // Week rank
                    $weekRank = null;
                    if ($ws->total_picks > 0) {
                        $above = DB::table('picks_user_scores')
                            ->where('week_id', $ws->week_id)
                            ->where('total_picks', '>', 0)
                            ->where('total_points', '>', $ws->total_points)
                            ->count();
                        $weekRank = $above + 1;
                    }

                    $weeksData[] = [
                        'week_id'       => (int) $ws->week_id,
                        'week_name'     => $ws->week_name,
                        'week_number'   => (int) $ws->week_number,
                        'is_open'       => (bool) $ws->is_open,
                        'total_picks'   => (int) $ws->total_picks,
                        'correct_picks' => (int) $ws->correct_picks,
                        'total_points'  => (int) $ws->total_points,
                        'accuracy'      => (float) $ws->accuracy,
                        'rank'          => $weekRank,
                    ];
                }

                $seasonsData[] = [
                    'season_id'    => (int) $season->id,
                    'name'         => $season->name,
                    'year'         => (int) $season->year,
                    'is_current'   => ((int) $season->id === (int) $currentSeasonId),
                    'stats'        => $seasonScore ? [
                        'total_picks'   => (int) $seasonScore->total_picks,
                        'correct_picks' => (int) $seasonScore->correct_picks,
                        'total_points'  => (int) $seasonScore->total_points,
                        'accuracy'      => (float) $seasonScore->accuracy,
                        'rank'          => $seasonRank,
                        'total_players' => $seasonTotalPlayers,
                    ] : null,
                    'weeks'        => $weeksData,
                ];
            }

            return new JsonResponse([
                'alltime' => $alltimeRow ? [
                    'total_picks'    => (int) $alltimeRow->total_picks,
                    'correct_picks'  => (int) $alltimeRow->correct_picks,
                    'total_points'   => (int) $alltimeRow->total_points,
                    'accuracy'       => (float) $alltimeRow->accuracy,
                    'rank'           => $alltimeRank,
                    'total_players'  => $alltimeTotalPlayers,
                    'longest_streak' => $longestStreak,
                    'best_week'      => $bestWeek,
                ] : null,
                'seasons' => $seasonsData,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to load history.'], 500);
        }
    }

    /**
     * Calculate the longest consecutive correct picks streak for a user.
     * Walks picks ordered by their event's match_date ascending.
     */
    private function calculateLongestStreak(int $userId): int
    {
        $picks = DB::table('picks_picks')
            ->join('picks_events', 'picks_picks.event_id', '=', 'picks_events.id')
            ->where('picks_picks.user_id', $userId)
            ->whereNotNull('picks_picks.is_correct')
            ->orderBy('picks_events.match_date')
            ->orderBy('picks_events.id')
            ->pluck('picks_picks.is_correct');

        $longest = 0;
        $current = 0;

        foreach ($picks as $isCorrect) {
            if ($isCorrect) {
                $current++;
                if ($current > $longest) {
                    $longest = $current;
                }
            } else {
                $current = 0;
            }
        }

        return $longest;
    }
}
