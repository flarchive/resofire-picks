<?php

namespace Resofire\Picks;

use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

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
];
