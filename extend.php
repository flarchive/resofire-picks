<?php

namespace Resofire\Picks;

use Flarum\Extend;
use Resofire\Picks\Api\Controller\RefreshTeamLogoController;
use Resofire\Picks\Api\Controller\SyncLogosController;
use Resofire\Picks\Api\Controller\SyncScheduleController;
use Resofire\Picks\Api\Controller\SyncTeamsController;
use Resofire\Picks\Api\Resource\EventResource;
use Resofire\Picks\Api\Resource\SeasonResource;
use Resofire\Picks\Api\Resource\TeamResource;
use Resofire\Picks\Api\Resource\WeekResource;
use Resofire\Picks\Console\SyncTeamsCommand;
use Resofire\Picks\PicksServiceProvider;

return [
    // -------------------------------------------------------------------------
    // Service provider — binds services with explicit dependencies
    // -------------------------------------------------------------------------
    (new Extend\ServiceProvider())
        ->register(PicksServiceProvider::class),

    // -------------------------------------------------------------------------
    // Frontend assets
    // -------------------------------------------------------------------------
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    // -------------------------------------------------------------------------
    // Settings defaults
    // -------------------------------------------------------------------------
    (new Extend\Settings())
        ->default('resofire-picks.cfbd_api_key', '')
        ->default('resofire-picks.season_year', (int) date('Y'))
        ->default('resofire-picks.conference_filter', '')
        ->default('resofire-picks.sync_regular_season', true)
        ->default('resofire-picks.sync_postseason', true)
        ->default('resofire-picks.auto_sync_enabled', false)
        ->default('resofire-picks.reverse_display', false)
        ->default('resofire-picks.picks_lock_offset_minutes', 0)
        ->default('resofire-picks.confidence_mode', false)
        ->default('resofire-picks.default_week_view', 'current')
        ->default('resofire-picks.last_teams_sync', null)
        ->default('resofire-picks.last_schedule_sync', null)
        ->default('resofire-picks.last_scores_sync', null),

    // -------------------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------------------
    (new Extend\Policy())
        ->globalPolicy(Access\PicksPolicy::class),

    // -------------------------------------------------------------------------
    // API Resources
    // -------------------------------------------------------------------------
    new Extend\ApiResource(TeamResource::class),
    new Extend\ApiResource(SeasonResource::class),
    new Extend\ApiResource(WeekResource::class),
    new Extend\ApiResource(EventResource::class),

    // -------------------------------------------------------------------------
    // Custom API routes (non-resource actions)
    // -------------------------------------------------------------------------
    (new Extend\Routes('api'))
        ->post('/picks/sync/teams', 'picks.sync.teams', SyncTeamsController::class)
        ->post('/picks/sync/logos', 'picks.sync.logos', SyncLogosController::class)
        ->post('/picks/sync/schedule', 'picks.sync.schedule', SyncScheduleController::class)
        ->post('/picks/teams/{id}/refresh-logo', 'picks.teams.refresh-logo', RefreshTeamLogoController::class),

    // Note: No separate admin frontend route needed — PicksPage extends
    // ExtensionPage and is registered via Extend\Admin().page(), so it
    // renders directly in the admin dashboard extension card.

    // -------------------------------------------------------------------------
    // Console commands
    // -------------------------------------------------------------------------
    (new Extend\Console())
        ->command(SyncTeamsCommand::class),
];
