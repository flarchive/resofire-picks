<?php

namespace Resofire\Picks\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class PicksPolicy extends AbstractPolicy
{
    /**
     * Admins can always manage picks.
     */
    public function manage(User $actor): ?string
    {
        if ($actor->isAdmin()) {
            return $this->allow();
        }

        return $actor->hasPermission('picks.manage')
            ? $this->allow()
            : null;
    }

    /**
     * Users with the view permission can view the picks page.
     */
    public function view(User $actor): ?string
    {
        if ($actor->isAdmin()) {
            return $this->allow();
        }

        return $actor->hasPermission('picks.view')
            ? $this->allow()
            : null;
    }

    /**
     * Users with the makePicks permission can make picks.
     */
    public function makePicks(User $actor): ?string
    {
        if ($actor->isAdmin()) {
            return $this->allow();
        }

        return $actor->hasPermission('picks.makePicks')
            ? $this->allow()
            : null;
    }
}
