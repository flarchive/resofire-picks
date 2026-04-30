<?php

namespace Resofire\Picks\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Resofire\Picks\Pick;
use Resofire\Picks\Team;
use Resofire\Picks\UserScore;
use Resofire\Picks\Week;

class PublicStatsController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertCan('picks.view');

        // ── Current week ─────────────────────────────────────────────────────
        // Find the most recently opened week
        $currentWeek = Week::where('is_open', true)
            ->orderByDesc('week_number')
            ->first();

        $currentWeekId   = $currentWeek?->id;
        $currentWeekName = $currentWeek?->name ?? null;
        $currentWeekNum  = $currentWeek?->week_number ?? null;

        // ── Participation ─────────────────────────────────────────────────────
        $totalPlayers = Pick::distinct('user_id')->count('user_id');

        $uniquePickersThisWeek = $currentWeekId
            ? Pick::whereHas('event', fn ($q) => $q->where('week_id', $currentWeekId))
                ->distinct('user_id')->count('user_id')
            : 0;

        $participationRate = ($totalPlayers > 0 && $currentWeekId)
            ? round($uniquePickersThisWeek / $totalPlayers * 100, 1)
            : null;

        // ── Accuracy ─────────────────────────────────────────────────────────
        $scoredPicks = Pick::whereNotNull('is_correct');
        $avgAccuracyAllTime = null;

        if ($scoredPicks->count() > 0) {
            $correct = (clone $scoredPicks)->where('is_correct', true)->count();
            $total   = $scoredPicks->count();
            $avgAccuracyAllTime = round($correct / $total * 100, 1);
        }

        $avgAccuracyThisWeek = null;
        if ($currentWeekId) {
            $weekScored  = Pick::whereNotNull('is_correct')
                ->whereHas('event', fn ($q) => $q->where('week_id', $currentWeekId));
            $weekTotal   = $weekScored->count();
            $weekCorrect = (clone $weekScored)->where('is_correct', true)->count();

            if ($weekTotal > 0) {
                $avgAccuracyThisWeek = round($weekCorrect / $weekTotal * 100, 1);
            }
        }

        // ── Season leader ─────────────────────────────────────────────────────
        // Top user by total_points in the all-time scope (week_id = null, season_id = null)
        $seasonLeader     = null;
        $topScore = UserScore::whereNull('week_id')
            ->whereNull('season_id')
            ->where('total_picks', '>', 0)
            ->orderByDesc('total_points')
            ->with('user')
            ->first();

        if ($topScore && $topScore->user) {
            $seasonLeader = [
                'display_name' => $topScore->user->display_name ?? $topScore->user->username,
                'avatar_url'   => $topScore->user->avatarUrl,
                'total_points' => $topScore->total_points,
                'accuracy'     => $topScore->accuracy,
            ];
        }

        // ── Most picked team (all time) ───────────────────────────────────────
        $mostPickedTeam = null;

        $homeTop = Pick::join('picks_events', 'picks_picks.event_id', '=', 'picks_events.id')
            ->where('picks_picks.selected_outcome', 'home')
            ->groupBy('picks_events.home_team_id')
            ->selectRaw('picks_events.home_team_id as team_id, COUNT(*) as cnt')
            ->orderByDesc('cnt')
            ->first();

        $awayTop = Pick::join('picks_events', 'picks_picks.event_id', '=', 'picks_events.id')
            ->where('picks_picks.selected_outcome', 'away')
            ->groupBy('picks_events.away_team_id')
            ->selectRaw('picks_events.away_team_id as team_id, COUNT(*) as cnt')
            ->orderByDesc('cnt')
            ->first();

        $topTeamId  = null;
        $topTeamCnt = 0;

        if ($homeTop && $homeTop->cnt > $topTeamCnt) {
            $topTeamId  = $homeTop->team_id;
            $topTeamCnt = $homeTop->cnt;
        }
        if ($awayTop && $awayTop->cnt > $topTeamCnt) {
            $topTeamId  = $awayTop->team_id;
            $topTeamCnt = $awayTop->cnt;
        }

        if ($topTeamId) {
            $team = Team::find($topTeamId);
            if ($team) {
                $baseUrl = rtrim($this->settings->get('url', ''), '/');
                $mostPickedTeam = [
                    'name'         => $team->name,
                    'abbreviation' => $team->abbreviation,
                    'logo_url'     => $team->logo_path
                        ? $baseUrl . '/' . ltrim($team->logo_path, '/')
                        : null,
                    'logo_dark_url' => $team->logo_dark_path
                        ? $baseUrl . '/' . ltrim($team->logo_dark_path, '/')
                        : null,
                    'picks'        => $topTeamCnt,
                ];
            }
        }

        return new JsonResponse([
            'current_week' => [
                'id'          => $currentWeekId,
                'name'        => $currentWeekName,
                'week_number' => $currentWeekNum,
            ],
            'participation' => [
                'total_players'        => $totalPlayers,
                'pickers_this_week'    => $uniquePickersThisWeek,
                'participation_rate'   => $participationRate,
            ],
            'accuracy' => [
                'avg_accuracy_all_time'  => $avgAccuracyAllTime,
                'avg_accuracy_this_week' => $avgAccuracyThisWeek,
            ],
            'season_leader'   => $seasonLeader,
            'most_picked_team' => $mostPickedTeam,
        ]);
    }
}
