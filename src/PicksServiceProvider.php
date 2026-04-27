<?php

namespace Resofire\Picks;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Intervention\Image\ImageManager;
use Resofire\Picks\Api\Controller\ListEventsController;
use Resofire\Picks\Service\CfbdService;
use Resofire\Picks\Service\LogoService;
use Resofire\Picks\Service\ScheduleSyncService;
use Resofire\Picks\Service\TeamSyncService;
class PicksServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ListEventsController::class, function ($container) {
            return new ListEventsController(
                $container->make(SettingsRepositoryInterface::class)
            );
        });

        $this->container->singleton(CfbdService::class, function ($container) {
            return new CfbdService(
                $container->make(SettingsRepositoryInterface::class)
            );
        });

        $this->container->singleton(LogoService::class, function ($container) {
            return new LogoService(
                $container->make('image'),
                $container->make(Paths::class),
                $container->make(SettingsRepositoryInterface::class)
            );
        });

        $this->container->singleton(TeamSyncService::class, function ($container) {
            return new TeamSyncService(
                $container->make(CfbdService::class),
                $container->make(LogoService::class),
                $container->make(SettingsRepositoryInterface::class)
            );
        });

        $this->container->singleton(ScheduleSyncService::class, function ($container) {
            return new ScheduleSyncService(
                $container->make(CfbdService::class),
                $container->make(SettingsRepositoryInterface::class)
            );
        });
    }
}
