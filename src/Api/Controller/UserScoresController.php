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
 * GET /picks/user-scores?user_id=X
 *
 * Returns alltime, season, and current-week stats for a user.
 * Ported wholesale from StatCards\UserScoresController — same logic,
 * same response shape, updated namespace and route only.
 *
 * Permission: picks.view (same gate as every other picks endpoint).
 */
class UserScoresController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('picks.view');

        $params = $request->getQueryParams();
        $userId = (int) Arr::get($params, 'user_id', 0);

        if (!$userId) {
            return new JsonResponse(['error' => 'user_id required'], 422);
        }

        try {
            // ── Current week + season ─────────────────────────────────────────
            $currentWeek = DB::table('picks_weeks')
                ->where('is_open', true)
                ->orderByDesc('week_number')
                ->first();

            $currentWeekId   = $currentWeek?->id ?? null;
            $currentSeasonId = $currentWeek?->season_id ?? null;
            $currentWeekName = $currentWeek?->name ?? null;

            // ── All-time scores ───────────────────────────────────────────────
            $alltime = DB::table('picks_user_scores')
                ->where('user_id', $userId)
                ->whereNull('week_id')
                ->whereNull('season_id')
                ->first();

            $alltimeRank = null;
            if ($alltime && $alltime->total_picks > 0) {
                $above = DB::table('picks_user_scores')
                    ->whereNull('week_id')
                    ->whereNull('season_id')
                    ->where('total_picks', '>', 0)
                    ->where('total_points', '>', $alltime->total_points)
                    ->count();
                $alltimeRank = $above + 1;
            }

            $totalAlltime = DB::table('picks_user_scores')
                ->whereNull('week_id')->whereNull('season_id')
                ->where('total_picks', '>', 0)->count();

            // ── Season scores ─────────────────────────────────────────────────
            $season     = null;
            $seasonRank = null;
            $totalSeason = 0;

            if ($currentSeasonId) {
                $season = DB::table('picks_user_scores')
                    ->where('user_id', $userId)
                    ->where('season_id', $currentSeasonId)
                    ->whereNull('week_id')
                    ->first();

                if ($season && $season->total_picks > 0) {
                    $above = DB::table('picks_user_scores')
                        ->where('season_id', $currentSeasonId)
                        ->whereNull('week_id')
                        ->where('total_picks', '>', 0)
                        ->where('total_points', '>', $season->total_points)
                        ->count();
                    $seasonRank = $above + 1;
                }

                $totalSeason = DB::table('picks_user_scores')
                    ->where('season_id', $currentSeasonId)->whereNull('week_id')
                    ->where('total_picks', '>', 0)->count();
            }

            // ── Week scores ───────────────────────────────────────────────────
            $week      = null;
            $weekRank  = null;
            $totalWeek = 0;

            if ($currentWeekId) {
                $week = DB::table('picks_user_scores')
                    ->where('user_id', $userId)
                    ->where('week_id', $currentWeekId)
                    ->first();

                if ($week && $week->total_picks > 0) {
                    $above = DB::table('picks_user_scores')
                        ->where('week_id', $currentWeekId)
                        ->where('total_picks', '>', 0)
                        ->where('total_points', '>', $week->total_points)
                        ->count();
                    $weekRank = $above + 1;
                }

                $totalWeek = DB::table('picks_user_scores')
                    ->where('week_id', $currentWeekId)
                    ->where('total_picks', '>', 0)->count();
            }

            return new JsonResponse([
                'current_week_name' => $currentWeekName,
                'alltime' => $alltime ? [
                    'total_picks'   => (int) $alltime->total_picks,
                    'correct_picks' => (int) $alltime->correct_picks,
                    'total_points'  => (int) $alltime->total_points,
                    'accuracy'      => (float) $alltime->accuracy,
                    'rank'          => $alltimeRank,
                    'total_players' => $totalAlltime,
                ] : null,
                'season' => $season ? [
                    'total_picks'   => (int) $season->total_picks,
                    'correct_picks' => (int) $season->correct_picks,
                    'total_points'  => (int) $season->total_points,
                    'accuracy'      => (float) $season->accuracy,
                    'rank'          => $seasonRank,
                    'total_players' => $totalSeason,
                ] : null,
                'week' => $week ? [
                    'total_picks'   => (int) $week->total_picks,
                    'correct_picks' => (int) $week->correct_picks,
                    'total_points'  => (int) $week->total_points,
                    'accuracy'      => (float) $week->accuracy,
                    'rank'          => $weekRank,
                    'total_players' => $totalWeek,
                ] : null,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'current_week_name' => null,
                'alltime' => null,
                'season'  => null,
                'week'    => null,
            ]);
        }
    }
}
